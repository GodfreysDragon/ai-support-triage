<?php

namespace App\Providers;

use Anthropic\Client;
use App\Ai\ClaudeSupportAssistant;
use App\Ai\Contracts\SupportAssistant;
use App\Ai\FakeSupportAssistant;
use App\Ai\TicketPromptBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SupportAssistant::class, fn () => match (config('ai.driver')) {
            'fake' => new FakeSupportAssistant(config('ai.fake_chunk_delay_ms')),
            default => new ClaudeSupportAssistant(
                new Client(apiKey: config('ai.anthropic.api_key')),
                new TicketPromptBuilder,
                config('ai.anthropic.model'),
                config('ai.anthropic.triage'),
                config('ai.anthropic.reply'),
            ),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Limits on the endpoints that call the model, and on demo sign-ups.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(config('ai.rate_limits.per_minute'))
            ->by($request->user()?->id ?: $request->ip()));

        // Each "Try the demo" click creates an account, so cap it per visitor.
        RateLimiter::for('demo', fn (Request $request) => Limit::perMinute(config('demo.per_minute'))
            ->by($request->ip()));
    }
}
