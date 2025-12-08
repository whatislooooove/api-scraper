<?php

namespace App\Command;

use App\Entity\Post;
use App\Service\PostScraperService;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $em
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
            $posts = $this->postScraper->getPostsList($i);
            // TODO: добавление нужно отсюда вынести
            // TODO: добавить проверку на существование поста по externalId
            foreach ($posts as $post) {
                $postObj = new Post();
                $postObj->setExternalId($post['id']);
                $postObj->setTitle($post['title']);
                $postObj->setDescription($post['description']);
                $postObj->setCreatedAt(new \DateTimeImmutable($post['createdAt']));
                $this->em->persist($postObj);
            }
            $this->em->flush();
            $this->em->clear();
            $this->em->getConnection()->close();
            sleep(60 / 100);
        }
        $output->writeln('Done! Now start <info>php bin/console post:get-detail</info> for update \'body\'');

        return Command::SUCCESS;
    }
}
