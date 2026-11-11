<?php

namespace OCA\Appointments\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PageToken extends Base
{
    protected function configure(): void
    {
        $this->setName('appointments:page:token')
            ->setDescription('Show or set the public token of a page')
            ->setHelp('The public form is served at /apps/appointments/pub/<token>/form. '
                . 'Setting a new token makes the old URL stop working.')
            ->addArgument('user', InputArgument::REQUIRED, 'user id')
            ->addArgument('pageId', InputArgument::REQUIRED, 'page id, e.g. p0')
            ->addArgument('token', InputArgument::OPTIONAL, 'new token, 16 characters A-Z, a-z, 0-9, unused');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('user');
        $pageId = $input->getArgument('pageId');
        if (!$this->checkUser($userId, $output) || !$this->checkPage($userId, $pageId, $output)) {
            return 1;
        }
        $token = $input->getArgument('token');
        if ($token !== null) {
            try {
                $this->utils->setPageToken($userId, $pageId, $token);
            } catch (\InvalidArgumentException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return 1;
            }
        }
        $output->writeln($this->utils->getToken($userId, $pageId));
        return 0;
    }
}
