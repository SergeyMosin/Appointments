<?php

namespace OCA\Appointments\Command;

use OCA\Appointments\Backend\PageIO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends Base
{
    protected function configure(): void
    {
        $this->setName('appointments:import')
            ->setDescription('Apply exported page settings to a page')
            ->setHelp('Applies a file written by appointments:export to --page, or to a new page with --create. '
                . 'The page ends up with the settings from the file: settings missing from the file are reset '
                . 'to their defaults. Every value is checked like a change in the settings UI; unknown keys are '
                . 'skipped with a warning and values of the wrong type are rejected. Secrets are not in the file '
                . 'and are left as they are: ' . PageIO::excludedKeysHelp()
                . ' With --dry-run nothing is written; the per-key checks that need to write are not run then.')
            ->addArgument('user', InputArgument::REQUIRED, 'user id')
            ->addArgument('file', InputArgument::REQUIRED, 'JSON file, "-" for stdin')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'existing page id to apply the settings to')
            ->addOption('create', null, InputOption::VALUE_NONE, 'create a new page')
            ->addOption('keep-token', null, InputOption::VALUE_NONE, 'also set the token from the file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'only print what would change');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('user');
        if (!$this->checkUser($userId, $output)) {
            return 1;
        }
        $file = $input->getArgument('file');
        $text = $file === '-' ? stream_get_contents(STDIN) : @file_get_contents($file);
        if ($text === false) {
            $output->writeln('<error>can not read ' . $file . '</error>');
            return 1;
        }
        $doc = json_decode($text, true);
        try {
            $page = PageIO::checkDocument($doc);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }

        $pageId = $input->getOption('page');
        $create = $input->getOption('create');
        $keepToken = $input->getOption('keep-token');
        $dryRun = $input->getOption('dry-run');
        if (($pageId === null) === !$create) {
            $output->writeln('<error>give either --page or --create</error>');
            return 1;
        }

        try {
            if ($create) {
                $token = $keepToken ? ($page['token'] ?? '') : null;
                if ($dryRun) {
                    if ($token !== null && (!\OCA\Appointments\Backend\BackendUtils::isValidPageToken($token) || $this->utils->isPageTokenUsed($token))) {
                        throw new \InvalidArgumentException('the token from the file is invalid or already used');
                    }
                    $output->writeln('would create page ' . $this->pageIO->nextPageId($userId)
                        . ($token !== null ? ' with token ' . $token : '') . ' and apply ' . count($page['settings'] ?? []) . ' settings');
                    return 0;
                }
                [$pageId, $token] = $this->pageIO->createPage($userId, $token);
                $output->writeln('created ' . $pageId . ' ' . $token);
                // the token was set on create
                $keepToken = false;
            } elseif (!$this->checkPage($userId, $pageId, $output)) {
                return 1;
            }
            $report = $this->pageIO->importPage($userId, $pageId, $doc, $dryRun, $keepToken);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }

        foreach ($report['warnings'] as $w) {
            $output->writeln('<comment>warning: ' . $w . '</comment>');
        }
        foreach ($report['changes'] as $k => $c) {
            $output->writeln(($dryRun ? 'would set ' : 'set ') . $k . ': '
                . self::show($c['from']) . ' -> ' . self::show($c['to']));
        }
        foreach ($report['errors'] as $e) {
            $output->writeln('<error>' . $e . '</error>');
        }
        $output->writeln(($dryRun ? 'dry run, ' : '') . count($report['changes']) . ' changed, '
            . count($report['warnings']) . ' warnings, ' . count($report['errors']) . ' errors (page ' . $pageId . ')');
        return empty($report['errors']) ? 0 : 2;
    }

    private static function show($v): string
    {
        $s = json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return strlen($s) > 80 ? substr($s, 0, 77) . '...' : $s;
    }
}
