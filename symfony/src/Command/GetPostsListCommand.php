<?php

namespace App\Command;

use App\Factory\ScrapeServicesFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'post:get-list',
    description: 'Get all posts from API (without \'body\')',
)]
class GetPostsListCommand extends Command
{
    const string GET_POSTS_LIST_API_URL = 'https://proof.moneymediagroup.co.uk/api/posts';

    public function __construct(
        private ScrapeServicesFactory $scrapeFactory
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        //TODO: сделать валидацию threads на числа
        $this->addOption('threads', null, InputOption::VALUE_OPTIONAL, 'Threads count for async crawling', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $roundedMaxPage = 15000;//$this->apiHandler->getRoundedMaxPage();
        $threads = (int)$input->getOption('threads');

        $this->scrapeFactory->makeMasterService($threads, $roundedMaxPage, $output)->handle();

        return Command::SUCCESS;
    }
}
