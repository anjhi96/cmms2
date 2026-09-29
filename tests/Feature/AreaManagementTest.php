<?php

use App\Models\Area;
use App\Models\User;

test('admin can list areas', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('areas.index'))
        ->assertOk()
        ->assertSee('WWD')
        ->assertSee('BUL');
});

test('admin can create a new area', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('areas.store'), [
        'name' => 'GRIPPER',
    ]);

    $response->assertRedirect(route('areas.index'));
    $area = Area::where('name', 'GRIPPER')->first();
    expect($area)->not->toBeNull()
        ->and($area->slug)->toBe('gripper')
        ->and($area->is_active)->toBeTrue();
});

test('area name must be unique', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('areas.store'), [
        'name' => 'WWD',
    ]);

    $response->assertSessionHasErrors('name');
});

test('admin can deactivate and reactivate an area', function () {
    $admin = User::factory()->admin()->create();
    $area = Area::factory()->create(['name' => 'PACKING']);

    $this->actingAs($admin)->put(route('areas.update', $area), ['is_active' => false])
        ->assertRedirect(route('areas.index'));
    expect($area->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->put(route('areas.update', $area), ['is_active' => true])
        ->assertRedirect(route('areas.index'));
    expect($area->fresh()->is_active)->toBeTrue();
});

test('area name cannot be changed via update — the edit form does not expose it', function () {
    $admin = User::factory()->admin()->create();
    $area = Area::factory()->create(['name' => 'PACKING']);

    $this->actingAs($admin)->put(route('areas.update', $area), [
        'name' => 'RENAMED',
        'is_active' => true,
    ]);

    expect($area->fresh()->name)->toBe('PACKING');
});

test('non-admin roles cannot access area management', function (string $role) {
    $user = User::factory()->create(roleAttributes($role));

    $this->actingAs($user)->get(route('areas.index'))->assertForbidden();
    $this->actingAs($user)->post(route('areas.store'), ['name' => 'X'])->assertForbidden();
})->with(['KOORDINATOR WWD', 'PIC WWD', 'GUEST']);

test('a newly created area is immediately usable in Machine and User forms without any code change', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('areas.store'), ['name' => 'GRIPPER']);

    $this->actingAs($admin)->get(route('machines.create'))->assertSee('GRIPPER');
    $this->actingAs($admin)->get(route('users.create'))->assertSee('GRIPPER');
});
