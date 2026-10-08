<?php

use App\Ai\Data\TriageResult;

test('the triage schema gives the model the priority definitions', function () {
    expect(TriageResult::schema()['properties']['priority']['description'])->toBe(
        'urgent = outage, data loss, security or payment failure blocking the customer; high = core feature broken; medium = degraded or confusing; low = questions and suggestions.'
    );
});

test('the triage schema lists every enum value', function () {
    $properties = TriageResult::schema()['properties'];

    expect($properties['category']['enum'])->toBe(['billing', 'bug', 'feature_request', 'account', 'how_to', 'other'])
        ->and($properties['priority']['enum'])->toBe(['low', 'medium', 'high', 'urgent'])
        ->and($properties['sentiment']['enum'])->toBe(['positive', 'neutral', 'negative', 'angry']);
});
