<?php

namespace App\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;

class CommandBus
{
    public function __construct(
        private MessageBusInterface $bus
    ) {}

    public function dispatch(object $message): void
    {
        $this->bus->dispatch($message);
    }
}
