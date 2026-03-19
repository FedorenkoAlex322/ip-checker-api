<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\RetryConfig;

interface RetryableInterface
{
    public function execute(callable $operation, RetryConfig $config): mixed;
}
