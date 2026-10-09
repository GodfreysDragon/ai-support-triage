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

];
