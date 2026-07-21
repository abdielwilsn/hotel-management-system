<?php

use App\Support\StayCalculator;
use App\Support\StayPolicy;
use Carbon\CarbonImmutable;

/**
 * The house clock these cases are written against: arrive from 14:00, leave by
 * 12:00, and 08:00 is the earliest an arrival still counts as that morning.
 */
function calculator(): StayCalculator
{
    return new StayCalculator(new StayPolicy(
        checkInTime: '14:00',
        checkOutTime: '12:00',
        earlyCheckInFrom: '08:00',
    ));
}

function nightsFor(string $arrival, string $departure): int
{
    return calculator()->charge(
        CarbonImmutable::parse($arrival),
        CarbonImmutable::parse($departure),
    )->nights;
}

test('the standard stay is one night even though it is under 24 hours', function () {
    expect(nightsFor('2026-03-10 14:00', '2026-03-11 12:00'))->toBe(1);
});

test('an early check-in leaving the same day is a single night', function () {
    expect(nightsFor('2026-03-10 08:00', '2026-03-10 12:00'))->toBe(1);
});

test('an early check-in rolling over to the next day is two nights', function () {
    expect(nightsFor('2026-03-10 08:00', '2026-03-11 12:00'))->toBe(2);
});

test('arriving before the early check-in time consumes the previous night', function (string $arrival) {
    expect(nightsFor($arrival, '2026-03-10 12:00'))->toBe(2);
})->with([
    '07:00' => '2026-03-10 07:00',
    '06:00' => '2026-03-10 06:00',
    '05:00' => '2026-03-10 05:00',
    '04:00' => '2026-03-10 04:00',
    'midnight' => '2026-03-10 00:30',
]);

test('the early check-in boundary itself is not an overnight arrival', function () {
    expect(nightsFor('2026-03-10 08:00', '2026-03-10 12:00'))->toBe(1);
    expect(nightsFor('2026-03-10 07:59', '2026-03-10 12:00'))->toBe(2);
});

test('a pre-dawn arrival staying on adds to the following nights', function () {
    // 05:00 on the 10th through noon on the 11th: the night before, plus the
    // night of the 10th.
    expect(nightsFor('2026-03-10 05:00', '2026-03-11 12:00'))->toBe(3);
});

test('multi night stays count each hotel day', function () {
    expect(nightsFor('2026-03-10 14:00', '2026-03-13 12:00'))->toBe(3);
    expect(nightsFor('2026-03-10 14:00', '2026-03-17 12:00'))->toBe(7);
});

test('a late departure past checkout runs into the next night', function () {
    expect(nightsFor('2026-03-10 14:00', '2026-03-11 18:00'))->toBe(2);
});

test('a stay is never charged less than one night', function () {
    expect(nightsFor('2026-03-10 14:00', '2026-03-10 16:00'))->toBe(1);
});

test('the basis explains an overnight arrival', function () {
    $charge = calculator()->charge(
        CarbonImmutable::parse('2026-03-10 05:00'),
        CarbonImmutable::parse('2026-03-10 12:00'),
    );

    expect($charge->consumedPreviousNight)->toBeTrue();
    expect($charge->basis)->toContain('before the 08:00 early check-in time');
    expect($charge->hotelDays)->toBe(1);
});

test('a deviation from the computed nights needs approval', function () {
    $charge = calculator()->charge(
        CarbonImmutable::parse('2026-03-10 08:00'),
        CarbonImmutable::parse('2026-03-11 12:00'),
    );

    expect($charge->nights)->toBe(2);
    expect($charge->requiresApprovalFor(2))->toBeFalse();
    expect($charge->requiresApprovalFor(1))->toBeTrue();
});

test('a hotel with a different clock bills by its own boundaries', function () {
    $calculator = new StayCalculator(new StayPolicy(
        checkInTime: '15:00',
        checkOutTime: '11:00',
        earlyCheckInFrom: '09:00',
    ));

    $nights = fn (string $a, string $d) => $calculator->charge(
        CarbonImmutable::parse($a),
        CarbonImmutable::parse($d),
    )->nights;

    expect($nights('2026-03-10 15:00', '2026-03-11 11:00'))->toBe(1);
    // 08:00 is before this hotel's 09:00 boundary, so it takes the night before.
    expect($nights('2026-03-10 08:00', '2026-03-10 11:00'))->toBe(2);
    expect($nights('2026-03-10 09:30', '2026-03-10 11:00'))->toBe(1);
});
