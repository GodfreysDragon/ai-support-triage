<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

afterEach(fn () => TrustProxies::flushState());

/**
 * Re-run the provider's boot so it reads the current config, as it would at startup.
 */
function bootWithTrustedProxies(?string $proxies): void
{
    config(['app.trusted_proxies' => $proxies]);
    app()->getProvider(AppServiceProvider::class)->boot();
}

test('behind a trusted proxy, forwarded https is honoured in generated URLs', function () {
    bootWithTrustedProxies('*');

    $this->get('/dashboard', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'demo.example.com'])
        ->assertRedirect('https://demo.example.com/login');
});

test('without TRUSTED_PROXIES, forwarded headers are ignored', function () {
    bootWithTrustedProxies(null);

    $this->get('/dashboard', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example.com'])
        ->assertRedirect(rtrim(config('app.url'), '/').'/login');
});
