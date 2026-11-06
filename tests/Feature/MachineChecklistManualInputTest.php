<?php

use App\Models\MachineChecklist;
use App\Models\User;

function manualChecklistPayload(array $overrides = []): array
{
    return array_merge([
        'machine_type' => 'CRUSHER',
        'section' => 'CONE 1',
        'section_order' => 1,
        'checklist_item' => 'Check cone liner',
        'maintenance_type' => 'lubrication',
        'item_order' => 3,
    ], $overrides);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
});

test('create page renders with existing sections for prefill', function () {
    MachineChecklist::create(manualChecklistPayload(['item_order' => 4]));

    $response = $this->get(route('machine-checklists.create'));

    $response->assertOk();
    expect($response->viewData('sectionsByType')['CRUSHER']->first())->toBe([
        'section' => 'CONE 1',
        'section_order' => 1,
        'next_item_order' => 5,
    ]);
});

test('store saves a checklist item including maintenance type', function () {
    $this->post(route('machine-checklists.store'), manualChecklistPayload())
        ->assertRedirect(route('machine-checklists.index'));

    expect(MachineChecklist::sole()->only(['machine_type', 'section', 'checklist_item', 'maintenance_type', 'item_order']))
        ->toBe([
            'machine_type' => 'CRUSHER',
            'section' => 'CONE 1',
            'checklist_item' => 'Check cone liner',
            'maintenance_type' => 'lubrication',
            'item_order' => 3,
        ]);
});

test('save and add another returns to the form with type and section kept', function () {
    $this->post(route('machine-checklists.store'), manualChecklistPayload(['add_another' => 1]))
        ->assertRedirect(route('machine-checklists.create', [
            'machine_type' => 'CRUSHER',
            'section' => 'CONE 1',
            'maintenance_type' => 'lubrication',
        ]));
});

test('store rejects a duplicate item in the same section', function () {
    MachineChecklist::create(manualChecklistPayload());

    $this->post(route('machine-checklists.store'), manualChecklistPayload(['section' => ' CONE 1 ']))
        ->assertSessionHas('error');

    expect(MachineChecklist::count())->toBe(1);
});

test('store rejects an unknown maintenance type', function () {
    $this->post(route('machine-checklists.store'), manualChecklistPayload(['maintenance_type' => 'paint']))
        ->assertSessionHasErrors('maintenance_type');
});

test('supervisor cannot open the create page or store', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPERVISOR]));

    $this->get(route('machine-checklists.create'))->assertForbidden();
    $this->post(route('machine-checklists.store'), manualChecklistPayload())->assertForbidden();
});
