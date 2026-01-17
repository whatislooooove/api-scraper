<?php

namespace App\Command;

use App\Message\GetPostDetailBatchMessage;
use App\Service\PageProgressCoordinator;
use App\Service\PostScraperService;
use Psr\Log\LoggerInterface;
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
        private MessageBusInterface $bus,
        private PageProgressCoordinator $pageProgress,
        private LoggerInterface $logger
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        // TODO: добавить валидацию
        $this->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start label');
        $this->addOption('to', null, InputOption::VALUE_OPTIONAL, 'Finish label');
        $this->addOption('restart', null, InputOption::VALUE_NONE, 'Continue scraping or start again');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('From page  ' . $input->getOption('from') . ' to ' . $input->getOption('to'));

        if ($input->getOption('restart')) {
            $this->pageProgress->clear();
        }

        for ($i = $input->getOption('from'); $i < $input->getOption('to'); $i++) {
            if (!$input->getOption('restart')) {
                if ($this->pageProgress->isScraped($i)) {
                    $this->logger->warning('Page ' . $i . ' already scrapped');
                    continue;
                }
            }

            //TODO: упремся в лимит 100 в минуту. Сделать паузу
            try {
                foreach ($this->postScraper->getPostsListFromPage($i) as $rawPost) {
                    //TODO: $rawPost['id'] надо убрать отсюда и сделать DTO для конструктора message
                    //TODO: сделать проверку, что уже есть message с такими id и proxy
                    $this->bus->dispatch(new GetPostDetailBatchMessage($rawPost['id']));
                    //sleep(1);
                }

                $this->pageProgress->markDone($i);
            } catch (\Throwable $e) {
                $this->logger->error('error on getting page: ' . $e->getMessage());
            }
        }
        $output->writeln('Done! You have initialized crawl the collection of all posts');

        return Command::SUCCESS;
    }
}
