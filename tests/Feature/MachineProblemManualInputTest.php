<?php

use App\Models\MachineProblem;
use App\Models\MachineProblemFinding;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
});

test('problem category create page lists existing categories', function () {
    MachineProblem::create(['machine_type' => 'CRUSHER', 'category' => 'BEARING', 'problem' => 'Bearing noise']);

    $response = $this->get(route('machine-problems.create'));

    $response->assertOk();
    expect($response->viewData('categories')->all())->toBe(['BEARING']);
});

test('problem store saves machine type, category and trimmed problem', function () {
    $this->post(route('machine-problems.store'), [
        'machine_type' => 'CRUSHER',
        'category' => ' BEARING ',
        'problem' => ' Bearing noise ',
    ])->assertRedirect(route('machine-problems.index'));

    expect(MachineProblem::sole()->only(['machine_type', 'category', 'problem']))->toBe([
        'machine_type' => 'CRUSHER',
        'category' => 'BEARING',
        'problem' => 'Bearing noise',
    ]);
});

test('problem store requires a category', function () {
    $this->post(route('machine-problems.store'), [
        'machine_type' => 'CRUSHER',
        'problem' => 'Bearing noise',
    ])->assertSessionHasErrors('category');

    expect(MachineProblem::count())->toBe(0);
});

test('problem store rejects a duplicate for the same machine type', function () {
    MachineProblem::create(['machine_type' => 'CRUSHER', 'category' => 'BEARING', 'problem' => 'Bearing noise']);

    $this->post(route('machine-problems.store'), [
        'machine_type' => 'CRUSHER',
        'category' => 'BEARING',
        'problem' => 'Bearing noise',
    ])->assertSessionHas('error');

    expect(MachineProblem::count())->toBe(1);
});

test('problem save and add another keeps machine type and category', function () {
    $this->post(route('machine-problems.store'), [
        'machine_type' => 'CRUSHER',
        'category' => 'BEARING',
        'problem' => 'Bearing noise',
        'add_another' => 1,
    ])->assertRedirect(route('machine-problems.create', ['machine_type' => 'CRUSHER', 'category' => 'BEARING']));
});

test('problem update saves the category', function () {
    $problem = MachineProblem::create(['machine_type' => 'CRUSHER', 'category' => 'BEARING', 'problem' => 'Bearing noise']);

    $this->put(route('machine-problems.update', $problem), [
        'machine_type' => 'CRUSHER',
        'category' => 'MOTOR',
        'problem' => 'Bearing noise',
    ])->assertRedirect(route('machine-problems.index'));

    expect($problem->fresh()->category)->toBe('MOTOR');
});

test('finding create page renders with categories', function () {
    MachineProblem::create(['machine_type' => 'CRUSHER', 'category' => 'BEARING', 'problem' => 'Bearing noise']);

    $response = $this->get(route('machine-problem-findings.create'));

    $response->assertOk();
    expect($response->viewData('categories')->all())->toBe(['BEARING']);
});

test('finding store saves a trimmed finding', function () {
    $this->post(route('machine-problem-findings.store'), [
        'category' => 'BEARING',
        'finding' => ' Worn outer race ',
    ])->assertRedirect(route('machine-problem-findings.index'));

    expect(MachineProblemFinding::sole()->only(['category', 'finding']))->toBe([
        'category' => 'BEARING',
        'finding' => 'Worn outer race',
    ]);
});

test('finding store rejects a duplicate in the same category', function () {
    MachineProblemFinding::create(['category' => 'BEARING', 'finding' => 'Worn outer race']);

    $this->post(route('machine-problem-findings.store'), [
        'category' => 'BEARING',
        'finding' => 'Worn outer race',
    ])->assertSessionHasErrors('finding');

    expect(MachineProblemFinding::count())->toBe(1);
});

test('finding save and add another keeps the category', function () {
    $this->post(route('machine-problem-findings.store'), [
        'category' => 'BEARING',
        'finding' => 'Worn outer race',
        'add_another' => 1,
    ])->assertRedirect(route('machine-problem-findings.create', ['category' => 'BEARING']));
});

test('supervisor cannot open either create page', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPERVISOR]));

    $this->get(route('machine-problems.create'))->assertForbidden();
    $this->get(route('machine-problem-findings.create'))->assertForbidden();
});
