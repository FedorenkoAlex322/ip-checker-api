<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApiKeyTier;
use App\Services\ApiKey\ApiKeyService;
use Illuminate\Database\Seeder;

final class ApiKeySeeder extends Seeder
{
    public function run(ApiKeyService $apiKeyService): void
    {
        $keys = [
            ['name' => 'Demo Free Key', 'tier' => ApiKeyTier::Free],
            ['name' => 'Demo Pro Key', 'tier' => ApiKeyTier::Pro],
            ['name' => 'Demo Enterprise Key', 'tier' => ApiKeyTier::Enterprise],
        ];

        foreach ($keys as $keyData) {
            $plaintext = $apiKeyService->generateKey($keyData['name'], $keyData['tier']);
            $this->command->info("Created {$keyData['tier']->value} key [{$keyData['name']}]: {$plaintext}");
        }
    }
}
