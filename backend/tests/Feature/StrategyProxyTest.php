<?php

use App\Services\RagClient;
use Laravel\Sanctum\Sanctum;

it('proxies and caches the rag-service strategy catalogue', function () {
    $catalogue = [
        'strategies' => [
            ['name' => 'auto', 'label' => 'Auto', 'config_schema' => [], 'defaults' => []],
            ['name' => 'recursive', 'label' => 'Recursive', 'config_schema' => [], 'defaults' => ['chunk_tokens' => 512]],
        ],
        'embedders' => [['provider' => 'local', 'models' => []]],
    ];

    $this->mock(RagClient::class)
        ->shouldReceive('strategies')->once()   // second call is served from cache
        ->andReturn($catalogue);

    Sanctum::actingAs(createOwner());

    $this->getJson('/api/v1/strategies')->assertOk()->assertJsonPath('strategies.1.name', 'recursive');
    $this->getJson('/api/v1/strategies')->assertOk();
});

it('requires authentication', function () {
    $this->getJson('/api/v1/strategies')->assertUnauthorized();
});
