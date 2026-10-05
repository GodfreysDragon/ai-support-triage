<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Driver
    |--------------------------------------------------------------------------
    |
    | "claude" calls the Anthropic API. "fake" uses a deterministic offline
    | assistant so you can run and demo the app without an API key.
    |
    */

    'driver' => env('AI_DRIVER', 'claude'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Per-user limits on the endpoints that spend tokens.
    |
    */

    'rate_limits' => [
        'per_minute' => (int) env('AI_REQUESTS_PER_MINUTE', 10),
    ],

    // Artificial delay between fake stream chunks, so streaming is visible.
    'fake_chunk_delay_ms' => (int) env('AI_FAKE_CHUNK_DELAY_MS', 40),

];
