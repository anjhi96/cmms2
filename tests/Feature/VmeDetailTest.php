<?php

use App\Models\ActivityMonitorClosure;
use App\Models\Area;
use App\Models\Greasing;
use App\Models\Group;
use App\Models\Machine;
use App\Models\MandatorySparepart;
use App\Models\ManualActivity;
use App\Models\PicAvailability;
use App\Models\PMSchedule;
use App\Models\PMSparepart;
use App\Models\Sparepart;
use App\Models\User;
use App\Services\Vme\VmeFeature;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['vme.snapshot_ttl' => 0, 'vme.kill_switch' => false]);
});

function vdOn(array $on = VmeFeature::KEYS): void
{
    foreach (VmeFeature::KEYS as $key) {
        VmeFeature::set($key, in_array($key, $on, true));
    }
}

function vdAdmin(): User
{
    return User::factory()->admin()->create(['is_active' => true]);
}

function vdPic(string $name, string $role = 'PIC WWD'): User
{
    return User::factory()->create([...roleAttributes($role), 'name' => $name, 'is_active' => true]);
}

function vdMachine(string $number, string $area = 'WWD', array $extra = []): Machine
{
    Area::firstOrCreate(['name' => $area], ['slug' => strtolower($area), 'is_active' => true]);

    return Machine::create(['machine_number' => $number, 'area' => $area, 'machine_type' => 'NDE', 'status' => 'ACTIVE', ...$extra]);
}

function vdPm(string $picName, Machine $machine, array $over = []): PMSchedule
{
    $today = now()->toDateString();

    return PMSchedule::create([
        'machine_id' => $machine->id, 'machine_number' => $machine->machine_number, 'machine_type' => $machine->machine_type,
        'area' => $machine->area, 'order_number' => 'ORD-'.uniqid(), 'plan_date' => $today, 'plan_month' => 'Oktober',
        'plan_year' => now()->format('Y'), 'due_date' => now()->addDays(14)->toDateString(), 'pic' => $picName,
        'status' => 'IN_PROGRESS', 'start_time' => '08:00', 'actual_date' => $today, ...$over,
    ]);
}

// ── Access ────────────────────────────────────────────────────────────────

test('guests are sent to login and PIC / GUEST roles are refused', function () {
    vdOn();
    $pic = vdPic('TARGET');
    $machine = vdMachine('M-1');

    $this->getJson(route('vme.pic', $pic))->assertRedirect(route('login'));
    $this->getJson(route('vme.machine', $machine))->assertRedirect(route('login'));

    foreach (['PIC WWD', 'GUEST'] as $role) {
        $u = User::factory()->create([...roleAttributes($role), 'is_active' => true]);
        $this->actingAs($u)->getJson(route('vme.pic', $pic))->assertForbidden();
        $this->actingAs($u)->getJson(route('vme.machine', $machine))->assertForbidden();
    }
});

test('viewers only get PICs and machines of their own area', function () {
    vdOn();
    $wwd = vdPic('WWD PIC', 'PIC WWD');
    $bul = vdPic('BUL PIC', 'PIC BUL');
    $mWwd = vdMachine('W-1', 'WWD');
    $mBul = vdMachine('B-1', 'BUL');
    $koordinator = User::factory()->create([...roleAttributes('KOORDINATOR WWD'), 'is_active' => true]);

    $this->actingAs($koordinator)->getJson(route('vme.pic', $wwd))->assertOk();
    $this->actingAs($koordinator)->getJson(route('vme.machine', $mWwd))->assertOk();
    $this->actingAs($koordinator)->getJson(route('vme.pic', $bul))->assertForbidden();
    $this->actingAs($koordinator)->getJson(route('vme.machine', $mBul))->assertForbidden();

    // Supervisor: view-only, narrowed to assigned areas when any.
    $supervisor = User::factory()->supervisor()->create(['is_active' => true]);
    $this->actingAs($supervisor)->getJson(route('vme.pic', $bul))->assertOk();
    $supervisor->areas()->attach(Area::where('name', 'WWD')->value('id'));
    $supervisor = $supervisor->fresh(); // the relation was cached by the first request
    $this->actingAs($supervisor)->getJson(route('vme.pic', $bul))->assertForbidden();
    $this->actingAs($supervisor)->getJson(route('vme.machine', $mWwd))->assertOk();

    $this->actingAs(vdAdmin())->getJson(route('vme.pic', $bul))->assertOk();
});

test('detail endpoints are 404 while VME is off or killed, and unknown ids are 404', function () {
    $pic = vdPic('X');
    $machine = vdMachine('M-1');
    $admin = vdAdmin();

    $this->actingAs($admin)->getJson(route('vme.pic', $pic))->assertNotFound();
    $this->actingAs($admin)->getJson(route('vme.machine', $machine))->assertNotFound();

    vdOn();
    $this->actingAs($admin)->getJson(route('vme.machine', 99999))->assertNotFound();
    $this->actingAs($admin)->getJson(route('vme.pic', $admin))->assertNotFound(); // not a PIC
    $inactive = vdPic('NONAKTIF');
    $inactive->update(['is_active' => false]);
    $this->actingAs($admin)->getJson(route('vme.pic', $inactive))->assertNotFound();

    config(['vme.kill_switch' => true]);
    $this->actingAs($admin)->getJson(route('vme.pic', $pic))->assertNotFound();
});

// ── PIC detail ────────────────────────────────────────────────────────────

test('a PIC working on a PM shows the real PM record and a link to it', function () {
    vdOn();
    $pic = vdPic('ANDI PM');
    $machine = vdMachine('10001');
    $pm = vdPm($pic->name, $machine, ['order_number' => 'ORD-7788']);

    $json = $this->actingAs(vdAdmin())->getJson(route('vme.pic', $pic))->assertOk()->json();

    expect($json['pic'])->toMatchArray(['id' => $pic->id, 'name' => 'ANDI PM', 'area' => 'WWD', 'status' => 'WORKING'])
        ->and($json['activity'])->toMatchArray(['source' => 'PM', 'record_id' => $pm->id, 'job_type' => 'pm'])
        ->and($json['activity']['record'])->toMatchArray(['order_number' => 'ORD-7788', 'status' => 'IN_PROGRESS', 'machine_number' => '10001'])
        ->and($json['activity']['link']['url'])->toBe(route('machine-history.detail', ['10001', $pm->id]))
        ->and($json['activity']['started_at'])->not->toBeEmpty()
        ->and($json['today'])->toHaveCount(1)
        ->and($json['sync_enabled'])->toBeTrue();
});

test('Greasing, Manual and Oil Audit show their own details', function () {
    vdOn();
    $admin = vdAdmin();

    $grease = vdPic('GRESI');
    $group = Group::create(['name' => 'WWD 02', 'area_id' => $grease->area_id]);
    $g = Greasing::create([
        'group_id' => $group->id, 'order_number' => 'WO-9', 'cycle' => '4W', 'plan_date' => now()->toDateString(),
        'due_date' => Greasing::calculateDueDate(now()->toDateString()), 'pic' => $grease->name, 'status' => 'OPEN', 'start_time' => now()->setTime(9, 0),
    ]);
    $gj = $this->actingAs($admin)->getJson(route('vme.pic', $grease))->json('activity');
    expect($gj['source'])->toBe('GREASING')
        ->and($gj['record'])->toMatchArray(['order_number' => 'WO-9', 'cycle' => '4W', 'group' => 'WWD 02'])
        ->and($gj['link']['url'])->toBe(route('greasings.execute', $g->id));

    $manual = vdPic('MANUAL');
    ManualActivity::create(['user_id' => $manual->id, 'name' => 'ASSEMBLING', 'machine_number' => 'WORKSHOP', 'started_at' => now()]);
    $mj = $this->actingAs($admin)->getJson(route('vme.pic', $manual))->json('activity');
    expect($mj['source'])->toBe('MANUAL')->and($mj['record'])->toMatchArray(['name' => 'ASSEMBLING', 'machine_number' => 'WORKSHOP'])
        ->and($mj['link'])->toBeNull();

    $oil = vdPic('OLI');
    $oil->update(['oil_audit_started_at' => now()]);
    $oj = $this->actingAs($admin)->getJson(route('vme.pic', $oil))->json('activity');
    expect($oj['source'])->toBe('OIL_AUDIT')->and($oj['record'])->toBeNull()
        ->and($oj['link']['url'])->toBe(route('oil-audits.scan'));
});

test('the Greasing link is only offered to admin and koordinator (a supervisor is view-only)', function () {
    vdOn();
    $pic = vdPic('GRESI 2');
    $group = Group::create(['name' => 'G', 'area_id' => $pic->area_id]);
    Greasing::create([
        'group_id' => $group->id, 'order_number' => 'WO-1', 'cycle' => '4W', 'plan_date' => now()->toDateString(),
        'due_date' => Greasing::calculateDueDate(now()->toDateString()), 'pic' => $pic->name, 'status' => 'OPEN', 'start_time' => now()->setTime(9, 0),
    ]);
    $supervisor = User::factory()->supervisor()->create(['is_active' => true]);

    expect($this->actingAs($supervisor)->getJson(route('vme.pic', $pic))->json('activity.link'))->toBeNull();
});

test('closing from the monitor shows no current activity but says how it ended', function () {
    vdOn();
    $pic = vdPic('TUTUP');
    $pm = vdPm($pic->name, vdMachine('M-9'));
    ActivityMonitorClosure::create([
        'source' => 'PM', 'source_key' => (string) $pm->id, 'business_date' => today(),
        'pic_user_id' => $pic->id, 'closed_by_user_id' => $pic->id, 'closed_at' => now(),
    ]);

    $json = $this->actingAs(vdAdmin())->getJson(route('vme.pic', $pic))->json();

    expect($json['activity'])->toBeNull()
        ->and($json['pic']['status'])->toBe('IDLE')
        ->and($json['last_ended']['reason'])->toBe('CLOSED_BY_MONITOR')
        ->and($json['today'][0]['status'])->toBe('CLOSED_BY_MONITOR');
});

test('an inactive-today PIC shows the reason; activity sync OFF shows no activity at all', function () {
    vdOn();
    $away = vdPic('CUTI');
    $admin = vdAdmin();
    PicAvailability::create(['user_id' => $away->id, 'date' => today(), 'reason' => 'Cuti', 'set_by_user_id' => $admin->id]);

    $json = $this->actingAs($admin)->getJson(route('vme.pic', $away))->json('pic');
    expect($json['status'])->toBe('INACTIVE')->and($json['unavailable_reason'])->toBe('Cuti');

    vdOn(['display']); // sync OFF
    $busy = vdPic('SYNC OFF');
    vdPm($busy->name, vdMachine('M-2'));
    $off = $this->actingAs($admin)->getJson(route('vme.pic', $busy))->json();
    expect($off['sync_enabled'])->toBeFalse()->and($off['activity'])->toBeNull()->and($off['today'])->toBe([]);
});

test('PIC detail carries no personal data beyond name and area', function () {
    vdOn();
    $pic = vdPic('PRIVASI');

    $raw = $this->actingAs(vdAdmin())->getJson(route('vme.pic', $pic))->getContent();

    expect($raw)->not->toContain('@')->not->toContain('password')->not->toContain('email')->not->toContain('avatar');
});

// ── Machine detail ────────────────────────────────────────────────────────

test('machine detail shows the machine, its PM status, history and spareparts from real records', function () {
    vdOn();
    $group = null;
    $machine = vdMachine('10002', 'WWD', ['description' => 'Pompa utama', 'criticality' => 'A', 'pm_cycle_value' => 4, 'pm_cycle_unit' => 'week', 'install_date' => '2020-05-01']);
    $group = Group::create(['name' => 'WWD 01', 'area_id' => Area::where('name', 'WWD')->value('id')]);
    $machine->update(['group_id' => $group->id]);

    $finished = vdPm('BUDI', $machine, ['status' => 'FINISHED', 'actual_date' => '2026-09-01', 'start_time' => '08:00', 'end_time' => '10:00']);
    vdPm('BUDI', $machine, ['status' => 'MISSED', 'actual_date' => null, 'start_time' => null, 'plan_date' => '2026-08-01']);
    $running = vdPm('ANDI', $machine, ['status' => 'IN_PROGRESS']);
    $next = vdPm('CICI', $machine, ['status' => 'OPEN', 'actual_date' => null, 'start_time' => null, 'plan_date' => now()->addDays(10)->toDateString()]);

    $sp = Sparepart::create(['material_number' => 'MAT-1', 'description' => 'Bearing 6205', 'stock' => 12, 'unit' => 'PCS', 'machine_type' => 'NDE']);
    $sp2 = Sparepart::create(['material_number' => 'MAT-2', 'description' => 'Seal kit', 'stock' => 3, 'unit' => 'SET', 'machine_type' => 'NDE']);
    MandatorySparepart::create(['machine_type' => 'NDE', 'sparepart_id' => $sp->id, 'qty' => 2]);
    PMSparepart::create(['pm_schedule_id' => $finished->id, 'sparepart_id' => $sp2->id, 'qty' => 1]);
    PMSparepart::create(['pm_schedule_id' => $running->id, 'sparepart_id' => $sp2->id, 'qty' => 2]);

    $json = $this->actingAs(vdAdmin())->getJson(route('vme.machine', $machine))->assertOk()->json();

    expect($json['machine'])->toMatchArray([
        'number' => '10002', 'type' => 'NDE', 'description' => 'Pompa utama', 'group' => 'WWD 01', 'area' => 'WWD',
        'status' => 'ACTIVE', 'criticality' => 'A', 'install_date' => '2020-05-01', 'pm_cycle' => '4 week',
    ])
        ->and($json['machine']['link']['url'])->toBe(route('machine-history.show', '10002'))
        ->and($json['pm']['running']['id'])->toBe($running->id)
        ->and($json['pm']['next']['id'])->toBe($next->id)
        ->and($json['pm']['last']['id'])->toBe($finished->id)
        ->and($json['pm']['next_estimated'])->toBe('2026-09-29') // last finished 2026-09-01 + 4 weeks
        ->and($json['history'])->toMatchArray(['pm_total' => 4, 'pm_finished' => 1, 'pm_missed' => 1, 'pm_open' => 2])
        ->and($json['history']['recent'])->not->toBeEmpty()
        ->and($json['spareparts']['mandatory'])->toHaveCount(1)
        ->and($json['spareparts']['mandatory'][0])->toMatchArray(['material_number' => 'MAT-1', 'qty' => 2, 'unit' => 'PCS'])
        ->and($json['spareparts']['used'])->toHaveCount(1)
        ->and($json['spareparts']['used'][0])->toMatchArray(['material_number' => 'MAT-2', 'total_qty' => 3, 'pm_count' => 2]);
});

test('an OPEN PM already past its plan date is still the next PM, flagged as overdue', function () {
    vdOn();
    $machine = vdMachine('LATE-1');
    $late = vdPm('X', $machine, ['status' => 'OPEN', 'actual_date' => null, 'start_time' => null, 'plan_date' => now()->subDays(20)->toDateString()]);

    $json = $this->actingAs(vdAdmin())->getJson(route('vme.machine', $machine))->json('pm');

    expect($json['next']['id'])->toBe($late->id)->and($json['next']['overdue'])->toBeTrue()->and($json['running'])->toBeNull();

    $future = vdPm('X', $machine, ['status' => 'OPEN', 'actual_date' => null, 'start_time' => null, 'plan_date' => now()->addDays(5)->toDateString()]);
    $json = $this->actingAs(vdAdmin())->getJson(route('vme.machine', $machine))->json('pm');
    expect($json['next']['id'])->toBe($future->id)->and($json['next']['overdue'])->toBeFalse();
});

test('a machine with no history returns empty, not an error', function () {
    vdOn();
    $machine = vdMachine('NEW-1');

    $json = $this->actingAs(vdAdmin())->getJson(route('vme.machine', $machine))->assertOk()->json();

    expect($json['pm'])->toBe(['running' => null, 'next' => null, 'next_estimated' => null, 'last' => null])
        ->and($json['history']['pm_total'])->toBe(0)
        ->and($json['spareparts'])->toBe(['mandatory' => [], 'used' => []]);
});

test('machine detail works even with activity sync OFF (it reads the master data)', function () {
    vdOn(['display']);
    $machine = vdMachine('M-OFF');

    $this->actingAs(vdAdmin())->getJson(route('vme.machine', $machine))->assertOk()->assertJsonPath('machine.number', 'M-OFF');
});

// ── Read-only ─────────────────────────────────────────────────────────────

test('opening details never writes to operational tables', function () {
    vdOn();
    $pic = vdPic('BACA SAJA');
    $machine = vdMachine('M-RO');
    $pm = vdPm($pic->name, $machine);
    $admin = vdAdmin(); // created before measuring: only the two GETs are observed
    $before = [
        'pm' => PMSchedule::count(), 'updated' => PMSchedule::find($pm->id)->updated_at->toIso8601String(),
        'closures' => ActivityMonitorClosure::count(), 'users' => User::count(), 'manual' => ManualActivity::count(),
    ];
    DB::enableQueryLog();

    $this->actingAs($admin)->getJson(route('vme.pic', $pic))->assertOk();
    $this->actingAs($admin)->getJson(route('vme.machine', $machine))->assertOk();

    $writes = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => preg_match('/^\s*(insert|update|delete)/i', $q));
    expect($writes->all())->toBe([])
        ->and(['pm' => PMSchedule::count(), 'updated' => PMSchedule::find($pm->id)->updated_at->toIso8601String(),
            'closures' => ActivityMonitorClosure::count(), 'users' => User::count(), 'manual' => ManualActivity::count()])->toBe($before);

    // and the routes accept GET only
    $this->actingAs($admin)->postJson(route('vme.pic', $pic))->assertStatus(405);
    $this->actingAs($admin)->deleteJson(route('vme.machine', $machine))->assertStatus(405);
});
