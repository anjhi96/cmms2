<?php

use App\Models\Area;
use App\Models\Greasing;
use App\Models\Group;
use App\Models\User;

/**
 * Accepts the OLD-style combined role labels this file's tests are written
 * around, and translates each into the new base role + Area (role/area are
 * separate now — see App\Models\User / App\Models\Area).
 */
function assignPicUser(string $role): User
{
    [$baseRole, $areaName] = match ($role) {
        'ADMIN' => [User::ROLE_ADMIN, null],
        'GUEST' => [User::ROLE_GUEST, null],
        'KOORDINATOR WWD' => [User::ROLE_KOORDINATOR, 'WWD'],
        'KOORDINATOR BUL' => [User::ROLE_KOORDINATOR, 'BUL'],
        'PIC WWD' => [User::ROLE_PIC, 'WWD'],
        'PIC BUL' => [User::ROLE_PIC, 'BUL'],
    };

    $attributes = ['role' => $baseRole];

    if ($areaName !== null) {
        $attributes['area_id'] = Area::firstOrCreate(
            ['name' => $areaName],
            ['slug' => strtolower($areaName), 'is_active' => true]
        )->id;
    }

    return User::factory()->create($attributes);
}

/**
 * Groups previously had no area column — area was guessed from the name
 * (the old Group::inferredArea(), now removed). Area is now a real
 * Group::area() FK, so tests must set it explicitly.
 */
function groupInArea(string $name, ?string $areaName): Group
{
    $areaId = $areaName
        ? Area::firstOrCreate(['name' => $areaName], ['slug' => strtolower($areaName), 'is_active' => true])->id
        : null;

    return Group::create(['name' => $name, 'area_id' => $areaId]);
}

test('group area relation reflects the area it was assigned', function () {
    $wwdGroup = groupInArea('WWD 1', 'WWD');
    $bulGroup = groupInArea('Line 2', 'BUL');
    $unassignedGroup = groupInArea('Something Else', null);

    expect($wwdGroup->area?->name)->toBe('WWD')
        ->and($bulGroup->area?->name)->toBe('BUL')
        ->and($unassignedGroup->area)->toBeNull();
});

test('admin can assign a pic wwd user to a schedule in a wwd group', function () {
    $admin = assignPicUser('ADMIN');
    $group = groupInArea('WWD 1', 'WWD');
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-1',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $pic = assignPicUser('PIC WWD');

    $response = $this->actingAs($admin)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $pic->name,
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    expect($greasing->fresh()->pic)->toBe($pic->name);
});

test('a pic bul user cannot be assigned to a schedule in a wwd group', function () {
    $admin = assignPicUser('ADMIN');
    $group = groupInArea('WWD 1', 'WWD');
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-2',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $picBul = assignPicUser('PIC BUL');

    $response = $this->actingAs($admin)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $picBul->name,
    ]);

    $response->assertStatus(422);
    expect($greasing->fresh()->pic)->toBeNull();
});

test('a pic wwd user cannot be assigned to a schedule in a bul group', function () {
    $admin = assignPicUser('ADMIN');
    $group = groupInArea('BUL 1', 'BUL');
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-3',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $picWwd = assignPicUser('PIC WWD');

    $response = $this->actingAs($admin)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $picWwd->name,
    ]);

    $response->assertStatus(422);
    expect($greasing->fresh()->pic)->toBeNull();
});

test('selecting the blank option clears the assigned pic', function () {
    $admin = assignPicUser('ADMIN');
    $pic = assignPicUser('PIC WWD');
    $group = groupInArea('WWD 1', 'WWD');
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-4',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'pic' => $pic->name,
        'status' => 'OPEN',
    ]);

    $response = $this->actingAs($admin)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => '',
    ]);

    $response->assertOk();
    expect($greasing->fresh()->pic)->toBeNull();
});

test('assigning pic on a group with no area assigned is rejected', function () {
    $admin = assignPicUser('ADMIN');
    $group = groupInArea('Unrelated Name', null);
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-5',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $pic = assignPicUser('PIC WWD');

    $response = $this->actingAs($admin)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $pic->name,
    ]);

    $response->assertStatus(422);
});

test('koordinator can assign a pic on a schedule in their own area', function () {
    $koordinatorWwd = assignPicUser('KOORDINATOR WWD');
    $group = groupInArea('WWD 1', 'WWD');
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-6',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $pic = assignPicUser('PIC WWD');

    $this->actingAs($koordinatorWwd)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $pic->name,
    ])->assertOk();

    expect($greasing->fresh()->pic)->toBe($pic->name);
});

test('koordinator cannot assign a pic on a schedule outside their area', function () {
    $koordinatorBul = assignPicUser('KOORDINATOR BUL');
    $group = groupInArea('WWD 1', 'WWD');
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-6b',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $pic = assignPicUser('PIC WWD');

    $this->actingAs($koordinatorBul)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $pic->name,
    ])->assertForbidden();

    expect($greasing->fresh()->pic)->toBeNull();
});

test('pic and guest cannot call the assign-pic endpoint directly', function (string $role) {
    $group = groupInArea('WWD 1', 'WWD');
    $greasing = Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-7',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);
    $pic = assignPicUser('PIC WWD');
    $user = assignPicUser($role);

    $this->actingAs($user)->postJson(route('greasings.assign-pic', $greasing), [
        'pic' => $pic->name,
    ])->assertForbidden();

    expect($greasing->fresh()->pic)->toBeNull();
})->with(['PIC WWD', 'PIC BUL', 'GUEST']);

test('index only renders the pic dropdown for admin/koordinator, not for pic', function () {
    $admin = assignPicUser('ADMIN');
    $pic = assignPicUser('PIC WWD');
    $group = groupInArea('WWD 1', 'WWD');
    Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-8',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'pic' => $pic->name,
        'status' => 'OPEN',
    ]);

    // Note: the page always includes a small <script> that references the
    // ".assign-pic" selector, so we must assert on the rendered element's
    // opening tag specifically, not the bare "assign-pic" substring.
    $adminResponse = $this->actingAs($admin)->get(route('greasings.index'));
    $adminResponse->assertOk();
    $adminResponse->assertSee('class="assign-pic', false);

    $picResponse = $this->actingAs($pic)->get(route('greasings.index'));
    $picResponse->assertOk();
    $picResponse->assertDontSee('class="assign-pic', false);
});

test('index dropdown only lists pic users from the matching area', function () {
    $admin = assignPicUser('ADMIN');
    $wwdPic = assignPicUser('PIC WWD');
    $bulPic = assignPicUser('PIC BUL');
    $group = groupInArea('WWD 1', 'WWD');
    Greasing::create([
        'group_id' => $group->id,
        'order_number' => 'WO-9',
        'cycle' => '4W',
        'plan_date' => '2026-08-01',
        'due_date' => Greasing::calculateDueDate('2026-08-01'),
        'status' => 'OPEN',
    ]);

    $response = $this->actingAs($admin)->get(route('greasings.index'));

    $response->assertOk();
    $response->assertSee($wwdPic->name);
    $response->assertDontSee($bulPic->name);
});
