<?php

namespace App\Command;

use App\Factory\PostFactory;
use App\Service\PostScraperService;
use App\Service\PostService;
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
    const int DELAY_IN_MS = 600000;

    public function __construct(
        private PostScraperService $postScraper,
        private PostService $postService,
        private PostFactory $postFactory
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

            foreach ($this->postScraper->getPostsList($i) as $post) {
                $createPostInputDTO = $this->postFactory->makeCreatePostInputDTO($post);
                $this->postService->createIfNotExists($createPostInputDTO);
            }

            usleep(self::DELAY_IN_MS);
        }
        $output->writeln('Done! Now start <info>php bin/console post:get-detail</info> for update \'body\'');

        return Command::SUCCESS;
    }
}
