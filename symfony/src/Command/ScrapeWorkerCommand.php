<?php

namespace App\Command;

use App\Message\GetPostDetailBatchMessage;
use App\Service\PostScraperService;
use App\Service\ScrapeWorkerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'scrape:worker',
    description: 'Worker process of scraping',
)]
class ScrapeWorkerCommand extends Command
{
    public function __construct(
        private PostScraperService $postScraper,
        private MessageBusInterface $bus
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        // TODO: добавить валидацию
        $this->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start label');
        $this->addOption('to', null, InputOption::VALUE_OPTIONAL, 'Finish label');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('From page  ' . $input->getOption('from') . ' to ' . $input->getOption('to'));

        for ($i = $input->getOption('from'); $i < $input->getOption('to'); $i++) {
            foreach ($this->postScraper->getPostsListFromPage($i) as $rawPost) {
                //TODO: $rawPost['id'] надо убрать отсюда и сделать DTO для конструктора message
                //TODO: сделать проверку, что уже есть message с такими id и proxy
                $this->bus->dispatch(new GetPostDetailBatchMessage($rawPost['id']));
            }
        }
        $output->writeln('Done! You have initialized crawl the collection of all posts');

        return Command::SUCCESS;
    }
}
