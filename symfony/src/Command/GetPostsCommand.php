<?php

namespace App\Command;

use App\Factory\ScrapeServicesFactory;
use App\Service\PostScraperService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'post:get-list',
    description: 'Get all posts from API (without \'body\')',
)]
class GetPostsCommand extends Command
{
    public function __construct(
        private ScrapeServicesFactory $scrapeFactory,
        private PostScraperService $apiHandler
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        //TODO: сделать валидацию threads на числа и сделать ограничение на максимальное количество процессов
        $this->addOption('threads', null, InputOption::VALUE_OPTIONAL, 'Threads count for async crawling', '15');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $roundedMaxPage = $this->apiHandler->getRoundedMaxPage();
        $threads = (int)$input->getOption('threads');

        $output->writeln("<info>Total pages: $roundedMaxPage</info>");
        $this->scrapeFactory->makeMasterService($threads, $roundedMaxPage, $output)->handle();

        return Command::SUCCESS;
    }
}
