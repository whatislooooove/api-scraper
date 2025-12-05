<?php

namespace App\Command;

use App\Service\TargetApiService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'GetPostsListCommand',
    description: 'Get all posts from API (without \'body\')',
)]
class GetPostsListCommand extends Command
{
    public function __construct(private TargetApiService $apiHandler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $roundedMaxPage = $this->apiHandler->getRoundedMaxPage();
        // stub...
        return Command::SUCCESS;
    }
}
