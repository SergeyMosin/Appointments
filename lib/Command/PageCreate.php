<?php

namespace OCA\Appointments\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PageCreate extends Base
{
    protected function configure(): void
    {
        $this->setName('appointments:page:create')
            ->setDescription('Create a booking page for a user and print "<pageId> <token>"')
            ->setHelp('Creates the next free page id (p0, p1, ...). The page starts disabled. '
                . 'The page count limit of the settings UI does not apply here.')
            ->addArgument('user', InputArgument::REQUIRED, 'user id')
            ->addOption('token', null, InputOption::VALUE_REQUIRED, 'public token, 16 characters A-Z, a-z, 0-9, unused (default: random)')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'page label')
            ->addOption('from-page', null, InputOption::VALUE_REQUIRED, 'copy all settings, reminders included, from this page of the same user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('user');
        if (!$this->checkUser($userId, $output)) {
            return 1;
        }
        try {
            [$pageId, $token] = $this->pageIO->createPage($userId,
                $input->getOption('token'),
                $input->getOption('label'),
                $input->getOption('from-page'));
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
        $output->writeln($pageId . ' ' . $token);
        return 0;
    }
}
