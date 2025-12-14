<?php

namespace App\Command;

use App\Service\PostsUpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'post:update-list',
    description: 'Update list with new posts',
)]
class UpdatePostsCommand extends Command
{
    public function __construct(private PostsUpdateService $postsUpdateService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $newPostsCount = $this->postsUpdateService->update();
        $output->writeln("<info>Updating is done. Added $newPostsCount posts</info>");

        return Command::SUCCESS;
    }
}
