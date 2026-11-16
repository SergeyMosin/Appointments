<?php

namespace OCA\Appointments\Command;

use OCA\Appointments\Backend\PageIO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Export extends Base
{
    protected function configure(): void
    {
        $this->setName('appointments:export')
            ->setDescription('Export page settings as JSON')
            ->setHelp('Writes one <pageId>.json per page into --output, or prints the JSON of a single page '
                . 'to stdout. The file holds the label, the token, all settings that differ from the defaults '
                . 'and the reminders. Never exported: ' . PageIO::excludedKeysHelp())
            ->addArgument('user', InputArgument::REQUIRED, 'user id')
            ->addArgument('pageId', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'page ids, e.g. p0 p1')
            ->addOption('all', null, InputOption::VALUE_NONE, 'export all pages of the user')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'directory to write <pageId>.json files into');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('user');
        if (!$this->checkUser($userId, $output)) {
            return 1;
        }
        $pageIds = $input->getArgument('pageId');
        if ($input->getOption('all')) {
            $pageIds = array_column($this->pageIO->listPages($userId), 'id');
        }
        if (empty($pageIds)) {
            $output->writeln('<error>give one or more page ids or --all</error>');
            return 1;
        }
        $dir = $input->getOption('output');
        if ($dir === null && count($pageIds) > 1) {
            $output->writeln('<error>--output is required for more than one page</error>');
            return 1;
        }
        if ($dir !== null && !is_dir($dir) && !mkdir($dir, 0700, true)) {
            $output->writeln('<error>can not create ' . $dir . '</error>');
            return 1;
        }

        foreach ($pageIds as $pageId) {
            try {
                $doc = $this->pageIO->exportPage($userId, $pageId);
            } catch (\InvalidArgumentException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return 1;
            }
            $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($dir === null) {
                $output->writeln($json, OutputInterface::OUTPUT_RAW);
            } else {
                $file = rtrim($dir, '/') . '/' . $pageId . '.json';
                if (file_put_contents($file, $json . "\n") === false) {
                    $output->writeln('<error>can not write ' . $file . '</error>');
                    return 1;
                }
                $output->writeln($file);
            }
        }
        return 0;
    }
}
