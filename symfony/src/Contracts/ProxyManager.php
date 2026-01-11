<?php

namespace App\Contracts;

interface ProxyManager
{
    public function acquire(): ?string;
}
