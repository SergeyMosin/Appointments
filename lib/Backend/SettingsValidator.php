<?php

namespace OCA\Appointments\Backend;

use OCA\Appointments\AppInfo\Application;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Validates and stores single page settings. Shared by the state API
 * (StateController) and the page import (PageIO).
 */
class SettingsValidator
{
    private string $userId = '';
    private IBackendConnector $bc;

    public function __construct(private IConfig         $config,
                                private IL10N           $l,
                                private BackendUtils    $utils,
                                BackendManager          $backendManager,
                                private LoggerInterface $logger)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->bc = $backendManager->getConnector();
    }

    /**
     * Settings for $userId/$pageId must be loaded in BackendUtils.
     * @return array{int, string} [http status, data]; anything but 200 means the value was not stored
     */
    public function apply(string $userId, string $pageId, string $key, $value): array
    {
        $this->userId = $userId;
        $settings = $this->utils->getUserSettings();

        if (empty($key) || !isset($settings[$key])
            || gettype($settings[$key]) !== gettype($value)) {
            $this->logger->warning("setUserSettingsV2: invalid key or value - " . $key);
            return [Http::STATUS_BAD_REQUEST, ''];
        }

        if (is_string($value)) {
            $value = self::regexRemoveScriptTag($value);
        }

        $validator = $this->getValidator($key);
        if ($validator !== null) {
            /** @var array{ int, string }|null $validationResult [code,data] */
            $validationResult = $validator($value, $pageId, $key);
            if ($validationResult[0] !== Http::STATUS_OK) {
                return $validationResult;
            }
        }

        return $this->utils->setUserSettingsV2($this->userId, $pageId, $key, $value);
    }

    public static function regexRemoveScriptTag(string $text): string
    {
        return str_replace('<?', '&lt;?', preg_replace('#<script(.*?)>(.*?)</script>#is', '', $text));
    }

    private function getValidator(string $prop): ?\Closure
    {
        return match ($prop) {

            BackendUtils::PAGE_ENABLED => function ($value, $pageId, $key): array {
                if ($value === false) {
                    return [Http::STATUS_OK, ''];
                }
                $settings = $this->utils->getUserSettings();
                // check BackendUtils::ORG_EMAIL
                $orgEmail = $settings[BackendUtils::ORG_EMAIL];
                if (empty($orgEmail) || !filter_var($orgEmail, FILTER_VALIDATE_EMAIL)) {
                    // try to set BackendUtils::ORG_EMAIL to user's email
                    /** @var IUserManager $userManager */
                    $userManager = \OC::$server->get(IUserManager::class);
                    $orgEmail = $userManager->get($this->userId)->getEMailAddress();
                    if (empty($orgEmail) || !filter_var($orgEmail, FILTER_VALIDATE_EMAIL)) {
                        return [Http::STATUS_ACCEPTED, json_encode([
                            "type" => 3,
                            "message" => $this->l->t("A valid email address is required.")
                        ])];
                    }
                    $res = $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::ORG_EMAIL, $orgEmail);
                    if ($res[0] !== Http::STATUS_OK) {
                        $res[1] = 'email configuration error';
                        return $res;
                    }
                }
                // BackendUtils::ORG_NAME
                if (empty($settings[BackendUtils::ORG_NAME])) {
                    /** @var IUserManager $userManager */
                    $userManager = \OC::$server->get(IUserManager::class);
                    $displayName = $userManager->getDisplayName($this->userId);
                    if (empty($displayName)) {
                        $displayName = substr($orgEmail, 0, strpos($orgEmail, '@'));
                    }
                    if (empty($displayName) || $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::ORG_NAME, $displayName)[0] !== Http::STATUS_OK) {
                        return [Http::STATUS_ACCEPTED, json_encode([
                            "type" => 3,
                            "message" => $this->l->t("Can not determine or set organizer name")
                        ])];
                    }
                }

                // check if we at least have the main calendar
                if ($this->utils->getMainCalId($this->userId, $this->bc) === '-1') {
                    return [Http::STATUS_ACCEPTED, json_encode([
                        "type" => 3,
                        "message" => $this->l->t("Main calendar not found, check settings.")
                    ])];
                };
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::CLS_TMM_DST_ID => function ($calId, $pageId, $key): array {
                $settings = $this->utils->getUserSettings();
                if ($settings[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_TEMPLATE) {
                    // if CLS_TMM_DST_ID is in CLS_TMM_MORE_CALS we need to remove it
                    $conflictCalsIds = $settings[BackendUtils::CLS_TMM_MORE_CALS];
                    if (in_array($calId, $conflictCalsIds)) {
                        return $this->utils->setUserSettingsV2($this->userId, $pageId,
                            BackendUtils::CLS_TMM_MORE_CALS,
                            array_values(array_diff($conflictCalsIds, [$calId]))
                        );
                    }
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::CLS_TMM_MORE_CALS, BackendUtils::CLS_TMM_SUBSCRIPTIONS => function ($value, $pageId, $key): array {
                $d = $this->config->getUserValue($this->userId, Application::APP_ID, "cnk");
                if ($d === "" || ((hexdec(substr($d, 0, 4)) >> 15) & 1) !== ((hexdec(substr($d, 4, 4)) >> 12) & 1)) {

                    $calCount = count($value);
                    $settings = $this->utils->getUserSettings();
                    if ($key === BackendUtils::CLS_TMM_MORE_CALS) {
                        $calCount += count($settings[BackendUtils::CLS_TMM_SUBSCRIPTIONS]);
                    } else {
                        $calCount += count($settings[BackendUtils::CLS_TMM_MORE_CALS]);
                    }
                    if ($calCount > 2) {
                        return [Http::STATUS_ACCEPTED, json_encode([
                            "type" => 2,
                            "message" => $this->l->t("More than 2 additional calendars.")
                        ])];
                    }
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::CLS_BUFFER_BEFORE, BackendUtils::CLS_BUFFER_AFTER => function ($value, $pageId, $key): array {
                if ($key === BackendUtils::CLS_BUFFER_BEFORE) {
                    $buf2 = BackendUtils::CLS_BUFFER_AFTER;
                } else {
                    $buf2 = BackendUtils::CLS_BUFFER_BEFORE;
                }
                $settings = $this->utils->getUserSettings();
                if ($settings[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE && $value !== 0) {
                    return [Http::STATUS_BAD_REQUEST, $this->makeErrorJson('in simple mode buffers must be 0')];
                }
                if ($value < 0 || $value > 120) {
                    return [Http::STATUS_BAD_REQUEST, ''];
                }
                // for now the "after" buffer must be the same as the "before" buffer because there is really no good way to deal with buffer overlap when bufferBefore != afterBuffer
                return $this->utils->setUserSettingsV2($this->userId, $pageId, $buf2, $value);
            },

            BackendUtils::CLS_DEST_ID => function (&$value, $pageId, $key): array {
                $settings = $this->utils->getUserSettings();
                if ($settings[BackendUtils::CLS_MAIN_ID] === $value) {
                    // '-1' = use mail calendar
                    $value = '-1';
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::KEY_TMPL_DATA => function (&$value, $page, $key): array {
                if (!is_array($value) || count($value) !== 7) {
                    return [Http::STATUS_BAD_REQUEST, ''];
                }

                $maxDurCount = empty($this->config->getUserValue($this->userId, Application::APP_ID, "cnk")) ? 2 : 16;

                $settings = $this->utils->getUserSettings();

                if (isset($settings[BackendUtils::KEY_TMPL_ALLOW_OVERLAP])) {
                    // new structure

                    for ($i = 0; $i < 7; ++$i) {
                        $day = &$value[$i];
                        if (!is_array($day)) {
                            return [Http::STATUS_BAD_REQUEST, ''];
                        }
                        $spotsCount = count($day);
                        for ($j = 0; $j < $spotsCount; ++$j) {
                            $spot = $day[$j];
                            if (!is_array($spot)
                                || !array_key_exists('start', $spot)
                                || !array_key_exists('end', $spot)
                                || !array_key_exists('dur', $spot)
                                || !array_key_exists('title', $spot)
                                || !array_key_exists('tkn', $spot)
                            ) {
                                return [Http::STATUS_BAD_REQUEST, ''];
                            }
                        }

                        if ($spotsCount > 0) {
                            usort($day, function ($a, $b) {
                                return $a['start'] <=> $b['start'] ?: ($a['end'] <=> $b['end']);
                            });
                        }
                    }
                } else {
                    // old structure
                    for ($i = 0; $i < 7; ++$i) {
                        $day = &$value[$i];
                        if (!is_array($day)) {
                            return [Http::STATUS_BAD_REQUEST, ''];
                        }
                        $spotsCount = count($day);
                        for ($j = 0; $j < $spotsCount; ++$j) {
                            $spot = $day[$j];
                            if (!is_array($spot)
                                || !array_key_exists('start', $spot)
                                || !array_key_exists('dur', $spot)
                                || !array_key_exists('title', $spot)) {
                                return [Http::STATUS_BAD_REQUEST, ''];
                            }
                            if (count($spot['dur']) > $maxDurCount) {
                                array_splice($value[$i][$j]['dur'], $maxDurCount);
                            }
                        }

                        if (!empty($day)) {
                            usort($day, function ($a, $b) {
                                return $a['start'] <=> $b['start'];
                            });
                        }

                    }
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::KEY_REMINDERS => function (&$value, $pageId, $key): array {

                $valid = $this->utils->getDefaultSettingsData()[BackendUtils::KEY_REMINDERS];
                foreach ($valid as $k => $v) {
                    if (!isset($value[$k]) || gettype($value[$k]) !== gettype($v)) {
                        $this->logger->error('bad/missing "' . $k . '" key in reminders data');
                        return [Http::STATUS_BAD_REQUEST, ''];
                    }
                }

                $validDataCount = 0;
                foreach ($valid[BackendUtils::REMINDER_DATA] as $v) {
                    $remType = $v[BackendUtils::REMINDER_DATA_TYPE];
                    if ($remType === BackendUtils::REMINDER_TYPE_APPT) {
                        $validDataCount++;
                    }
                }

                if (count($valid) !== count($value)
                    || $validDataCount !== count($value[BackendUtils::REMINDER_DATA])
                ) {
                    $this->logger->error('bad count reminders data');
                    return [Http::STATUS_BAD_REQUEST, ''];
                }

                $allowed_values = ["0", "3600", "7200", "14400", "28800", "86400"];
                $k = $this->config->getUserValue($this->userId, Application::APP_ID, "cn" . "k");
                if ($k !== "" && ((hexdec(substr($k, 0, 4)) >> 15) & 1) === ((hexdec(substr($k, 4, 4)) >> 12) & 1)) {
                    array_push($allowed_values, "172800", "259200", "345600", "432000", "518400", "604800");
                }

                $reminders = $this->utils->getUserSettings()[BackendUtils::KEY_REMINDERS];
                foreach ($value[BackendUtils::REMINDER_DATA] as $index => &$item) {
                    if (!in_array($item[BackendUtils::REMINDER_DATA_TIME], $allowed_values, true)
                        || ($k === "" && $index > 0)
                    ) {
                        $item[BackendUtils::REMINDER_DATA_TIME] = "0";
                    }
                    $reminders[BackendUtils::REMINDER_DATA][$index] = $item;
                }
                if (is_string($value[BackendUtils::REMINDER_MORE_TEXT])) {
                    $reminders[BackendUtils::REMINDER_MORE_TEXT] = self::regexRemoveScriptTag($value[BackendUtils::REMINDER_MORE_TEXT]);
                }

                if (is_bool($value[BackendUtils::REMINDER_SEND_ON_FRIDAY])) {
                    $reminders[BackendUtils::REMINDER_SEND_ON_FRIDAY] = $value[BackendUtils::REMINDER_SEND_ON_FRIDAY];
                }

                // because we have internal items in the BackendUtils::REMINDER_DATA array
                $value = $reminders;
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::KEY_FORM_INPUTS_JSON => function (&$value, $pageId, $key): array {
                $inputsHtml = $this->makeFormComponent($value);
                return $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::KEY_FORM_INPUTS_HTML, $inputsHtml);
            },

            BackendUtils::TALK_ENABLED => function (&$value, $pageId, $key) {
                if ($value === false) {
                    // nothing to do
                    return [Http::STATUS_OK, ''];
                }
                if (!TalkIntegration::canTalk()) {
                    $value = false;
                    return [Http::STATUS_OK, ''];
                }
                // ensure that BBB is unset
                return $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::BBB_ENABLED, false);
            },

            BackendUtils::BBB_ENABLED => function (&$value, $pageId, $key) {
                if ($value === false) {
                    // nothing to do
                    return [Http::STATUS_OK, ''];
                }
                if (!\OC::$server->get(BbbIntegration::class)->canBBB()) {
                    $value = false;
                    return [Http::STATUS_OK, ''];
                }
                // ensure that TALK is unset
                return $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::TALK_ENABLED, false);
            },

            BackendUtils::SEC_EMAIL_BLACKLIST => function ($value, $pageId, $key) {
                if (!is_array($value)) {
                    return [Http::STATUS_BAD_REQUEST, ''];
                }
                foreach ($value as $item) {
                    if (strlen($item) < 4) {
                        return [Http::STATUS_BAD_REQUEST, ''];
                    }
                    if (str_starts_with($item, '*@')) {
                        // should be a valid domain after the '*@'
                        if (filter_var(substr($item, 2), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                            return [Http::STATUS_BAD_REQUEST, ''];
                        }
                    } else {
                        // this should be a valid email
                        if (filter_var($item, FILTER_VALIDATE_EMAIL) === false) {
                            return [Http::STATUS_BAD_REQUEST, ''];
                        }
                    }
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::PSN_FORM_FINISH_TEXT => function (&$value, $pageId, $key) {
                if (!empty($value)) {
                    $value = strip_tags($value, '<div><p><span><br>');
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::EML_CANCEL_PENDING_HOURS, BackendUtils::EML_NOTIFY_BEFORE_CANCEL_PENDING => function ($value, $pageId, $key) {

                $settings = $this->utils->getUserSettings();

                if ($key === BackendUtils::EML_CANCEL_PENDING_HOURS) {
                    if (!is_numeric($value) || !in_array($value, array(0, 2, 4, 8, 24, 48, 96))) {
                        return [Http::STATUS_BAD_REQUEST, ''];
                    }
                    $cancelHours = $value;
                    $notifyBeforeCancel = $settings[BackendUtils::EML_NOTIFY_BEFORE_CANCEL_PENDING];

                } else {
                    if (!is_bool(!$value)) {
                        return [Http::STATUS_BAD_REQUEST, ''];
                    }
                    if ($value === true) {
                        $d = $this->config->getUserValue($this->userId, Application::APP_ID, 'c' . "n" . "k");
                        if (empty($d) || ((hexdec(substr($d, 0, 4)) >> 15) & 1) !== ((hexdec(substr($d, 4, 4)) >> 12) & 1)) {

                            return [Http::STATUS_ACCEPTED, json_encode([
                                "type" => 2,
                                // TRANSLATORS This is an item in popup dialog
                                "message" => $this->l->t("Unconfirmed appointments cancellation reminders")
                            ])];
                        }
                    }

                    $cancelHours = $settings[BackendUtils::EML_CANCEL_PENDING_HOURS];
                    $notifyBeforeCancel = $value;
                }

                // internal representation

                $valid = $this->utils->getDefaultSettingsData()[BackendUtils::KEY_REMINDERS][BackendUtils::REMINDER_DATA];

                $reminders = $settings[BackendUtils::KEY_REMINDERS];

                $cancelReminder = $valid[3];
                $cancelReminder[BackendUtils::REMINDER_DATA_TIME] = (string)($cancelHours * 3600);
                $reminders[BackendUtils::REMINDER_DATA][3] = $cancelReminder;

                $notifyHours = $notifyBeforeCancel === true && $cancelHours > 0
                    ? ($cancelHours >= 4 ? 2 : 1)
                    : 0;

                $cancelNotifyReminder = $valid[4];
                $cancelNotifyReminder[BackendUtils::REMINDER_DATA_TIME] = (string)($notifyHours * 3600);
                $reminders[BackendUtils::REMINDER_DATA][4] = $cancelNotifyReminder;

                return $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::KEY_REMINDERS, $reminders);
            },

            default => null,
        };
    }

    private function makeErrorJson(string $error): string
    {
        return '{"error":"' . $error . '"}';
    }

    private function makeFormComponent(&$obj, $index = 0)
    {
        $r = '';
        $fields = [];
        if (is_array($obj) && array_key_exists(0, $obj) && is_array($obj[0])) {
            foreach ($obj as $ind => $field) {
                $r = $this->makeFormField($field, $ind);
                if ($r === '') {
                    return $r;
                }
                $fields[] = $r;
                $obj[$ind]['name'] = $field['name'];
                $r = '';
            }
            return implode('', $fields);
        } else {
            return $this->makeFormField($obj, $index);
        }
    }

    private function makeFormField(&$obj, $index = 0)
    {
        $r = '';
        if (!isset($obj['tag']) || !isset($obj['label'][2])) {
            return $r;
        }
        $obj['label'] = htmlspecialchars(trim($obj['label']), ENT_QUOTES, 'UTF-8');
        $tail = '';
        $class = '';
        $id = 'srgdev-ncfp_' . hash('adler32', $index . $obj['tag'] . $obj['label']);
        $name = 'n' . hash('adler32', json_encode($obj) . $id . $index);
        $obj['name'] = $name;
        $dmo = (isset($obj['required']) && $obj['required']) === true ? 'r1' : 'r0';
        $allowedAttrsByTag = [
            'input' => [
                'text' => [
                    'placeholder' => false,
                    'maxlength' => '512',
                    'required' => false
                ],
                'number' => [
                    'min' => false,
                    'max' => false,
                    'step' => false,
                    'placeholder' => false,
                    'required' => false
                ],
                'email' => [
                    'placeholder' => false,
                    'maxlength' => '512',
                    'required' => false
                ],
                'url' => [
                    'placeholder' => false,
                    'maxlength' => '512',
                    'required' => false
                ],
                'checkbox' => [
                    'required' => false
                ],
                'radio' => [
                    'required' => false
                ],
            ],
            'textarea' => [
                'placeholder' => false,
                'maxlength' => '512',
                'required' => false
            ],
            'select' => [
                'required' => false
            ]
        ];
        $applyAttrs = function (array $allowed) use ($obj) {
            $out = '';
            foreach ($allowed as $attr => $default) {
                if ($attr === 'required' && !empty($obj[$attr])) {
                    $out .= ' required';
                } elseif (isset($obj[$attr])) {
                    $out .= ' ' . $attr . '="' . htmlspecialchars($obj[$attr], ENT_QUOTES, 'UTF-8') . '"';
                } elseif ($default !== false) {
                    $out .= ' ' . $attr . '="' . htmlspecialchars($default, ENT_QUOTES, 'UTF-8') . '"';
                }
            }
            return $out;
        };

        switch ($obj['tag']) {
            case 'input':
                if (!isset($obj['type']) || !array_key_exists($obj['type'], $allowedAttrsByTag['input'])) {
                    $obj['type'] = 'text';
                }
                if (($obj['type'] === 'checkbox' || $obj['type'] === 'radio') && isset($obj['options']) && is_array($obj['options'])) {
                    $class = 'srgdev-ncfp-form-' . $obj['type'];
                    $nameSuffix = $obj['type'] === 'checkbox' ? '[]' : '';
                    $r .= '<div class="srgdev-ncfp-form-fieldset">';
                    $r .= '<legend class="srgdev-ncfp-form-label">' . $obj['label'] . '</legend>';
                    foreach ($obj['options'] as $optIndex => $optValue) {
                        $optValEscaped = htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8');
                        $optId = $id . '_' . $optIndex;
                        $r .= '<p>';
                        $r .= '<input data-more="' . $dmo . '" type="' . $obj['type'] . '" id="' . $optId . '" name="' . $name . $nameSuffix . '" class="' . $obj['type'] . ' ' . $class . '" value="' . $optValEscaped . '">';
                        $r .= '<label for="' . $optId . '">' . $optValEscaped . '</label>';
                        $r .= '</p>';
                    }
                    $r .= '</div>';
                    return $r;
                }
                $class = 'srgdev-ncfp-form-input';
                $tail = $applyAttrs($allowedAttrsByTag['input'][$obj['type']] ?? []);
                $tail .= ' type="' . $obj['type'] . '"/>';
                break;
            case 'textarea':
                $class = 'srgdev-ncfp-form-textarea';
                $tail = $applyAttrs($allowedAttrsByTag['textarea']);
                $tail .= '></textarea>';
                break;
            case 'select':
                if (!isset($obj['options']) || !is_array($obj['options']) || count($obj['options']) === 0) {
                    return $r;
                }
                $tail = $applyAttrs($allowedAttrsByTag[$obj['tag']] ?? []);
                $tail .= '>';
                foreach ($obj['options'] as $option) {
                    if (is_array($option) && array_first($option) === 'optgroup') {
                        $o = htmlspecialchars($option[1], ENT_QUOTES, 'UTF-8');
                        $tail .= '<optgroup label="' . $o . '">';
                    } elseif (is_array($option) && array_first($option) === '/optgroup') {
                        $tail .= '</optgroup>';
                    } elseif (isset($option[1])) {
                        $o = htmlspecialchars($option, ENT_QUOTES, 'UTF-8');
                        $tail .= '<option class="srgdev-ncfp-form-option" value="' . $o . '">' . $o . '</option>';
                    }
                }
                if (!isset($tail[1])) {
                    return $r;
                }
                $tail .= '</select>';
                $class = 'srgdev-ncfp-form-input srgdev-ncfp-form-select';
                break;
            default:
                return $r;
        }
        return '<label for="' . $id . '" class="srgdev-ncfp-form-label">' . $obj['label'] . '</label><' . $obj['tag'] . ' data-more="' . $dmo . '" id="' . $id . '" name="' . $name . '" class="' . $class . '"' . $tail;
    }
}
