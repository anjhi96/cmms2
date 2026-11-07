<?php

require_once __DIR__.'/../Support/predictive_helpers.php';

use App\Models\PredictiveAction;
use App\Models\PredictiveReopen;
use App\Models\PredictiveWorkOrder;
use App\Models\User;

function pvEdit(array $overrides = []): array
{
    return array_merge([
        'order_number' => 'WO-EDITED',
        'machine_number' => 'M-EDIT',
        'wo_date' => '2026-11-02',
        'due_date' => '2026-11-20',
        'problem_description' => 'Deskripsi baru',
    ], $overrides);
}

test('admin and koordinator of the area can edit a WO', function (string $role) {
    $machine = pvMachine('M-EDIT');
    $wo = pvWo(['machine' => $machine]);

    $this->actingAs(pvUser($role))->put(route('predictive.update', $wo), pvEdit())->assertSessionHas('success');

    $wo->refresh();
    expect($wo->order_number)->toBe('WO-EDITED')
        ->and($wo->wo_date->toDateString())->toBe('2026-11-02')
        ->and($wo->due_date->toDateString())->toBe('2026-11-20')
        ->and($wo->problem_description)->toBe('Deskripsi baru')
        ->and($wo->category)->toBe('VIBRATION');
})->with(['ADMIN', 'KOORDINATOR WWD']);

test('keeping the same order number is not a duplicate, taking another WO number is', function () {
    $machine = pvMachine('M-EDIT');
    $wo = pvWo(['machine' => $machine, 'order_number' => 'KEEP-1']);
    pvWo(['order_number' => 'TAKEN-1']);
    $admin = pvUser('ADMIN');

    $this->actingAs($admin)->put(route('predictive.update', $wo), pvEdit(['order_number' => 'KEEP-1']))->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(route('predictive.update', $wo), pvEdit(['order_number' => 'TAKEN-1']))->assertSessionHasErrors('order_number');
    expect($wo->fresh()->order_number)->toBe('KEEP-1');
});

test('edit validates fields and due date order', function () {
    $wo = pvWo(['machine' => pvMachine('M-EDIT')]);

    $this->actingAs(pvUser('ADMIN'))->put(route('predictive.update', $wo), [])
        ->assertSessionHasErrors(['order_number', 'machine_number', 'wo_date', 'due_date', 'problem_description']);
    $this->actingAs(pvUser('ADMIN'))->put(route('predictive.update', $wo), pvEdit(['due_date' => '2026-10-01']))
        ->assertSessionHasErrors('due_date');
});

test('changing the machine of an untouched WO refreshes area and machine type from the master', function () {
    $wo = pvWo(['machine' => pvMachine('OLD-1', 'WWD', 'NDE')]);
    pvMachine('NEW-1', 'WWD', 'FORGED');

    $this->actingAs(pvUser('KOORDINATOR WWD'))->put(route('predictive.update', $wo), pvEdit(['machine_number' => 'NEW-1']));

    $wo->refresh();
    expect($wo->machine_number)->toBe('NEW-1')->and($wo->machine_type)->toBe('FORGED')->and($wo->machine->machine_number)->toBe('NEW-1');
});

test('the machine cannot change once the WO is claimed or has history', function () {
    $pic = pvUser('PIC WWD');
    $claimed = pvWo(['machine' => pvMachine('OLD-1'), 'pic_user_id' => $pic->id]);
    $closed = pvWo(['machine' => pvMachine('OLD-2'), 'status' => 'CLOSED']);
    PredictiveAction::create(['work_order_id' => $closed->id, 'action_number' => 1, 'pic_name' => 'x', 'actioned_at' => now()]);
    pvMachine('NEW-1');
    $admin = pvUser('ADMIN');

    $this->actingAs($admin)->put(route('predictive.update', $claimed), pvEdit(['machine_number' => 'NEW-1']))->assertSessionHasErrors('machine_number');
    $this->actingAs($admin)->put(route('predictive.update', $closed), pvEdit(['machine_number' => 'NEW-1']))->assertSessionHasErrors('machine_number');
    expect($claimed->fresh()->machine_number)->toBe('OLD-1')->and($closed->fresh()->machine_number)->toBe('OLD-2');

    // Other fields of the same WOs stay editable.
    $this->actingAs($admin)->put(route('predictive.update', $claimed), pvEdit(['machine_number' => 'OLD-1']))->assertSessionHasNoErrors();
    expect($claimed->fresh()->problem_description)->toBe('Deskripsi baru');
});

test('koordinator cannot move a WO to a machine outside their area or edit another area', function () {
    $own = pvWo(['machine' => pvMachine('W-1', 'WWD')]);
    pvMachine('B-9', 'BUL');
    $bul = pvWo(['machine' => pvMachine('B-1', 'BUL')]);
    $koordinator = pvUser('KOORDINATOR WWD');

    $this->actingAs($koordinator)->put(route('predictive.update', $own), pvEdit(['machine_number' => 'B-9']))->assertSessionHasErrors('machine_number');
    $this->actingAs($koordinator)->put(route('predictive.update', $bul), pvEdit(['machine_number' => 'B-1']))->assertForbidden();
    $this->actingAs($koordinator)->delete(route('predictive.destroy', $bul))->assertForbidden();
    expect($bul->fresh())->not->toBeNull();
});

test('PIC, supervisor and guest cannot edit or delete', function () {
    $wo = pvWo(['machine' => pvMachine('M-EDIT')]);
    $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

    foreach ([pvUser('PIC WWD'), $supervisor] as $user) {
        $this->actingAs($user)->put(route('predictive.update', $wo), pvEdit())->assertForbidden();
        $this->actingAs($user)->delete(route('predictive.destroy', $wo))->assertForbidden();
    }
    expect($wo->fresh()->order_number)->not->toBe('WO-EDITED')->and(PredictiveWorkOrder::count())->toBe(1);
});

test('delete removes the WO together with its actions and reopens', function () {
    $wo = pvWo(['status' => 'CLOSED']);
    $other = pvWo();
    PredictiveAction::create(['work_order_id' => $wo->id, 'action_number' => 1, 'pic_name' => 'x', 'actioned_at' => now()]);
    PredictiveReopen::create(['work_order_id' => $wo->id, 'user_name' => 'k', 'reopened_at' => now()]);

    $this->actingAs(pvUser('ADMIN'))->delete(route('predictive.destroy', $wo))
        ->assertRedirect(route('predictive.index', ['tab' => 'vibration']))->assertSessionHas('success');

    expect(PredictiveWorkOrder::pluck('id')->all())->toBe([$other->id])
        ->and(PredictiveAction::count())->toBe(0)->and(PredictiveReopen::count())->toBe(0);
});

test('a deleted WO no longer counts on the dashboard', function () {
    $wo = pvWo(['status' => 'CLOSED']);
    pvAddAction($wo, 1, 'Bearing aus', 'Getaran');
    $admin = pvUser('ADMIN');

    $this->actingAs($admin)->delete(route('predictive.destroy', $wo));

    $dash = pvDash($admin);
    expect($dash['overview']['total'])->toBe(0)->and($dash['findings'])->toBe([]);
});

test('edit and delete controls show only to admin and the koordinator of the area', function () {
    pvWo(['order_number' => 'WO-BTN', 'machine' => pvMachine('W-1', 'WWD')]);

    $this->actingAs(pvUser('KOORDINATOR WWD'))->get(route('predictive.index'))->assertSee('predictive/work-orders/', false)->assertSee('>Edit<', false)->assertSee('value="DELETE"', false);
    $this->actingAs(pvUser('ADMIN'))->get(route('predictive.index'))->assertSee('>Edit<', false);
    $this->actingAs(pvUser('KOORDINATOR BUL'))->get(route('predictive.index'))->assertDontSee('WO-BTN');
    $this->actingAs(pvUser('PIC WWD'))->get(route('predictive.index'))->assertSee('WO-BTN')->assertDontSee('>Edit<', false)->assertDontSee('value="DELETE"', false);
});

test('a failed edit returns to the page with the edit dialog set to reopen', function () {
    $wo = pvWo(['machine' => pvMachine('M-EDIT')]);
    pvWo(['order_number' => 'TAKEN-1']);

    $this->actingAs(pvUser('ADMIN'))
        ->from(route('predictive.show', $wo))
        ->followingRedirects()
        ->put(route('predictive.update', $wo), pvEdit(['order_number' => 'TAKEN-1', '_edit_wo' => $wo->id]))
        ->assertSee('edit: JSON.parse', false)
        ->assertSee('TAKEN-1');
});
