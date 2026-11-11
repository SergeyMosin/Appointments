<?php

namespace OCA\Appointments\Command;

use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Backend\PageIO;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Base extends Command
{
    public function __construct(protected PageIO       $pageIO,
                                protected BackendUtils $utils,
                                protected IUserManager $userManager)
    {
        parent::__construct();
    }

    protected function checkUser(string $userId, OutputInterface $output): bool
    {
        if (!$this->userManager->userExists($userId)) {
            $output->writeln('<error>user "' . $userId . '" does not exist</error>');
            return false;
        }
        return true;
    }

    protected function checkPage(string $userId, string $pageId, OutputInterface $output): bool
    {
        foreach ($this->pageIO->listPages($userId) as $page) {
            if ($page['id'] === $pageId) {
                return true;
            }
        }
        $output->writeln('<error>page "' . $pageId . '" not found for user "' . $userId . '"</error>');
        return false;
    }
}
