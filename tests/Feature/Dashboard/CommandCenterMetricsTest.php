<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\MultiDbSaas\Helpers\CommandCenter;

uses(DatabaseTransactions::class);

/**
 * Metric correctness against the contract §16 definitions, on deterministic fixtures.
 * Leads are raw-inserted because the engine reads direct columns (lead_value,
 * closed_at, status, created_at, closed_at windowing) and must see exactly what we set.
 */
const CC_NOW = '2026-11-15 12:00:00';

function ccPipeline(): object
{
    return DB::table('lead_pipelines')->orderBy('id')->first();
}

function ccStage(object $pipeline, string $code, ?int $sort = null): object
{
    $st = DB::table('lead_pipeline_stages')
        ->where('lead_pipeline_id', $pipeline->id)->where('code', $code)->first();

    if ($st) {
        return $st;
    }

    $id = DB::table('lead_pipeline_stages')->insertGetId([
        'name' => ucfirst($code), 'code' => $code,
        'sort_order' => $sort ?? 50, 'lead_pipeline_id' => $pipeline->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('lead_pipeline_stages')->find($id);
}

function ccLead(object $p, array $o = []): int
{
    return DB::table('leads')->insertGetId(array_merge([
        'title' => 'CC fixture '.bin2hex(random_bytes(3)),
        'status' => 0,
        'user_id' => 1,
        'lead_pipeline_id' => $p->id,
        'lead_pipeline_stage_id' => ccStage($p, 'new', 1)->id,
        'lead_value' => null,
        'created_at' => '2026-11-15 10:00:00',
        'updated_at' => '2026-11-15 10:00:00',
    ], $o));
}

function ccMove(object $p, int $leadId, string $from, string $to, string $at = '2026-11-15 09:00:00'): void
{
    $aid = DB::table('activities')->insertGetId([
        'title' => 'Lead updated', 'type' => 'system_log', 'is_done' => 1, 'user_id' => 1,
        'additional' => json_encode([
            'attribute' => 'Stage',
            'old' => ['value' => (int) ccStage($p, $from)->id, 'label' => $from],
            'new' => ['value' => (int) ccStage($p, $to)->id, 'label' => $to],
        ]),
        'created_at' => $at, 'updated_at' => $at,
    ]);
    DB::table('lead_activities')->insert(['activity_id' => $aid, 'lead_id' => $leadId]);
}

function ccRange(string $period = 'this_month'): array
{
    return CommandCenter::resolveRange($period, null, null, 'UTC');
}

beforeEach(function () {
    Carbon::setTestNow(CC_NOW);
    $this->pipeline = ccPipeline();
    // Guarantee canonical codes regardless of installer seed content.
    foreach ([['new', 1], ['follow-up', 2], ['won', 96], ['lost', 97]] as [$code, $sort]) {
        ccStage($this->pipeline, $code, $sort);
    }
});

afterEach(function () {
    Carbon::setTestNow();
});

test('new leads counts creations in period and excludes out-of-window', function () {
    ccLead($this->pipeline, ['title' => 'nov in window']);
    ccLead($this->pipeline, ['created_at' => '2026-11-10 08:00:00']);
    ccLead($this->pipeline, ['created_at' => '2026-10-26 08:00:00', 'updated_at' => '2026-10-26 08:00:00']);

    $kpis = app(CommandCenter::class)->kpis(ccRange(), $this->pipeline->id, null);

    // comparison window = Oct 2-31 (equal-length, immediately preceding) -> includes the Oct-26 lead
    expect($kpis['new_leads']['value'])->toBe(2)
        ->and($kpis['new_leads']['previous'])->toBe(1);
});

test('won deals are closed_at based, not creation based', function () {
    // created in October, WON in November -> counts as a November won deal
    $id = ccLead($this->pipeline, ['created_at' => '2026-10-05 08:00:00', 'updated_at' => '2026-11-14 08:00:00']);
    DB::table('leads')->where('id', $id)->update([
        'status' => 1,
        'lead_pipeline_stage_id' => ccStage($this->pipeline, 'won')->id,
        'closed_at' => '2026-11-14 08:00:00',
        'lead_value' => 500,
    ]);

    $kpis = app(CommandCenter::class)->kpis(ccRange(), $this->pipeline->id, null);

    expect($kpis['won_deals']['value'])->toBe(1)
        ->and($kpis['won_deals']['previous'])->toBe(0)
        ->and($kpis['won_deals']['direction'])->toBe('new');
});

test('stage progressions counts only transitions into non-initial stages', function () {
    $a = ccLead($this->pipeline, ['title' => 'moved forward']);
    $b = ccLead($this->pipeline, ['title' => 'moved backward']);

    ccMove($this->pipeline, $a, 'new', 'follow-up');
    ccMove($this->pipeline, $b, 'follow-up', 'new');

    $kpis = app(CommandCenter::class)->kpis(ccRange(), $this->pipeline->id, null);

    expect($kpis['stage_progressions']['value'])->toBe(1);
});

test('conversion rate uses terminal outcome cohort', function () {
    $won = ccLead($this->pipeline, ['title' => 'w']);
    DB::table('leads')->where('id', $won)->update([
        'status' => 1,
        'lead_pipeline_stage_id' => ccStage($this->pipeline, 'won')->id,
        'closed_at' => now(),
    ]);
    ccLead($this->pipeline, ['title' => 'l', 'lead_pipeline_stage_id' => ccStage($this->pipeline, 'lost')->id]);

    $kpis = app(CommandCenter::class)->kpis(ccRange(), $this->pipeline->id, null);

    expect($kpis['conversion_rate']['value'])->toBe(50.0)
        ->and($kpis['conversion_rate']['cohort'])->toBe(['won' => 1, 'lost' => 1, 'terminal' => 2]);
});

test('pipeline widget excludes closed stages and sums open value', function () {
    ccLead($this->pipeline, ['lead_value' => 100]);
    ccLead($this->pipeline, ['lead_value' => 200]);
    $won = ccLead($this->pipeline, ['lead_value' => 999]);
    DB::table('leads')->where('id', $won)->update([
        'status' => 1,
        'lead_pipeline_stage_id' => ccStage($this->pipeline, 'won')->id,
        'closed_at' => now(),
    ]);

    $p = app(CommandCenter::class)->pipeline($this->pipeline->id, null, 7);

    expect($p['open_leads'])->toBe(2)
        ->and((float) $p['pipeline_value'])->toBe(300.0)
        ->and(collect($p['stages'])->where('id', ccStage($this->pipeline, 'won')->id)->isEmpty())->toBeTrue();
});

test('stale detection uses the configurable threshold', function () {
    ccLead($this->pipeline, ['title' => 'old and silent', 'created_at' => '2026-10-20 08:00:00', 'updated_at' => '2026-10-20 08:00:00']);
    ccLead($this->pipeline, ['title' => 'fresh']);

    $pr = app(CommandCenter::class)->priorities(null, 7);
    $pr30 = app(CommandCenter::class)->priorities(null, 30);

    expect($pr['stale']['count'])->toBe(1)
        ->and($pr['stale']['threshold_days'])->toBe(7)
        ->and($pr30['stale']['count'])->toBe(0);
});

test('priorities classify overdue, due today, unassigned', function () {
    $lead = ccLead($this->pipeline, ['title' => 'has overdue task']);
    $act = DB::table('activities')->insertGetId([
        'title' => 'Call back', 'type' => 'call', 'is_done' => 0, 'user_id' => 1,
        'schedule_to' => '2026-11-14 16:00:00', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('lead_activities')->insert(['activity_id' => $act, 'lead_id' => $lead]);

    $dueAct = DB::table('activities')->insertGetId([
        'title' => 'Send quote', 'type' => 'note', 'is_done' => 0, 'user_id' => 1,
        'schedule_to' => '2026-11-15 15:00:00', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('lead_activities')->insert(['activity_id' => $dueAct, 'lead_id' => $lead]);

    ccLead($this->pipeline, ['title' => 'nobody owns me', 'user_id' => null]);

    $pr = app(CommandCenter::class)->priorities(null, 7);

    expect($pr['overdue']['count'])->toBe(1)
        ->and($pr['overdue']['items'][0]['title'])->toBe('Call back')
        ->and($pr['due_today']['count'])->toBe(1)
        ->and($pr['unassigned']['count'])->toBe(1)
        ->and($pr['all_caught_up'])->toBeFalse();
});

test('won deal value series buckets by closed_at', function () {
    foreach ([['2026-11-03', 300], ['2026-11-03', 200], ['2026-11-10', 500]] as [$day, $v]) {
        $id = ccLead($this->pipeline, ['lead_value' => $v, 'created_at' => $day.' 09:00:00']);
        DB::table('leads')->where('id', $id)->update([
            'status' => 1,
            'lead_pipeline_stage_id' => ccStage($this->pipeline, 'won')->id,
            'closed_at' => $day.' 10:00:00',
        ]);
    }

    $s = app(CommandCenter::class)->wonValueSeries(ccRange(), 'daily', $this->pipeline->id, null);

    expect((float) $s['total'])->toBe(1000.0)
        ->and($s['series'])->toHaveCount(2)
        ->and(collect($s['series'])->firstWhere('bucket', '2026-11-03')['total'])->toBe(500.0);
});

test('sources distinguishes configured from unattributed', function () {
    $sourceId = DB::table('lead_sources')->insertGetId(['name' => 'Website Widget '.bin2hex(random_bytes(3)), 'created_at' => now(), 'updated_at' => now()]);
    ccLead($this->pipeline, ['lead_source_id' => $sourceId]);
    ccLead($this->pipeline, ['title' => 'no source']);

    $src = app(CommandCenter::class)->sources(ccRange(), null);

    expect($src['configured'])->toBeTrue()
        ->and($src['attributed'])->toBe(1)
        ->and(collect($src['items'])->firstWhere('source', 'Unattributed'))->not->toBeNull();
});

test('stale days helper defaults to seven', function () {
    expect(CommandCenter::staleDays())->toBe(7);
});
