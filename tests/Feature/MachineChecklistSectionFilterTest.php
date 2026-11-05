<?php

use App\Models\MachineChecklist;
use App\Models\User;

function checklistRow(string $type, string $section, int $order, string $item): MachineChecklist
{
    return MachineChecklist::create([
        'machine_type' => $type,
        'section' => $section,
        'section_order' => $order,
        'checklist_item' => $item,
        'item_order' => 1,
        'maintenance_type' => 'check',
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    checklistRow('CRUSHER', 'CONE 1', 1, 'crusher cone item');
    checklistRow('CRUSHER', 'CONE 1', 1, 'crusher cone item 2');
    checklistRow('CRUSHER', 'MOTOR', 2, 'crusher motor item');
    checklistRow('MILL', 'MOTOR', 1, 'mill motor item');
    checklistRow('MILL', 'GEARBOX', 2, 'mill gearbox item');
});

test('all machine types shows every distinct section', function () {
    $response = $this->get(route('machine-checklists.index'));

    $response->assertOk();
    expect($response->viewData('sections')->all())->toEqual(['CONE 1', 'MOTOR', 'GEARBOX']);
});

test('selected machine type limits the section list', function () {
    $response = $this->get(route('machine-checklists.index', ['machine_type' => 'CRUSHER']));

    expect($response->viewData('sections')->all())->toEqual(['CONE 1', 'MOTOR']);
});

test('machine type and section filters combine', function () {
    $response = $this->get(route('machine-checklists.index', ['machine_type' => 'MILL', 'section' => 'MOTOR']));

    expect($response->viewData('checklists')->pluck('checklist_item')->all())->toEqual(['mill motor item']);
});

test('a section from another machine type is ignored', function () {
    $response = $this->get(route('machine-checklists.index', ['machine_type' => 'MILL', 'section' => 'CONE 1']));

    expect($response->viewData('checklists')->total())->toBe(2);
});

test('maintenance type filter limits rows and combines with other filters', function () {
    MachineChecklist::create([
        'machine_type' => 'MILL', 'section' => 'MOTOR', 'section_order' => 1,
        'checklist_item' => 'mill lube item', 'item_order' => 2, 'maintenance_type' => 'lubrication',
    ]);
    MachineChecklist::create([
        'machine_type' => 'CRUSHER', 'section' => 'MOTOR', 'section_order' => 2,
        'checklist_item' => 'crusher lube item', 'item_order' => 2, 'maintenance_type' => 'lubrication',
    ]);

    $items = fn ($r) => $r->viewData('checklists')->pluck('checklist_item')->sort()->values()->all();

    expect($items($this->get(route('machine-checklists.index', ['maintenance_type' => 'lubrication']))))
        ->toEqual(['crusher lube item', 'mill lube item']);
    expect($items($this->get(route('machine-checklists.index', ['maintenance_type' => 'lubrication', 'machine_type' => 'MILL']))))
        ->toEqual(['mill lube item']);
    // unknown value is ignored rather than erroring / emptying the list
    expect($this->get(route('machine-checklists.index', ['maintenance_type' => 'bogus']))->viewData('checklists')->total())->toBe(7);
    $this->get(route('machine-checklists.index', ['maintenance_type' => 'lubrication']))->assertSee('All Maintenance Type');
});
