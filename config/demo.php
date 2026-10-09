<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    |
    | "Try the demo" on the landing page creates a throwaway account seeded
    | with sample tickets and signs the visitor in. Each visitor gets their
    | own account, so nobody sees anyone else's changes.
    |
    */

    'enabled' => (bool) env('DEMO_ENABLED', true),

    // Demo accounts older than this are deleted, along with their tickets.
    'lifetime_hours' => (int) env('DEMO_LIFETIME_HOURS', 24),

    // New demo accounts per minute, per IP address.
    'per_minute' => (int) env('DEMO_PER_MINUTE', 5),

    // New demo accounts per hour across all visitors (a backstop for the
    // per-IP limit, which a client could dodge behind a misconfigured proxy).
    'per_hour_total' => (int) env('DEMO_PER_HOUR_TOTAL', 120),

];
