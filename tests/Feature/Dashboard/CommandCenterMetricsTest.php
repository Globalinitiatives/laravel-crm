<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\MultiDbSaas\Helpers\CommandCenter;

uses(DatabaseTransactions::class);

/**
 * Metric correctness against the contract §16 definitions, on deterministic fixtures.
 * All leads are raw-inserted because the engine reads direct columns (lead_value,
 * closed_at, status, created_at) and must see exactly what we set.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-11-15 12:00:00 UTC');

    $this->pipeline = DB::table('lead_pipelines')->orderBy('id')->first();

    // Self-contained fixtures: guarantee the canonical codes this engine keys on,
    // whatever the installer seed happens to contain.
    $this->ensureStage = function (string $code, string $name, int $sort) {
        $st = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $this->pipeline->id)->where('code', $code)->first();
        if (! $st) {
            $id = DB::table('lead_pipeline_stages')->insertGetId([
                'name' => $name, 'code' => $code, 'sort_order' => $sort,
                'lead_pipeline_id' => $this->pipeline->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $st = DB::table('lead_pipeline_stages')->find($id);
        }

        return $st;
    };

    $this->ensureStage('new', 'New', 1);
    $this->ensureStage('follow-up', 'Follow Up', 2);
    $this->ensureStage('won', 'Won', 96);
    $this->ensureStage('lost', 'Lost', 97);

    $this->stage = fn (string $code) => ($this->ensureStage)($code, ucfirst($code), $code === 'won' ? 96 : ($code === 'lost' ? 97 : 3));
});

function ccInsertLead(object $t, array $o): int
{
    $id = DB::table('leads')->insertGetId(array_merge([
        'title' => 'CC fixture lead',
        'status' => 0,
        'user_id' => 1,
        'lead_pipeline_id' => $t->pipeline->id,
        'lead_pipeline_stage_id' => $t->stage('new')->id ?? $t->stage('follow-up')->id,
        'lead_value' => null,
        'created_at' => '2026-11-15 10:00:00',
        'updated_at' => '2026-11-15 10:00:00',
    ], $o));

    return $id;
}

function ccSystemStageMove(object $t, int $leadId, string $fromCode, string $toCode, string $at = '2026-11-15 09:00:00'): void
{
    $activityId = DB::table('activities')->insertGetId([
        'title' => 'Lead updated',
        'type' => 'system_log',
        'is_done' => 1,
        'user_id' => 1,
        'additional' => json_encode([
            'attribute' => 'Stage',
            'old' => ['value' => (int) $t->stage($fromCode)->id, 'label' => $fromCode],
            'new' => ['value' => (int) $t->stage($toCode)->id, 'label' => $toCode],
        ]),
        'created_at' => $at,
        'updated_at' => $at,
    ]);

    DB::table('lead_activities')->insert(['activity_id' => $activityId, 'lead_id' => $leadId]);
}

test('new leads counts creations in period and excludes out-of-window', function () {
    ccInsertLead($this, ['title' => 'nov in window']);
    ccInsertLead($this, ['title' => 'nov in window 2', 'created_at' => '2026-11-10 08:00:00']);
    ccInsertLead($this, ['title' => 'oct out', 'created_at' => '2026-10-26 08:00:00', 'updated_at' => '2026-10-26 08:00:00']);

    $kpis = app(CommandCenter::class)->kpis(CommandCenter::resolveRange('this_month', null, null, 'UTC'), $this->pipeline->id, null);

    expect($kpis['new_leads']['value'])->toBe(2);
});

test('won deals are closed_at based, not creation based', function () {
    // created in October, WON in November -> counts as a November won deal
    $id = ccInsertLead($this, ['created_at' => '2026-10-05 08:00:00', 'updated_at' => '2026-11-14 08:00:00']);
    DB::table('leads')->where('id', $id)->update([
        'status' => 1,
        'lead_pipeline_stage_id' => $this->stage('won')->id,
        'closed_at' => '2026-11-14 08:00:00',
        'lead_value' => 500,
    ]);

    $kpis = app(CommandCenter::class)->kpis(CommandCenter::resolveRange('this_month', null, null, 'UTC'), $this->pipeline->id, null);

    expect($kpis['won_deals']['value'])->toBe(1)
        ->and($kpis['won_deals']['previous'])->toBe(0)
        ->and($kpis['won_deals']['direction'])->toBe('new');
});

test('stage progressions counts only transitions into non-initial stages', function () {
    $a = ccInsertLead($this, ['title' => 'moved forward']);
    $b = ccInsertLead($this, ['title' => 'moved backward']);

    ccSystemStageMove($this, $a, 'new', 'follow-up');
    ccSystemStageMove($this, $b, 'follow-up', 'new');

    $kpis = app(CommandCenter::class)->kpis(CommandCenter::resolveRange('this_month', null, null, 'UTC'), $this->pipeline->id, null);

    expect($kpis['stage_progressions']['value'])->toBe(1);
});

test('conversion rate uses terminal outcome cohort', function () {
    $won = ccInsertLead($this, ['title' => 'w']);
    DB::table('leads')->where('id', $won)->update(['status' => 1, 'lead_pipeline_stage_id' => $this->stage('won')->id, 'closed_at' => now()]);
    ccInsertLead($this, ['title' => 'l', 'lead_pipeline_stage_id' => $this->stage('lost')->id]);

    $kpis = app(CommandCenter::class)->kpis(CommandCenter::resolveRange('this_month', null, null, 'UTC'), $this->pipeline->id, null);

    expect($kpis['conversion_rate']['value'])->toBe(50.0)
        ->and($kpis['conversion_rate']['cohort'])->toBe(['won' => 1, 'lost' => 1, 'terminal' => 2]);
});

test('pipeline widget excludes closed stages and sums open value', function () {
    ccInsertLead($this, ['lead_value' => 100]);
    ccInsertLead($this, ['lead_value' => 200]);
    $won = ccInsertLead($this, ['lead_value' => 999]);
    DB::table('leads')->where('id', $won)->update(['status' => 1, 'lead_pipeline_stage_id' => $this->stage('won')->id, 'closed_at' => now()]);

    $p = app(CommandCenter::class)->pipeline($this->pipeline->id, null, 7);

    expect($p['open_leads'])->toBe(2)
        ->and((float) $p['pipeline_value'])->toBe(300.0)
        ->and(collect($p['stages'])->where('name', $this->stage('won')->name)->isEmpty())->toBeTrue();
});

test('stale detection uses the configurable threshold', function () {
    ccInsertLead($this, ['title' => 'old and silent', 'created_at' => '2026-10-20 08:00:00', 'updated_at' => '2026-10-20 08:00:00']);
    ccInsertLead($this, ['title' => 'fresh']);

    $pr = app(CommandCenter::class)->priorities(null, 7);
    $pr30 = app(CommandCenter::class)->priorities(null, 30);

    expect($pr['stale']['count'])->toBe(1)
        ->and($pr['stale']['threshold_days'])->toBe(7)
        ->and($pr30['stale']['count'])->toBe(0);
});

test('priorities classify overdue, due today, unassigned', function () {
    $lead = ccInsertLead($this, ['title' => 'has overdue task']);
    $act = DB::table('activities')->insertGetId(['title' => 'Call back', 'type' => 'call', 'is_done' => 0, 'user_id' => 1, 'schedule_to' => '2026-11-14 16:00:00', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('lead_activities')->insert(['activity_id' => $act, 'lead_id' => $lead]);

    $dueAct = DB::table('activities')->insertGetId(['title' => 'Send quote', 'type' => 'note', 'is_done' => 0, 'user_id' => 1, 'schedule_to' => '2026-11-15 15:00:00', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('lead_activities')->insert(['activity_id' => $dueAct, 'lead_id' => $lead]);

    ccInsertLead($this, ['title' => 'nobody owns me', 'user_id' => null]);

    $pr = app(CommandCenter::class)->priorities(null, 7);

    expect($pr['overdue']['count'])->toBe(1)
        ->and($pr['overdue']['items'][0]['title'])->toBe('Call back')
        ->and($pr['due_today']['count'])->toBe(1)
        ->and($pr['unassigned']['count'])->toBe(1)
        ->and($pr['all_caught_up'])->toBeFalse();
});

test('won deal value series buckets by closed_at', function () {
    foreach ([['2026-11-03', 300], ['2026-11-03', 200], ['2026-11-10', 500]] as [$day, $v]) {
        $id = ccInsertLead($this, ['lead_value' => $v, 'created_at' => $day.' 09:00:00']);
        DB::table('leads')->where('id', $id)->update(['status' => 1, 'lead_pipeline_stage_id' => $this->stage('won')->id, 'closed_at' => $day.' 10:00:00']);
    }

    $s = app(CommandCenter::class)->wonValueSeries(CommandCenter::resolveRange('this_month', null, null, 'UTC'), 'daily', $this->pipeline->id, null);

    expect((float) $s['total'])->toBe(1000.0)
        ->and($s['series'])->toHaveCount(2)
        ->and(collect($s['series'])->firstWhere('bucket', '2026-11-03')['total'])->toBe(500.0);
});

test('sources distinguishes configured from unattributed', function () {
    $sourceId = DB::table('lead_sources')->insertGetId(['name' => 'Website Widget', 'created_at' => now(), 'updated_at' => now()]);
    ccInsertLead($this, ['lead_source_id' => $sourceId]);
    ccInsertLead($this, ['title' => 'no source']);

    $src = app(CommandCenter::class)->sources(CommandCenter::resolveRange('this_month', null, null, 'UTC'), null);

    expect($src['configured'])->toBeTrue()
        ->and($src['attributed'])->toBe(1)
        ->and(collect($src['items'])->firstWhere('source', 'Unattributed'))->not->toBeNull();
});

test('stale days helper defaults to seven', function () {
    expect(CommandCenter::staleDays())->toBe(7);
});

afterEach(function () {
    Carbon::setTestNow();
});
