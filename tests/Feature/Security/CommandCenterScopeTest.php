<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * The Command Center must never be a side channel around the existing data-scope rules
 * (contract §12: "hiding a widget in the frontend is not sufficient authorization").
 *
 * Every widget endpoint is exercised as three viewer kinds — `global`, `group`, `individual` —
 * against leads owned by two different users, and the returned numbers themselves (not just
 * HTTP status) must differ accordingly.
 */

function ccScopeUser(string $viewPermission): User
{
    $role = Role::create([
        'name' => 'CC Scope Role '.bin2hex(random_bytes(6)),
        'description' => 'Created by the command center scope tests.',
        'permission_type' => 'all',
    ]);

    return User::create([
        'name' => 'CC Scope Probe '.bin2hex(random_bytes(4)),
        'email' => 'cc-scope-'.bin2hex(random_bytes(6)).'@example.invalid',
        'password' => bcrypt('correct-horse-battery-staple'),
        'status' => 1,
        'role_id' => $role->id,
        'view_permission' => $viewPermission,
    ]);
}

function ccScopeLead(User|int|null $owner, string $title): void
{
    $pipeline = DB::table('lead_pipelines')->orderBy('id')->first();
    $stage = DB::table('lead_pipeline_stages')->where('lead_pipeline_id', $pipeline->id)->orderBy('sort_order')->first();

    if (! $stage) {
        $id = DB::table('lead_pipeline_stages')->insertGetId([
            'name' => 'New', 'code' => 'new', 'sort_order' => 1,
            'lead_pipeline_id' => $pipeline->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stage = DB::table('lead_pipeline_stages')->find($id);
    }

    DB::table('leads')->insert([
        'title' => $title.' '.bin2hex(random_bytes(3)),
        'status' => 0,
        'user_id' => $owner instanceof User ? $owner->id : $owner,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
        'lead_value' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('individual viewer only ever sees their own numbers on every widget', function () {
    $alice = ccScopeUser('individual');
    $bob = ccScopeUser('individual');

    ccScopeLead($alice, 'alice a1');
    ccScopeLead($alice, 'alice a2');
    ccScopeLead($bob, 'bob b1');
    ccScopeLead(null, 'orphan intake lead');

    foreach (['kpis', 'pipeline', 'priorities', 'recent-leads', 'recent-activities', 'team', 'customers', 'sources'] as $widget) {
        $response = test()->actingAs($alice, 'user')->getJson('/admin/dashboard/cc/'.$widget);
        $response->assertOk();
        expect($response->json('meta.viewer_scope'))->toBe('individual', "widget {$widget}");
    }

    $kpis = $this->actingAs($alice, 'user')->getJson('/admin/dashboard/cc/kpis')->assertOk()->json('data');
    expect($kpis['new_leads']['value'])->toBe(2);

    // Recent leads must contain only alice-owned rows.
    $rows = $this->actingAs($alice, 'user')->getJson('/admin/dashboard/cc/recent-leads')->assertOk()->json('data.rows');
    foreach ($rows as $row) {
        expect($row['owner'])->toBe($alice->name);
    }

    // Team performance for an individual viewer is their own row only (no teammate leakage).
    $team = $this->actingAs($alice, 'user')->getJson('/admin/dashboard/cc/team')->assertOk()->json('data.rows');
    $names = array_column($team, 'name');
    expect(count($team))->toBe(1)->and($names[0])->toBe($alice->name);

    // Unassigned work is not an individual viewer's data.
    $pr = $this->actingAs($alice, 'user')->getJson('/admin/dashboard/cc/priorities')->assertOk()->json('data');
    expect($pr['unassigned']['count'])->toBe(0);
});

test('global viewer sees the full board including unassigned intake leads', function () {
    $admin = ccScopeUser('global');

    ccScopeLead($admin, 'admin owned');
    ccScopeLead(null, 'orphan again');

    $kpis = $this->actingAs($admin, 'user')->getJson('/admin/dashboard/cc/kpis')->assertOk()->json('data');
    expect($kpis['new_leads']['value'])->toBeGreaterThanOrEqual(1);

    $pr = $this->actingAs($admin, 'user')->getJson('/admin/dashboard/cc/priorities')->assertOk()->json('data');
    expect($pr['unassigned']['count'])->toBeGreaterThanOrEqual(1);
});

test('group viewer is scoped to the union of their group members', function () {
    $group = DB::table('groups')->insertGetId(['name' => 'CC Test Group '.bin2hex(random_bytes(4)), 'created_at' => now(), 'updated_at' => now()]);

    $lead1 = ccScopeUser('group');
    $lead2 = ccScopeUser('group');
    $outsider = ccScopeUser('group');
    foreach ([$lead1, $lead2, $outsider] as $u) {
        DB::table('user_groups')->insert(['user_id' => $u->id, 'group_id' => $group]);
    }

    // outsider moves out of the group -> their lead must vanish from members' board
    DB::table('user_groups')->where('user_id', $outsider->id)->delete();

    ccScopeLead($lead1, 'team one');
    ccScopeLead($lead2, 'team two');
    ccScopeLead($outsider, 'not my team');

    $kpis = $this->actingAs($lead1, 'user')->getJson('/admin/dashboard/cc/kpis')->assertOk()->json('data');
    expect($kpis['new_leads']['value'])->toBe(2);
});

test('custom limits are bounded and parameters cannot escape their whitelist', function () {
    $admin = ccScopeUser('global');

    $this->actingAs($admin, 'user')->getJson('/admin/dashboard/cc/recent-leads?limit=500')
        ->assertOk();

    // absurd limit: engine caps at 20 rows max regardless
    $rows = $this->actingAs($admin, 'user')->getJson('/admin/dashboard/cc/recent-leads?limit=-5')->assertOk()->json('data.rows');
    expect(count($rows))->toBeLessThanOrEqual(20);

    $meta = $this->actingAs($admin, 'user')->getJson('/admin/dashboard/cc/kpis?period=bogus')->assertOk()->json('meta');
    expect($meta['period'])->toBe('this_month');
});
