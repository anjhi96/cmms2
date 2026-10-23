<?php

use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;

function pmCreateMachine(array $attributes = []): Machine
{
    return Machine::create(array_merge([
        'machine_number' => 'PMC-'.uniqid(),
        'area' => 'WWD',
        'machine_type' => 'NDE SW',
        'status' => 'ACTIVE',
    ], $attributes));
}

test('admin and koordinator can reach the create form, pic and supervisor cannot', function (string $role, bool $allowed) {
    $user = User::factory()->create(roleAttributes($role));

    $response = $this->actingAs($user)->get(route('pm-schedules.create'));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    ['ADMIN', true],
    ['KOORDINATOR WWD', true],
    ['PIC WWD', false],
]);

test('supervisor cannot reach the create form', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)->get(route('pm-schedules.create'))->assertForbidden();
});

test('admin can create a pm schedule with plan date, and due date is auto-calculated', function () {
    $admin = User::factory()->create(roleAttributes('ADMIN'));
    $machine = pmCreateMachine();

    $response = $this->actingAs($admin)->post(route('pm-schedules.store'), [
        'machine_id' => $machine->id,
        'order_number' => 'WO-PM-1',
        'plan_month' => 'October',
        'plan_year' => 2026,
        'plan_date' => '2026-10-10',
    ]);

    $response->assertRedirect(route('pm-schedules.index'));

    $schedule = PMSchedule::where('order_number', 'WO-PM-1')->firstOrFail();
    expect($schedule->machine_number)->toBe($machine->machine_number)
        ->and($schedule->area)->toBe('WWD')
        ->and(substr($schedule->plan_date, 0, 10))->toBe('2026-10-10')
        ->and(substr($schedule->due_date, 0, 10))->toBe('2026-10-24')
        ->and($schedule->status)->toBe('OPEN')
        ->and($schedule->pic)->toBeNull();
});

test('manual create has no pic field, matching PMScheduleImport which never sets pic', function () {
    $admin = User::factory()->create(roleAttributes('ADMIN'));

    $this->actingAs($admin)->get(route('pm-schedules.create'))
        ->assertOk()
        ->assertDontSee('name="pic"', false);
});

test('missing plan date is rejected', function () {
    $admin = User::factory()->create(roleAttributes('ADMIN'));
    $machine = pmCreateMachine();

    $response = $this->actingAs($admin)->post(route('pm-schedules.store'), [
        'machine_id' => $machine->id,
        'order_number' => 'WO-PM-2',
        'plan_month' => 'October',
        'plan_year' => 2026,
    ]);

    $response->assertSessionHasErrors('plan_date');
    expect(PMSchedule::where('order_number', 'WO-PM-2')->exists())->toBeFalse();
});

test('duplicate order number is rejected', function () {
    $admin = User::factory()->create(roleAttributes('ADMIN'));
    $machine = pmCreateMachine();
    PMSchedule::create([
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type,
        'area' => $machine->area,
        'order_number' => 'WO-DUP',
        'plan_month' => 'October',
        'plan_year' => 2026,
        'plan_date' => '2026-10-01',
        'due_date' => '2026-10-15',
        'status' => 'OPEN',
    ]);

    $response = $this->actingAs($admin)->post(route('pm-schedules.store'), [
        'machine_id' => $machine->id,
        'order_number' => 'WO-DUP',
        'plan_month' => 'October',
        'plan_year' => 2026,
        'plan_date' => '2026-10-02',
    ]);

    $response->assertSessionHasErrors('order_number');
});

test('pic cannot post to store', function () {
    $pic = User::factory()->create(roleAttributes('PIC WWD'));
    $machine = pmCreateMachine();

    $this->actingAs($pic)->post(route('pm-schedules.store'), [
        'machine_id' => $machine->id,
        'order_number' => 'WO-PM-3',
        'plan_month' => 'October',
        'plan_year' => 2026,
        'plan_date' => '2026-10-10',
    ])->assertForbidden();

    expect(PMSchedule::where('order_number', 'WO-PM-3')->exists())->toBeFalse();
});
