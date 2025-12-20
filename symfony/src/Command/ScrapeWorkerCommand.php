<?php

namespace App\Command;

use App\Message\GetPostDetailBatchMessage;
use App\Messenger\CommandBus;
use App\Service\PostScraperService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'scrape:worker',
    description: 'Worker process of scraping',
)]
class ScrapeWorkerCommand extends Command
{
    public function __construct(
        private PostScraperService $postScraper,
        private CommandBus $bus,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        // TODO: добавить валидацию
        $this->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start label');
        $this->addOption('to', null, InputOption::VALUE_OPTIONAL, 'Finish label');
        $this->addOption('proxy', null, InputOption::VALUE_OPTIONAL, 'Proxy');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('From page  ' . $input->getOption('from') . ' to ' . $input->getOption('to'));

        for ($i = $input->getOption('from'); $i < $input->getOption('to'); $i++) {
            $itemIdsToBatch = array_column($this->postScraper->getPostsListFromPage($i), 'id');

            $chunks = array_chunk($itemIdsToBatch, 10);
            foreach ($chunks as $chunk) {
                if (!empty($chunk)) {
                    $this->bus->dispatch(new GetPostDetailBatchMessage($chunk));
                }
            }
        }
        $output->writeln('Done! You have initialized crawl the collection of all posts');

        return Command::SUCCESS;
    }
}
