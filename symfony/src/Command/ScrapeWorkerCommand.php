<?php

namespace App\Command;

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
    public function __construct(private PostScraperService $postScraper)
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
        $posts = $this->postScraper->getPostsList();

        $output->writeln('Proxy: ' . $input->getOption('proxy'));
        $output->writeln('From page  ' . $input->getOption('from') . ' to ' . $input->getOption('to'));

        return Command::SUCCESS;
    }
}
