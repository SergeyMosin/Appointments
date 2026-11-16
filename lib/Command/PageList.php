<?php

namespace OCA\Appointments\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PageList extends Base
{
    protected function configure(): void
    {
        $this->setName('appointments:page:list')
            ->setDescription('List the booking pages of a user')
            ->addArgument('user', InputArgument::REQUIRED, 'user id')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'output format: plain or json', 'plain');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('user');
        if (!$this->checkUser($userId, $output)) {
            return 1;
        }
        $pages = $this->pageIO->listPages($userId);
        $format = $input->getOption('output');
        if ($format === 'json' || $format === 'json_pretty') {
            $output->writeln(json_encode($pages, $format === 'json_pretty' ? JSON_PRETTY_PRINT : 0));
            return 0;
        }
        $table = new Table($output);
        $table->setHeaders(['page', 'token', 'label', 'enabled']);
        foreach ($pages as $p) {
            $table->addRow([$p['id'], $p['token'], $p['label'], $p['enabled'] ? 'yes' : 'no']);
        }
        $table->render();
        return 0;
    }
}
