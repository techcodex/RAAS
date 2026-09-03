<?php

it('reports service health', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'service' => 'backend',
        ])
        ->assertJsonStructure(['status', 'service', 'env']);
});
