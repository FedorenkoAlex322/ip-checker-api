<?php

namespace App\Contracts;

use App\DTOs\RetryConfig;

interface RetryableInterface
{
    public function execute(callable $operation, RetryConfig $config): mixed;
}
