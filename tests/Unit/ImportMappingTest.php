<?php

use App\Jobs\ImportPhase1Job;

test('mapRegion maps us to us-east-2', function () {
    expect(ImportPhase1Job::mapRegion('us'))->toBe('us-east-2');
});

test('mapRegion maps eu to eu-west-2', function () {
    expect(ImportPhase1Job::mapRegion('eu'))->toBe('eu-west-2');
});

test('mapRegion defaults to us-east-2', function () {
    expect(ImportPhase1Job::mapRegion('ap'))->toBe('us-east-2');
});

test('mapDynoSize maps eco to flex', function () {
    expect(ImportPhase1Job::mapDynoSize('eco'))->toBe('flex.g-1vcpu-512mb');
});

test('mapDynoSize maps standard-2x', function () {
    expect(ImportPhase1Job::mapDynoSize('Standard-2X'))->toBe('flex.g-2vcpu-1gb');
});

test('mapDynoSize maps performance-m', function () {
    expect(ImportPhase1Job::mapDynoSize('performance-m'))->toBe('pro.g-2vcpu-4gb');
});

test('mapDynoSize maps performance-l', function () {
    expect(ImportPhase1Job::mapDynoSize('performance-l'))->toBe('pro.g-8vcpu-16gb');
});

test('mapDynoSize defaults to flex for unknown', function () {
    expect(ImportPhase1Job::mapDynoSize('unknown'))->toBe('flex.g-1vcpu-512mb');
});
