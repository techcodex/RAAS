<?php

use Illuminate\Support\Facades\RateLimiter;

beforeEach(fn () => RateLimiter::clear('login'));

it('throttles repeated failed logins for one account', function () {
    createOwner(['email' => 'target@example.com', 'password' => 'correct-horse']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'target@example.com', 'password' => 'wrong'])
            ->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'target@example.com', 'password' => 'wrong'])
        ->assertStatus(429);
});

it('does not let one account lock out another from the same IP', function () {
    createOwner(['email' => 'victim@example.com', 'password' => 'victim-pw']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'attacker@example.com', 'password' => 'x'])
            ->assertUnprocessable();
    }

    // A different account from the same IP is still under its own per-account budget.
    $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'victim-pw'])
        ->assertOk();
});
