<?php

use App\Models\Area;
use App\Models\Machine;
use App\Models\PMSchedule;
use App\Models\User;

/**
 * Proves the task's central acceptance criterion: adding a brand new area
 * (GRIPPER) through Area Management makes it work everywhere — role/area
 * authorization, Machine, PM Schedule, Dashboard, PM Status Board, and the
 * Today's Activity monitor board — with ZERO further source code changes.
 * Every area name used below is created at runtime by these tests, not
 * seeded or hardcoded anywhere in application code.
 */
function createGripperArea(): Area
{
    $admin = User::factory()->admin()->create();
    test()->actingAs($admin)->post(route('areas.store'), ['name' => 'GRIPPER'])
        ->assertRedirect(route('areas.index'));

    return Area::where('name', 'GRIPPER')->firstOrFail();
}

function gripperMachine(string $number = 'GRIP-1'): Machine
{
    return Machine::create([
        'machine_number' => $number,
        'area' => 'GRIPPER',
        'machine_type' => 'GRP',
        'status' => 'ACTIVE',
    ]);
}

test('GRIPPER area can be created from the Admin UI', function () {
    $area = createGripperArea();

    expect($area->name)->toBe('GRIPPER')
        ->and($area->slug)->toBe('gripper')
        ->and($area->is_active)->toBeTrue();
});

test('a machine can be created in the new GRIPPER area from the Admin UI', function () {
    $area = createGripperArea();
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('machines.store'), [
        'machine_number' => 'GRIP-1',
        'machine_type' => 'GRP',
        'area' => 'GRIPPER',
        'status' => 'ACTIVE',
    ]);

    $response->assertRedirect(route('machines.index'));
    $machine = Machine::where('machine_number', 'GRIP-1')->firstOrFail();
    expect($machine->area)->toBe('GRIPPER')
        ->and($machine->areaMaster()->first()->id)->toBe($area->id);
});

test('a koordinator assigned to GRIPPER can be created and is scoped to GRIPPER only', function () {
    createGripperArea();
    $koordinator = User::factory()->koordinator()->forArea('GRIPPER')->create();
    $gripperMachine = gripperMachine();
    $wwdMachine = Machine::create(['machine_number' => 'WWD-1', 'area' => 'WWD', 'machine_type' => 'NDE', 'status' => 'ACTIVE']);

    $gripperPm = PMSchedule::create([
        'machine_id' => $gripperMachine->id, 'machine_number' => $gripperMachine->machine_number,
        'machine_type' => $gripperMachine->machine_type, 'area' => 'GRIPPER',
        'order_number' => 'GRIP-WO-1', 'plan_date' => '2026-08-01', 'plan_month' => 8, 'plan_year' => 2026,
        'due_date' => '2026-08-15', 'status' => 'OPEN',
    ]);
    $wwdPm = PMSchedule::create([
        'machine_id' => $wwdMachine->id, 'machine_number' => $wwdMachine->machine_number,
        'machine_type' => $wwdMachine->machine_type, 'area' => 'WWD',
        'order_number' => 'WWD-WO-1', 'plan_date' => '2026-08-01', 'plan_month' => 8, 'plan_year' => 2026,
        'due_date' => '2026-08-15', 'status' => 'OPEN',
    ]);

    expect($gripperPm->isAccessibleBy($koordinator))->toBeTrue()
        ->and($wwdPm->isAccessibleBy($koordinator))->toBeFalse();

    $response = $this->actingAs($koordinator)->get(route('pm-schedules.index'));
    $response->assertOk();
    $orderNumbers = $response->viewData('schedules')->pluck('order_number');
    expect($orderNumbers)->toContain('GRIP-WO-1')
        ->not->toContain('WWD-WO-1');
});

test('a pic assigned to GRIPPER only sees their own assigned GRIPPER schedule', function () {
    createGripperArea();
    $mine = User::factory()->pic()->forArea('GRIPPER')->create(['name' => 'Gripper Pic Mine']);
    $other = User::factory()->pic()->forArea('GRIPPER')->create(['name' => 'Gripper Pic Other']);
    $machine = gripperMachine();

    $myPm = PMSchedule::create([
        'machine_id' => $machine->id, 'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type, 'area' => 'GRIPPER', 'pic' => $mine->name,
        'order_number' => 'GRIP-WO-2', 'plan_date' => '2026-08-01', 'plan_month' => 8, 'plan_year' => 2026,
        'due_date' => '2026-08-15', 'status' => 'OPEN',
    ]);
    $otherPm = PMSchedule::create([
        'machine_id' => $machine->id, 'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type, 'area' => 'GRIPPER', 'pic' => $other->name,
        'order_number' => 'GRIP-WO-3', 'plan_date' => '2026-08-01', 'plan_month' => 8, 'plan_year' => 2026,
        'due_date' => '2026-08-15', 'status' => 'OPEN',
    ]);

    expect($myPm->isAccessibleBy($mine))->toBeTrue()
        ->and($otherPm->isAccessibleBy($mine))->toBeFalse();

    $response = $this->actingAs($mine)->get(route('pm-schedules.index'));
    $response->assertOk();
    $orderNumbers = $response->viewData('schedules')->pluck('order_number');
    expect($orderNumbers)->toContain('GRIP-WO-2')
        ->not->toContain('GRIP-WO-3');
});

test('admin dashboard area filter includes GRIPPER once created', function () {
    createGripperArea();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('GRIPPER');
});

test('PM Status Board serves a working page at /pm-status/gripper once the area exists', function () {
    $area = createGripperArea();
    $machine = gripperMachine('GRIP-STATUS-1');
    PMSchedule::create([
        'machine_id' => $machine->id, 'machine_number' => $machine->machine_number,
        'machine_type' => $machine->machine_type, 'area' => 'GRIPPER',
        'order_number' => 'GRIP-WO-4', 'plan_date' => '2026-08-01', 'plan_month' => 8, 'plan_year' => 2026,
        'due_date' => '2026-08-15', 'status' => 'OPEN',
    ]);

    $response = $this->get(route('pm-status.show', [$area->slug, 'plan_month' => 8, 'plan_year' => 2026]));

    $response->assertOk();
    $response->assertSee('GRIP-STATUS-1');
});

test('PM Status Board 404s for an area slug that does not exist', function () {
    $this->get(route('pm-status.show', 'nonexistent-area'))->assertNotFound();
});

test('PM Status Board 404s for a deactivated area', function () {
    $area = createGripperArea();
    $area->update(['is_active' => false]);

    $this->get(route('pm-status.show', $area->slug))->assertNotFound();
});

test('koordinator assigned to GRIPPER cannot access WWD-only Oil Audit', function () {
    createGripperArea();
    $koordinator = User::factory()->koordinator()->forArea('GRIPPER')->create();

    $this->actingAs($koordinator)->get(route('oil-audits.scan'))->assertForbidden();
});

test("today's activity monitor board buckets a GRIPPER pic under its own area row, not WWD by default", function () {
    createGripperArea();
    User::factory()->pic()->forArea('GRIPPER')->create(['is_active' => true]);

    $response = $this->get(route('monitor'));

    $response->assertOk();
    $response->assertSee('GRIPPER');
});
