<?php

use App\Models\Machine;
use App\Models\PredictiveAction;
use App\Models\PredictiveFindingOption;
use App\Models\PredictiveProblemOption;
use App\Models\PredictiveWorkOrder;
use App\Models\User;
use App\Services\Predictive\PredictiveDashboardService;

// Shared builders for the Predictive feature tests.

function pvMachine(string $number = 'NDE-001', string $area = 'WWD', ?string $type = 'NDE'): Machine
{
    return Machine::create([
        'machine_number' => $number,
        'area' => $area,
        'machine_type' => $type,
        'status' => 'ACTIVE',
    ]);
}

/**
 * PICs are created as already started on Action Predictive (it gates Claim and
 * Action), so ordinary workflow tests need no setup. Pass `started => false` to
 * get a PIC who has not started it.
 */
function pvUser(string $role, bool $started = true): User
{
    $user = User::factory()->create(roleAttributes($role));

    if ($started && $user->isPic()) {
        $user->update(['predictive_started_at' => now()]);
    }

    return $user;
}

function pvWo(array $overrides = []): PredictiveWorkOrder
{
    $machine = $overrides['machine'] ?? pvMachine('M-'.uniqid());
    unset($overrides['machine']);

    return PredictiveWorkOrder::create(array_merge([
        'category' => PredictiveWorkOrder::CATEGORY_VIBRATION,
        'order_number' => 'WO-'.uniqid(),
        'machine_id' => $machine->id,
        'machine_number' => $machine->machine_number,
        'area' => $machine->area,
        'machine_type' => $machine->machine_type,
        'wo_date' => '2026-11-01',
        'problem_description' => 'Getaran tinggi',
        'due_date' => '2026-11-15',
        'status' => 'OPEN',
    ], $overrides));
}

function pvPayload(array $overrides = []): array
{
    return array_merge([
        'category' => 'VIBRATION',
        'order_number' => 'WO-1001',
        'machine_number' => 'NDE-001',
        'wo_date' => '2026-11-01',
        'problem_description' => 'Getaran tinggi pada motor',
        'due_date' => '2026-11-10',
    ], $overrides);
}

/**
 * The Problem / Finding master entry for a WO's own category and area, created on demand.
 *
 * @param  list<string>  $findings
 * @return array{problem: PredictiveProblemOption, findings: list<PredictiveFindingOption>}
 */
function pvOption(PredictiveWorkOrder $wo, string $problem, array $findings = ['Sisi motor']): array
{
    $option = PredictiveProblemOption::firstOrCreate(['category' => $wo->category, 'area' => $wo->area, 'name' => $problem]);

    return [
        'problem' => $option,
        'findings' => array_map(fn ($name) => $option->findings()->firstOrCreate(['name' => $name]), $findings),
    ];
}

/**
 * Posts the Action form. Pass `problems` as [['problem' => name, 'findings' => [['finding' => name]]]]
 * (master entries are created as needed), or the `finding` / `problem` shorthand for one of each.
 * Pass `raw` to post the body exactly as given (ids included).
 */
function pvClose($test, $pic, PredictiveWorkOrder $wo, array $data = [])
{
    if (isset($data['raw'])) {
        return $test->actingAs($pic)->post(route('predictive.action', $wo), $data['raw']);
    }

    $data = array_merge([
        'finding' => 'Bearing aus',
        'problem' => 'Getaran berlebih',
        'remarks' => 'Jadwalkan ganti bearing',
    ], $data);

    $named = $data['problems'] ?? [['problem' => $data['problem'], 'findings' => [['finding' => $data['finding']]]]];

    $body = ['remarks' => $data['remarks'], 'problems' => []];
    foreach ($named as $row) {
        $option = pvOption($wo, $row['problem'], array_map(fn ($f) => $f['finding'], $row['findings'] ?? []));
        $body['problems'][] = [
            'problem_id' => $option['problem']->id,
            'findings' => array_map(fn ($f) => ['finding_id' => $f->id], $option['findings']),
        ];
    }

    return $test->actingAs($pic)->post(route('predictive.action', $wo), $body);
}

function pvDash(User $user, array $filters = []): array
{
    $service = app(PredictiveDashboardService::class);
    $query = $service->workOrders($user, $filters);

    return [
        'overview' => $service->overview($query),
        'findings' => $service->findingSummary($query)->pluck('total', 'label')->all(),
        'problems' => $service->problemSummary($query)->pluck('total', 'label')->all(),
    ];
}

function pvAddAction(PredictiveWorkOrder $wo, int $n, string $finding, string $problem, ?User $pic = null): PredictiveAction
{
    $action = PredictiveAction::create([
        'work_order_id' => $wo->id,
        'action_number' => $n,
        'pic_user_id' => $pic?->id,
        'pic_name' => $pic?->name ?? 'PIC',
        'actioned_at' => now(),
    ]);

    $action->problems()->create(['problem' => $problem])->findings()->create(['finding' => $finding]);

    return $action;
}

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
