<?php

namespace App\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ScrapeMasterService
{
    private array $runningProcesses = [];

    public function __construct(
        private int $threads,
        private int $itemsToHandeCount,
        private OutputInterface $output,
        private ProxyManagerService $proxy
    )
    {
    }

    public function handle(): void
    {
        $this->runProcesses();
        $this->observeProcesses();
    }

    private function runProcesses(): void
    {
        $chunkSize = ceil($this->itemsToHandeCount / $this->threads);
        for ($i = 1; $i <= $this->threads; $i++) {
            $cmd = [
                PHP_BINARY,
                'bin/console',
                'scrape:worker',
                sprintf('--from=%d', $chunkSize * ($i - 1)),
                sprintf('--to=%d', $chunkSize * $i - 1),
                sprintf('--proxy=%s', $this->proxy->getProxyById($i - 1)),
            ];

            $process = new Process($cmd);
            $process->setTimeout(null);

            $this->output->writeln("<info>Starting worker $i</info>");
            $process->start();
            $this->runningProcesses[] = ['process' => $process, 'id' => $i];
        }
    }

    private function observeProcesses(): void
    {
        while (count($this->runningProcesses) > 0) {
            foreach ($this->runningProcesses as $id => $item) {
                $proc = $item['process'];
                if (!$proc->isRunning()) {
                    $exit = $proc->getExitCode();
                    if ($proc->isSuccessful()) {
                        $this->output->writeln("<info>Worker #{$item['id']} finished (exit={$exit})</info>");
                        $this->output->writeln($proc->getOutput());
                    } else {
                        $this->output->writeln("<error>Worker #{$item['id']} failed (exit={$exit})</error>");
                        $this->output->writeln($proc->getOutput());
                    }
                    unset($this->runningProcesses[$id]);
                }
            }
        }
    }
}
