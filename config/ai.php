<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Driver
    |--------------------------------------------------------------------------
    |
    | "fake" (the default) uses a deterministic offline assistant, so the app
    | runs and demos without an API key or cost. "claude" calls the Anthropic
    | API.
    |
    */

    'driver' => env('AI_DRIVER', 'fake'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),

        // Classification is short, schema-bound output: low effort and a
        // small token budget are enough.
        'triage' => [
            'max_tokens' => 4096,
            'effort' => 'low',
        ],

        // Drafting is customer-facing prose, so it gets more effort and
        // room to write.
        'reply' => [
            'max_tokens' => 16000,
            'effort' => 'medium',
        ],
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
