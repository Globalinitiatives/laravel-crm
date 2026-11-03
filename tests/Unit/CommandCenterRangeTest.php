<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\MultiDbSaas\Helpers\CommandCenter;

uses(DatabaseTransactions::class);

/**
 * Period resolution is the foundation of every widget number; boundaries are pinned here
 * against contract §4 (nine reporting periods + custom range + equal-length comparison).
 */
test('today spans the calendar day and compares to yesterday', function () {
    $r = CommandCenter::resolveRange('today', null, null, 'UTC');

    expect($r['start']->toTimeString())->toBe('00:00:00')
        ->and($r['end']->toTimeString())->toBe('23:59:59')
        ->and($r['prev_start']->toDateString())->toBe(now('UTC')->subDay()->toDateString());
});

test('yesterday is its own window', function () {
    $r = CommandCenter::resolveRange('yesterday', null, null, 'UTC');
    expect($r['start']->toDateString())->toBe(now('UTC')->subDay()->toDateString());
});

test('this week starts on monday and compares to last week', function () {
    $r = CommandCenter::resolveRange('this_week', null, null, 'UTC');
    expect($r['start']->dayOfWeekIso)->toBe(1)
        ->and($r['prev_start']->dayOfWeekIso)->toBe(1)
        ->and((int) $r['prev_start']->diffInDays($r['start']))->toBe(7);
});

test('month quarter year boundaries', function () {
    $m = CommandCenter::resolveRange('this_month', null, null, 'UTC');
    expect($m['start']->day)->toBe(1)->and($m['start']->toTimeString())->toBe('00:00:00');

    $q = CommandCenter::resolveRange('this_quarter', null, null, 'UTC');
    expect(in_array($q['start']->month, [1, 4, 7, 10], true))->toBeTrue();

    $y = CommandCenter::resolveRange('this_year', null, null, 'UTC');
    expect($y['start']->month)->toBe(1)->and($y['start']->day)->toBe(1);
});

test('last month window has same length as its comparison window', function () {
    $r = CommandCenter::resolveRange('last_month', null, null, 'UTC');
    expect($r['start']->diffInDays($r['end']))->toBe($r['prev_start']->diffInDays($r['prev_end']));
});

test('custom range swaps inverted dates', function () {
    $r = CommandCenter::resolveRange('custom', '2026-10-31', '2026-10-01', 'UTC');
    expect($r['start']->toDateString())->toBe('2026-10-01')
        ->and($r['end']->toDateString())->toBe('2026-10-31');
});

test('custom without bounds falls back to this month', function () {
    $r = CommandCenter::resolveRange('custom', null, null, 'UTC');
    expect($r['period'])->toBe('this_month');
});

test('unknown period falls back to this month', function () {
    expect(CommandCenter::resolveRange('nonsense', null, null, 'UTC')['period'])->toBe('this_month');
});

test('comparison window is always immediately preceding and equal length', function () {
    $r = CommandCenter::resolveRange('this_month', null, null, 'UTC');
    $curLen = $r['start']->copy()->startOfDay()->diffInDays($r['end']->copy()->startOfDay()) + 1;
    $prevLen = $r['prev_start']->copy()->startOfDay()->diffInDays($r['prev_end']->copy()->startOfDay()) + 1;

    expect((int) $prevLen)->toBe((int) $curLen)
        ->and($r['prev_end']->copy()->addDay()->startOfDay()->toDateString())->toBe($r['start']->copy()->startOfDay()->toDateString());
});
