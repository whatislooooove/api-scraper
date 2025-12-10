<?php

namespace App\Command;

use App\Message\GetPostDetailMessage;
use App\Service\PostScraperService;
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
    const int DELAY_IN_MS = 600000;

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
        $this->addOption('proxy', null, InputOption::VALUE_OPTIONAL, 'Proxy');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->postScraper->setProxy($input->getOption('proxy'));

        $output->writeln('Proxy: ' . $input->getOption('proxy'));
        $output->writeln('From page  ' . $input->getOption('from') . ' to ' . $input->getOption('to'));

        for ($i = $input->getOption('from'); $i < $input->getOption('to'); $i++) {
            $this->postScraper->setProxy($input->getOption('proxy'));

            foreach ($this->postScraper->getPostsListFromPage($i) as $rawPost) {
                //TODO: $rawPost['id'] надо убрать отсюда и сделать DTO для конструктора message
                $this->bus->dispatch(new GetPostDetailMessage($rawPost['id'], $input->getOption('proxy')));
            }

            //TODO: не надо делать delay, если запрос выполнялся условно больше секунды
            usleep(self::DELAY_IN_MS);
        }
        $output->writeln('Done! You have initialized crawl the collection of all posts');

        return Command::SUCCESS;
    }
}
