<?php

use App\Models\Area;
use App\Models\Machine;
use App\Models\SmallPmRow;
use App\Models\SmallPmSchedule;
use App\Models\User;
use App\Services\ActiveActivityResolver;
use App\Services\SmallPm\SmallPmScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

const SPM_NOW = '2026-11-11 09:00:00'; // Wednesday, week of Monday 2026-11-09

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(SPM_NOW, 'Asia/Jakarta'));
});

function spmArea(string $name = 'WWD'): Area
{
    return Area::firstOrCreate(['name' => $name], ['slug' => strtolower($name), 'is_active' => true]);
}

function spmUser(string $role, ?string $area = 'WWD', array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'role' => $role,
        'area_id' => $area ? spmArea($area)->id : null,
        'is_active' => true,
    ], $attrs));
}

function spmRow(int $order, array $machineNumbers = [], string $area = 'WWD'): SmallPmRow
{
    $row = SmallPmRow::create(['name' => sprintf('Row %02d', $order), 'area_id' => spmArea($area)->id, 'sort_order' => $order]);

    foreach ($machineNumbers ?: ["M{$order}A", "M{$order}B"] as $number) {
        $machine = Machine::firstOrCreate(
            ['machine_number' => $number],
            ['area' => $area, 'machine_type' => 'NDE SW', 'status' => 'ACTIVE']
        );
        // Not fillable on Machine (same as group_id): set it the way the row master does.
        Machine::whereKey($machine->id)->update(['small_pm_row_id' => $row->id]);
    }

    return $row;
}

function spmSchedule(SmallPmRow $row, array $attrs = []): SmallPmSchedule
{
    $plan = $attrs['plan_date'] ?? '2026-11-09';

    return SmallPmSchedule::create(array_merge([
        'small_pm_row_id' => $row->id,
        'task_type' => SmallPmRow::TASK_CLEANING_MOTOR,
        'cycle' => '26W',
        'plan_date' => $plan,
        'due_date' => SmallPmSchedule::calculateDueDate($plan)->toDateString(),
        'status' => 'OPEN',
    ], $attrs));
}

// --- Row master (like Group) ------------------------------------------------

test('a koordinator creates a row, then ticks machines on the edit page', function () {
    $koord = spmUser(User::ROLE_KOORDINATOR);
    $m1 = Machine::create(['machine_number' => 'A1', 'area' => 'WWD', 'machine_type' => 'NDE SW', 'status' => 'ACTIVE']);
    $m2 = Machine::create(['machine_number' => 'A2', 'area' => 'WWD', 'machine_type' => 'NDE SW', 'status' => 'ACTIVE']);

    $this->actingAs($koord)
        ->post('/small-pm-rows', ['name' => 'Row 01', 'area_id' => spmArea()->id, 'sort_order' => 1])
        ->assertRedirect();

    $row = SmallPmRow::sole();

    $this->actingAs($koord)->get("/small-pm-rows/{$row->id}/edit")->assertOk()->assertSee('A1')->assertSee('A2');

    $this->actingAs($koord)
        ->put("/small-pm-rows/{$row->id}", ['name' => 'Row 01', 'area_id' => spmArea()->id, 'sort_order' => 1, 'machine_ids' => [$m1->id, $m2->id]])
        ->assertRedirect(route('small-pm-rows.index'));

    expect($row->machines()->pluck('machine_number')->sort()->values()->all())->toBe(['A1', 'A2']);

    $this->actingAs($koord)->get('/small-pm-rows')->assertOk()->assertSee('Row 01');
    $this->actingAs($koord)->get("/small-pm-rows/{$row->id}")->assertOk()->assertSee('A1');
});

test('ticking a machine moves it out of its old row, unticking clears it', function () {
    $koord = spmUser(User::ROLE_KOORDINATOR);
    $r1 = spmRow(1, ['A1', 'A2']);
    $r2 = spmRow(2, ['B1']);
    $a1 = Machine::where('machine_number', 'A1')->first();

    $this->actingAs($koord)->put("/small-pm-rows/{$r2->id}", [
        'name' => 'Row 02', 'area_id' => spmArea()->id, 'sort_order' => 2, 'machine_ids' => [$a1->id],
    ]);

    expect($a1->fresh()->small_pm_row_id)->toBe($r2->id);
    expect(Machine::where('machine_number', 'B1')->value('small_pm_row_id'))->toBeNull();
    expect($r1->machines()->pluck('machine_number')->all())->toBe(['A2']);
});

test('the edit page only offers machines of the row area that are free or already in this row', function () {
    $koord = spmUser(User::ROLE_KOORDINATOR);
    spmRow(1, ['TAKEN']);
    $row = spmRow(2, ['MINE']);
    Machine::create(['machine_number' => 'FREE', 'area' => 'WWD', 'machine_type' => 'NDE SW', 'status' => 'ACTIVE']);
    Machine::create(['machine_number' => 'BULX', 'area' => 'BUL', 'machine_type' => 'NDE SW', 'status' => 'ACTIVE']);

    $this->actingAs($koord)->get("/small-pm-rows/{$row->id}/edit")
        ->assertSee('MINE')->assertSee('FREE')->assertDontSee('TAKEN')->assertDontSee('BULX');
});

test('a row with machines or schedules cannot be deleted', function () {
    $koord = spmUser(User::ROLE_KOORDINATOR);
    $withMachines = spmRow(1);
    $withSchedule = SmallPmRow::create(['name' => 'Kosong', 'area_id' => spmArea()->id, 'sort_order' => 5]);
    spmSchedule($withSchedule);
    $empty = SmallPmRow::create(['name' => 'Hapus', 'area_id' => spmArea()->id, 'sort_order' => 6]);

    $this->actingAs($koord)->delete("/small-pm-rows/{$withMachines->id}")->assertSessionHas('error');
    $this->actingAs($koord)->delete("/small-pm-rows/{$withSchedule->id}")->assertSessionHas('error');
    $this->actingAs($koord)->delete("/small-pm-rows/{$empty->id}")->assertSessionHas('success');

    expect(SmallPmRow::pluck('name')->sort()->values()->all())->toBe(['Kosong', 'Row 01']);
});

test('a PIC cannot open the row master, a supervisor can only view it', function () {
    $row = spmRow(1);

    $this->actingAs(spmUser(User::ROLE_PIC))->get('/small-pm-rows')->assertForbidden();

    $sup = spmUser(User::ROLE_SUPERVISOR, null);
    $this->actingAs($sup)->get('/small-pm-rows')->assertOk();
    $this->actingAs($sup)->get("/small-pm-rows/{$row->id}/edit")->assertForbidden();
});

// --- Schedules (like Greasing) ----------------------------------------------

test('a koordinator adds a schedule: due date is plan date + 14, status OPEN', function () {
    $koord = spmUser(User::ROLE_KOORDINATOR);
    $row = spmRow(1);

    $this->actingAs($koord)->post('/small-pm', [
        'tab' => 'cleaning-motor', 'small_pm_row_id' => $row->id, 'cycle' => '26w', 'plan_date' => '2026-11-12',
    ])->assertRedirect('/small-pm?tab=cleaning-motor');

    $s = SmallPmSchedule::sole();
    expect($s->due_date->toDateString())->toBe('2026-11-26');
    expect($s->cycle)->toBe('26W');
    expect($s->status)->toBe('OPEN');
    expect($s->task_type)->toBe(SmallPmRow::TASK_CLEANING_MOTOR);
});

test('a koordinator cannot plan a row of another area or assign a PIC of another area', function () {
    $koord = spmUser(User::ROLE_KOORDINATOR);
    $bulRow = spmRow(1, [], 'BUL');
    $row = spmRow(2);
    $bulPic = spmUser(User::ROLE_PIC, 'BUL');

    $this->actingAs($koord)->post('/small-pm', ['tab' => 'cleaning-motor', 'small_pm_row_id' => $bulRow->id, 'cycle' => '26W', 'plan_date' => '2026-11-12'])
        ->assertSessionHasErrors('small_pm_row_id');
    $this->actingAs($koord)->post('/small-pm', ['tab' => 'cleaning-motor', 'small_pm_row_id' => $row->id, 'cycle' => '26W', 'plan_date' => '2026-11-12', 'pic_user_id' => $bulPic->id])
        ->assertSessionHasErrors('pic_user_id');

    expect(SmallPmSchedule::count())->toBe(0);
});

test('import reads order_number, plan_date, row, cycle, pic, remarks like the greasing import', function () {
    $koord = spmUser(User::ROLE_KOORDINATOR);
    $pic = spmUser(User::ROLE_PIC, 'WWD', ['name' => 'Budi']);
    spmRow(1);
    spmRow(2);

    $csv = implode("\n", [
        'order_number,plan_date,row,cycle,pic,remarks',
        'WO-1,2026-11-09,Row 01,26W,Budi,catatan',
        ',2026-11-09,Row 02,26W,,',
        'WO-1,2026-11-09,Row 01,26W,,',      // duplicate
        'WO-3,2026-11-09,Row 99,26W,,',      // unknown row
        'WO-4,09/11/2026,Row 02,26W,,',      // bad date
    ]);

    $this->actingAs($koord)
        ->post('/small-pm/import', ['tab' => 'cleaning-motor', 'file' => UploadedFile::fake()->createWithContent('small-pm.csv', $csv)])
        ->assertSessionHas('small_pm_import_result', ['imported' => 2, 'duplicate' => 1, 'skipped' => 2]);

    $first = SmallPmSchedule::where('order_number', 'WO-1')->sole();
    expect($first->pic_user_id)->toBe($pic->id);
    expect($first->due_date->toDateString())->toBe('2026-11-23');
});

test('generate puts rows with machines into weeks, 4 a week, in row order', function () {
    $koord = spmUser(User::ROLE_KOORDINATOR);
    foreach (range(1, 9) as $i) {
        spmRow($i);
    }
    SmallPmRow::create(['name' => 'Tanpa mesin', 'area_id' => spmArea()->id, 'sort_order' => 0]);

    $this->actingAs($koord)
        ->post('/small-pm/generate', ['tab' => 'cleaning-motor', 'start_week' => '2026-11-12', 'cycle' => '26W'])
        ->assertSessionHas('success', '9 row dijadwalkan dalam 3 minggu.');

    $perWeek = SmallPmSchedule::orderBy('plan_date')->get()->groupBy(fn ($s) => $s->plan_date->toDateString())->map->count();
    expect($perWeek->all())->toBe(['2026-11-09' => 4, '2026-11-16' => 4, '2026-11-23' => 1]);

    $row5 = SmallPmRow::where('name', 'Row 05')->first();
    expect($row5->schedules()->value('plan_date')->toDateString())->toBe('2026-11-16');

    // Running it again finds nothing left to schedule.
    $this->actingAs($koord)
        ->post('/small-pm/generate', ['tab' => 'cleaning-motor', 'start_week' => '2026-11-12', 'cycle' => '26W'])
        ->assertSessionHas('error');
});

test('a 29-row area takes 8 weeks: 7 weeks of 4 and 1 week of 1', function () {
    foreach (range(1, 29) as $i) {
        spmRow($i);
    }

    $created = app(SmallPmScheduler::class)->generate(SmallPmRow::TASK_CLEANING_MOTOR, CarbonImmutable::parse('2026-11-09'), '26W');

    $counts = $created->groupBy(fn ($s) => $s->plan_date->toDateString())->map->count()->values()->all();
    expect($counts)->toBe([4, 4, 4, 4, 4, 4, 4, 1]);
});

test('a koordinator assigns a PIC from the dropdown (JSON, like greasing)', function () {
    $koord = spmUser(User::ROLE_KOORDINATOR);
    $pic = spmUser(User::ROLE_PIC);
    $s = spmSchedule(spmRow(1));

    $this->actingAs($koord)->postJson("/small-pm/{$s->id}/assign-pic", ['pic_user_id' => $pic->id])->assertOk()->assertJson(['success' => true]);
    expect($s->fresh()->pic_user_id)->toBe($pic->id);

    $this->actingAs($koord)->postJson("/small-pm/{$s->id}/assign-pic", ['pic_user_id' => ''])->assertOk();
    expect($s->fresh()->pic_user_id)->toBeNull();

    $bulPic = spmUser(User::ROLE_PIC, 'BUL');
    $this->actingAs($koord)->postJson("/small-pm/{$s->id}/assign-pic", ['pic_user_id' => $bulPic->id])->assertStatus(422);
});

test('a PIC sees unassigned schedules and their own, not those of another PIC', function () {
    $pic = spmUser(User::ROLE_PIC);
    $other = spmUser(User::ROLE_PIC);
    $open = spmSchedule(spmRow(1));
    $mine = spmSchedule(spmRow(2), ['pic_user_id' => $pic->id]);
    spmSchedule(spmRow(3), ['pic_user_id' => $other->id]);

    $this->actingAs($pic)->get('/small-pm')
        ->assertOk()
        ->assertViewHas('schedules', fn ($p) => collect($p->items())->pluck('id')->sort()->values()->all() === [$open->id, $mine->id]);
});

test('a PIC takes an unassigned schedule with START and it becomes their activity', function () {
    $pic = spmUser(User::ROLE_PIC);
    $s = spmSchedule(spmRow(1));

    $this->actingAs($pic)->post("/small-pm/{$s->id}/start", ['started_at' => '2026-11-11 09:00'])->assertSessionHas('success');

    $s->refresh();
    expect($s->pic_user_id)->toBe($pic->id);
    expect($s->start_time)->not->toBeNull();

    $activity = app(ActiveActivityResolver::class)->currentFor($pic);
    expect($activity->source)->toBe('SMALL_PM');
    expect($activity->locationLabel())->toBe('Row: Row 01');
    expect($activity->jobTypeKey)->toBe('cleaning');
});

test('a PIC cannot start a schedule assigned to another PIC', function () {
    $other = spmUser(User::ROLE_PIC);
    $s = spmSchedule(spmRow(1), ['pic_user_id' => $other->id]);

    $this->actingAs(spmUser(User::ROLE_PIC))->post("/small-pm/{$s->id}/start", ['started_at' => '2026-11-11 09:00'])->assertForbidden();
});

test('starting while another activity runs asks for END & START first', function () {
    $pic = spmUser(User::ROLE_PIC);
    $first = spmSchedule(spmRow(1));
    $second = spmSchedule(spmRow(2));

    $this->actingAs($pic)->post("/small-pm/{$first->id}/start", ['started_at' => '2026-11-11 08:00']);
    $this->actingAs($pic)->post("/small-pm/{$second->id}/start", ['started_at' => '2026-11-11 09:00'])->assertSessionHas('activity_conflict');
    expect($second->fresh()->start_time)->toBeNull();

    $this->actingAs($pic)->post("/small-pm/{$second->id}/start", ['started_at' => '2026-11-11 09:00', 'confirm_end_start' => 1])->assertSessionHas('success');
    expect(app(ActiveActivityResolver::class)->currentFor($pic)->recordId)->toBe($second->id);
});

test('execute sets the status from action date vs due date and plans the next cycle once', function () {
    $pic = spmUser(User::ROLE_PIC);
    $s = spmSchedule(spmRow(1), ['pic_user_id' => $pic->id, 'start_time' => now()]);

    $this->actingAs($pic)->get("/small-pm/{$s->id}/execute")->assertOk()->assertSee('M1A');

    $this->actingAs($pic)->post("/small-pm/{$s->id}/execute", ['action_date' => '2026-11-11', 'remarks' => 'bersih'])
        ->assertRedirect();

    expect($s->fresh()->status)->toBe('FINISH ON TIME');
    expect(app(ActiveActivityResolver::class)->currentFor($pic))->toBeNull();

    // 11 Nov 2026 + 26 weeks = Wed 12 May 2027 -> Monday 10 May.
    $next = SmallPmSchedule::where('generated_from_id', $s->id)->sole();
    expect($next->plan_date->toDateString())->toBe('2027-05-10');
    expect($next->due_date->toDateString())->toBe('2027-05-24');
    expect($next->status)->toBe('OPEN');

    // Correcting the action date moves the untouched next schedule instead of adding another.
    $this->actingAs($pic)->post("/small-pm/{$s->id}/execute", ['action_date' => '2026-11-30']);
    expect($s->fresh()->status)->toBe('FINISH');
    expect(SmallPmSchedule::where('generated_from_id', $s->id)->count())->toBe(1);
    expect($next->fresh()->plan_date->toDateString())->toBe('2027-05-31');
});

test('a supervisor can list but not execute or plan', function () {
    $sup = spmUser(User::ROLE_SUPERVISOR, null);
    $s = spmSchedule(spmRow(1));

    $this->actingAs($sup)->get('/small-pm')->assertOk()->assertSee('Row 01');
    $this->actingAs($sup)->get("/small-pm/{$s->id}/execute")->assertForbidden();
    $this->actingAs($sup)->get('/small-pm/create')->assertForbidden();
});

test('the filter panel tab shows not available', function () {
    $this->actingAs(spmUser(User::ROLE_KOORDINATOR))->get('/small-pm?tab=filter-panel')
        ->assertOk()->assertSee('belum tersedia');
});
