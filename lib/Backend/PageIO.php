<?php

namespace OCA\Appointments\Backend;

use OCA\Appointments\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\IDBConnection;

/**
 * Page provisioning, export and import. Used by the occ commands and the
 * state API, so both apply the same rules.
 */
class PageIO
{
    public const FORMAT = 'appointments-page';
    public const VERSION = 1;

    /**
     * Never exported or imported: credentials.
     * Keys ending in Secret/Pass/Password are excluded as well, unless their
     * value is an on/off flag (talk_password and bbbPassword only say whether
     * a meeting password is generated, they hold no password).
     */
    public const SECRET_KEYS = [
        BackendUtils::SEC_HCAP_SECRET,
    ];

    /**
     * Never exported or imported: derived, read-only or carried elsewhere.
     */
    public const INTERNAL_KEYS = [
        BackendUtils::PAGE_LABEL, // page.label
        BackendUtils::KEY_REMINDERS, // page.reminders
        BackendUtils::KEY_FORM_INPUTS_HTML, // rebuilt from fi_json
        BackendUtils::KEY_TMPL_DATA_SORTED,
        BackendUtils::TALK_FORM_DEF_LABEL,
        BackendUtils::TALK_FORM_DEF_PLACEHOLDER,
        BackendUtils::TALK_FORM_DEF_REAL,
        BackendUtils::TALK_FORM_DEF_VIRTUAL,
        BackendUtils::TALK_INTEGRATION_DISABLED,
        BackendUtils::BBB_INTEGRATION_DISABLED,
    ];

    public function __construct(private BackendUtils      $utils,
                                private SettingsValidator $validator,
                                private IDBConnection     $db,
                                private IAppManager       $appManager)
    {
    }

    public static function isExcludedKey(string $key, $defaultValue): bool
    {
        if (in_array($key, self::SECRET_KEYS, true) || in_array($key, self::INTERNAL_KEYS, true)) {
            return true;
        }
        return !is_bool($defaultValue) && preg_match('/(secret|pass|password)$/i', $key) === 1;
    }

    /**
     * Keys that can be exported/imported, with their default values.
     */
    public function getExportableDefaults(): array
    {
        $out = [];
        foreach ($this->utils->getDefaultSettingsData() as $k => $v) {
            if (!self::isExcludedKey($k, $v)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    public static function excludedKeysHelp(): string
    {
        return implode(', ', array_merge(self::SECRET_KEYS, ['any other key ending in Secret, Pass or Password that holds a value']))
            . '. Not exported either (derived or read-only): ' . implode(', ', array_slice(self::INTERNAL_KEYS, 2)) . '.';
    }

    /**
     * @return array[] [['id'=>'p0', 'token'=>..., 'label'=>..., 'enabled'=>bool], ...]
     */
    public function listPages(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $r = $qb->select(BackendUtils::KEY_PAGE_ID, BackendUtils::KEY_TOKEN, BackendUtils::KEY_DATA)
            ->from(BackendUtils::PREF_TABLE_V2_NAME)
            ->where($qb->expr()->eq(BackendUtils::KEY_USER_ID, $qb->createNamedParameter($userId)))
            ->orderBy(BackendUtils::KEY_PAGE_ID)
            ->executeQuery();
        $pages = [];
        while ($row = $r->fetch()) {
            $pageId = $row[BackendUtils::KEY_PAGE_ID];
            if ($this->utils->isDir($pageId)) {
                continue;
            }
            $data = json_decode($row[BackendUtils::KEY_DATA] ?? '', true) ?? [];
            $pages[] = [
                'id' => $pageId,
                'token' => $row[BackendUtils::KEY_TOKEN],
                'label' => $data[BackendUtils::PAGE_LABEL] ?? '',
                'enabled' => (bool)($data[BackendUtils::PAGE_ENABLED] ?? false),
            ];
        }
        $r->closeCursor();
        usort($pages, fn($a, $b) => intval(substr($a['id'], 1)) <=> intval(substr($b['id'], 1)));
        return $pages;
    }

    /**
     * First free page id ('p0', 'p1', ...), filling holes first.
     */
    public function nextPageId(string $userId): string
    {
        $used = [];
        foreach ($this->listPages($userId) as $page) {
            $used[intval(substr($page['id'], 1))] = true;
        }
        $n = 0;
        while (isset($used[$n])) {
            $n++;
        }
        return 'p' . $n;
    }

    private function getRow(string $userId, string $pageId): ?array
    {
        $qb = $this->db->getQueryBuilder();
        $r = $qb->select(BackendUtils::KEY_DATA, BackendUtils::KEY_REMINDERS)
            ->from(BackendUtils::PREF_TABLE_V2_NAME)
            ->where($qb->expr()->eq(BackendUtils::KEY_USER_ID, $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq(BackendUtils::KEY_PAGE_ID, $qb->createNamedParameter($pageId)))
            ->executeQuery();
        $row = $r->fetch();
        $r->closeCursor();
        return $row === false ? null : $row;
    }

    /**
     * Creates a page. With $fromPageId all settings (reminders included) are
     * copied from that page of the same user. No page count limit is applied.
     *
     * @return array{string, string} [pageId, token]
     * @throws \InvalidArgumentException
     */
    public function createPage(string $userId, ?string $token = null, ?string $label = null, ?string $fromPageId = null): array
    {
        $reminders = null;
        if ($fromPageId !== null) {
            if ($this->utils->isDir($fromPageId)) {
                throw new \InvalidArgumentException("can not copy from a directory page");
            }
            $row = $this->getRow($userId, $fromPageId);
            if ($row === null) {
                throw new \InvalidArgumentException("page " . $fromPageId . " not found for user " . $userId);
            }
            $data = json_decode($row[BackendUtils::KEY_DATA], true);
            if (!is_array($data)) {
                throw new \InvalidArgumentException("page " . $fromPageId . " has invalid data");
            }
            // a copy starts disabled, like any new page
            unset($data[BackendUtils::PAGE_ENABLED]);
            $reminders = $row[BackendUtils::KEY_REMINDERS];
        } else {
            $data = [
                BackendUtils::PAGE_ENABLED => false,
                BackendUtils::PAGE_LABEL => 'Public Page',
                BackendUtils::PSN_USE_NC_THEME => true,
            ];
        }
        if ($label !== null) {
            $data[BackendUtils::PAGE_LABEL] = $label;
        }

        $pageId = $this->nextPageId($userId);
        $count = $this->utils->createPage($userId, $pageId, json_encode($data), $token);
        if ($count !== 1) {
            throw new \RuntimeException('createPage returned ' . $count . ', expected 1');
        }
        if ($reminders !== null) {
            $this->utils->dbUpsert2($userId, $pageId, [BackendUtils::KEY_REMINDERS => $reminders]);
        }
        return [$pageId, $this->utils->getToken($userId, $pageId)];
    }

    /**
     * Reminders in the shape the settings UI and state API use: the
     * appointment reminders only, without the internal cancellation entries
     * (those follow cancelPendingHours/notifyCancelPending).
     */
    private function publicReminders(array $reminders): array
    {
        $out = $reminders;
        $out[BackendUtils::REMINDER_DATA] = [];
        foreach ($reminders[BackendUtils::REMINDER_DATA] ?? [] as $index => $item) {
            if ($index <= 2 && ($item[BackendUtils::REMINDER_DATA_TYPE] ?? BackendUtils::REMINDER_TYPE_APPT) === BackendUtils::REMINDER_TYPE_APPT) {
                $out[BackendUtils::REMINDER_DATA][] = $item;
            }
        }
        return $out;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function exportPage(string $userId, string $pageId): array
    {
        if ($this->utils->isDir($pageId) || !$this->utils->loadSettingsForUserAndPage($userId, $pageId)) {
            throw new \InvalidArgumentException("page " . $pageId . " not found for user " . $userId);
        }
        $current = $this->utils->getUserSettings();

        $settings = [];
        foreach ($this->getExportableDefaults() as $k => $default) {
            if (array_key_exists($k, $current) && $current[$k] !== $default) {
                $settings[$k] = $current[$k];
            }
        }

        $page = [
            'label' => $current[BackendUtils::PAGE_LABEL],
            'token' => $current[BackendUtils::KEY_TOKEN],
            'settings' => (object)$settings,
        ];
        $defRem = $this->publicReminders($this->utils->getDefaultSettingsData()[BackendUtils::KEY_REMINDERS]);
        $rem = $this->publicReminders($current[BackendUtils::KEY_REMINDERS]);
        if ($rem != $defRem) {
            $page['reminders'] = $rem;
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'app_version' => $this->appManager->getAppVersion(Application::APP_ID),
            'page' => $page,
        ];
    }

    /**
     * Checks the envelope of an exported document.
     * @throws \InvalidArgumentException
     */
    public static function checkDocument($doc): array
    {
        if (!is_array($doc) || ($doc['format'] ?? null) !== self::FORMAT) {
            throw new \InvalidArgumentException('not an ' . self::FORMAT . ' document');
        }
        if (($doc['version'] ?? null) !== self::VERSION) {
            throw new \InvalidArgumentException('unsupported version, expected ' . self::VERSION);
        }
        $page = $doc['page'] ?? null;
        if (!is_array($page)
            || (isset($page['settings']) && !is_array($page['settings']))
            || (isset($page['reminders']) && !is_array($page['reminders']))
            || (isset($page['label']) && !is_string($page['label']))
            || (isset($page['token']) && !is_string($page['token']))) {
            throw new \InvalidArgumentException('malformed "page" object');
        }
        return $page;
    }

    /**
     * Applies an exported document to an existing page. The page ends up with
     * the settings from the document; exportable keys missing from the
     * document are reset to their defaults. Secrets on the page are kept.
     *
     * @return array{changes: array, warnings: string[], errors: string[], token: ?string}
     *   changes: [key => ['from' => ..., 'to' => ...]] (applied, or would be with $dryRun)
     * @throws \InvalidArgumentException on a bad document, page or token
     */
    public function importPage(string $userId, string $pageId, array $doc, bool $dryRun = false, bool $keepToken = false): array
    {
        $page = self::checkDocument($doc);
        if ($this->utils->isDir($pageId) || !$this->utils->loadSettingsForUserAndPage($userId, $pageId)) {
            throw new \InvalidArgumentException("page " . $pageId . " not found for user " . $userId);
        }

        $report = ['changes' => [], 'warnings' => [], 'errors' => [], 'token' => null];

        if ($keepToken) {
            $token = $page['token'] ?? '';
            $currentToken = $this->utils->getUserSettings()[BackendUtils::KEY_TOKEN];
            if ($token !== $currentToken) {
                if (!BackendUtils::isValidPageToken($token)) {
                    throw new \InvalidArgumentException('the document has no valid token');
                }
                if ($this->utils->isPageTokenUsed($token)) {
                    throw new \InvalidArgumentException('token is already used by another page');
                }
                if (!$dryRun) {
                    $this->utils->setPageToken($userId, $pageId, $token);
                }
                $report['changes']['token'] = ['from' => $currentToken, 'to' => $token];
            }
            $report['token'] = $token;
        }

        $defaults = $this->getExportableDefaults();
        $fileSettings = $page['settings'] ?? [];

        // target values, every exportable key
        $target = [];
        foreach ($fileSettings as $k => $v) {
            if (!array_key_exists($k, $defaults)) {
                $report['warnings'][] = 'skipped unknown or excluded key "' . $k . '"';
                continue;
            }
            if (gettype($v) !== gettype($defaults[$k])) {
                $report['errors'][] = 'rejected "' . $k . '": expected ' . gettype($defaults[$k]) . ', got ' . gettype($v);
                continue;
            }
            $target[$k] = $v;
        }
        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $target) && !array_key_exists($k, $fileSettings)) {
                $target[$k] = $v;
            }
        }
        if (isset($page['label'])) {
            $target[BackendUtils::PAGE_LABEL] = $page['label'];
        }

        // "enabled" goes last, its check needs the rest of the settings
        if (array_key_exists(BackendUtils::PAGE_ENABLED, $target)) {
            $enabled = $target[BackendUtils::PAGE_ENABLED];
            unset($target[BackendUtils::PAGE_ENABLED]);
            $target[BackendUtils::PAGE_ENABLED] = $enabled;
        }

        $remTarget = $this->publicReminders(isset($page['reminders'])
            ? array_replace($this->utils->getDefaultSettingsData()[BackendUtils::KEY_REMINDERS], $page['reminders'])
            : $this->utils->getDefaultSettingsData()[BackendUtils::KEY_REMINDERS]);

        foreach ($target as $k => $v) {
            $current = $this->utils->getUserSettings();
            if (array_key_exists($k, $current) && self::sameValue($k, $current[$k], $v)) {
                continue;
            }
            if (!$dryRun) {
                [$status, $data] = $this->validator->apply($userId, $pageId, $k, $v);
                // validators may write other keys and reminders, start over from the stored state
                $this->utils->loadSettingsForUserAndPage($userId, $pageId);
                if ($status !== Http::STATUS_OK) {
                    $report['errors'][] = 'rejected "' . $k . '"' . self::statusMessage($data);
                    continue;
                }
            }
            $report['changes'][$k] = ['from' => $current[$k] ?? null, 'to' => $v];
        }

        $curRem = $this->publicReminders($this->utils->getUserSettings()[BackendUtils::KEY_REMINDERS]);
        if ($curRem != $remTarget) {
            if (!$dryRun) {
                [$status, $data] = $this->validator->apply($userId, $pageId, BackendUtils::KEY_REMINDERS, $remTarget);
                $this->utils->loadSettingsForUserAndPage($userId, $pageId);
                if ($status !== Http::STATUS_OK) {
                    $report['errors'][] = 'rejected "' . BackendUtils::KEY_REMINDERS . '"' . self::statusMessage($data);
                } else {
                    $report['changes'][BackendUtils::KEY_REMINDERS] = ['from' => $curRem, 'to' => $remTarget];
                }
            } else {
                $report['changes'][BackendUtils::KEY_REMINDERS] = ['from' => $curRem, 'to' => $remTarget];
            }
        }

        return $report;
    }

    /**
     * Form field names are hashes rebuilt on every save, they do not count as a change.
     */
    private static function sameValue(string $key, $a, $b): bool
    {
        if ($key === BackendUtils::KEY_FORM_INPUTS_JSON && is_array($a) && is_array($b)) {
            $strip = function (array $fields): array {
                return array_map(function ($f) {
                    if (is_array($f)) {
                        unset($f['name']);
                    }
                    return $f;
                }, $fields);
            };
            return $strip($a) === $strip($b);
        }
        return $a === $b;
    }

    private static function statusMessage(string $data): string
    {
        if ($data === '') {
            return '';
        }
        $j = json_decode($data, true);
        $msg = is_array($j) ? ($j['message'] ?? $j['error'] ?? '') : $data;
        return $msg !== '' ? ': ' . $msg : '';
    }
}
