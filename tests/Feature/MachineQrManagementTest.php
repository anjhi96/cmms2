<?php

use App\Models\Area;
use App\Models\Machine;
use App\Models\MachineLastPmOverride;
use App\Models\MachineLastPmOverrideLog;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\MachineQr\MachineQrDocx;
use App\Services\MachineQr\MachineQrRenderer;

function qrMachine(string $number, string $area = 'WWD', string $name = 'NDE'): Machine
{
    Area::firstOrCreate(['name' => $area], ['slug' => strtolower($area), 'is_active' => true]);

    return Machine::create([
        'machine_number' => $number,
        'area' => $area,
        'machine_type' => $name,
        'status' => 'ACTIVE',
        'pm_cycle_value' => 1,
        'pm_cycle_unit' => 'month',
    ]);
}

function qrPm(Machine $m, string $actual): PMSchedule
{
    return PMSchedule::create([
        'machine_id' => $m->id,
        'machine_number' => $m->machine_number,
        'machine_type' => $m->machine_type,
        'area' => $m->area,
        'order_number' => 'WO-'.uniqid(),
        'plan_date' => $actual,
        'plan_month' => 'January',
        'plan_year' => 2026,
        'due_date' => $actual,
        'actual_date' => $actual,
        'status' => 'FINISHED_ON_TIME',
    ]);
}

function qrUser(string $role, ?string $area = null): User
{
    $f = User::factory();
    $f = match ($role) {
        'admin' => $f->admin(), 'koor' => $f->koordinator(), 'pic' => $f->pic(),
        'sup' => $f->supervisor(), 'guest' => $f->guest(),
    };
    if ($area) {
        Area::firstOrCreate(['name' => $area], ['slug' => strtolower($area), 'is_active' => true]);
        $f = $f->forArea($area);
    }

    return $f->create();
}

/** Minimal zip reader (ext-zip is not installed everywhere). @return array<string,string> */
function qrReadZip(string $bytes): array
{
    $eocd = strrpos($bytes, "PK\x05\x06");
    $e = unpack('vdisk/vcd/ventriesDisk/ventries/Vsize/Voffset', substr($bytes, $eocd + 4, 18));
    $files = [];
    $p = $e['offset'];
    for ($i = 0; $i < $e['entries']; $i++) {
        $h = unpack('vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen/vclen', substr($bytes, $p + 4, 42));
        $name = substr($bytes, $p + 46, $h['nlen']);
        $off = unpack('V', substr($bytes, $p + 42, 4))[1];
        $l = unpack('vn/ve', substr($bytes, $off + 26, 4));
        $data = substr($bytes, $off + 30 + $l['n'] + $l['e'], $h['csize']);
        $files[$name] = $h['method'] === 8 ? gzinflate($data) : $data;
        $p += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
    }

    return $files;
}

/* ===================== QR generation ===================== */

test('qr points at the existing machine history route for the right machine', function () {
    $m = qrMachine('WWD-001');
    $url = MachineQrDocx::url($m);

    expect($url)->toEndWith('/m/WWD-001');
    // the target must really resolve to that machine's history page
    $this->get('/m/WWD-001')->assertOk()->assertSee('WWD-001');
});

test('qr label is centred, symbol is level H and the label stays inside the correction budget', function () {
    $r = new MachineQrRenderer;

    foreach (['A1', 'WWD-001', 'WWD-FIN-PRESS-0042', 'GRIPPER-LINE-2-MC-1234'] as $number) {
        $url = 'https://cmms.test/m/'.$number;
        $d = $r->raster($url, $number);
        $b = $d['box'];

        // centred within 1px
        expect(abs(($b['x0'] + $b['x1']) / 2 - $d['size'] / 2))->toBeLessThanOrEqual(1.0);
        expect(abs(($b['y0'] + $b['y1']) / 2 - $d['size'] / 2))->toBeLessThanOrEqual(1.0);

        // every module not touched by the label box is pixel-exact
        $s = $d['scale'];
        $hidden = 0;
        $total = $d['modules'] ** 2;
        for ($my = 0; $my < $d['modules']; $my++) {
            for ($mx = 0; $mx < $d['modules']; $mx++) {
                $x0 = $mx * $s;
                $y0 = $my * $s;
                $touches = $x0 < $b['x1'] && $x0 + $s > $b['x0'] && $y0 < $b['y1'] && $y0 + $s > $b['y0'];
                if ($touches) {
                    $hidden++;

                    continue;
                }
                $px = ord($d['pixels'][$y0 + intdiv($s, 2)][$x0 + intdiv($s, 2)]);
                expect($px === 0 ? 1 : 0)->toBe($d['matrix'][$my][$mx]);
            }
        }
        // level H repairs 30% of codewords; stay far below (<= 15% of modules)
        expect($hidden / $total)->toBeLessThan(0.15);

        // finder patterns (corners) are never touched
        expect($b['x0'])->toBeGreaterThan(8 * $s)->and($b['y0'])->toBeGreaterThan(8 * $s);
    }
});

test('qr png is exactly 2cm x 2cm via pHYs and a valid png', function () {
    $png = (new MachineQrRenderer)->png('https://cmms.test/m/WWD-001', 'WWD-001');

    expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    $ihdr = unpack('Nw/Nh', substr($png, 16, 8));
    expect($ihdr['w'])->toBe($ihdr['h']);
    $phys = strpos($png, 'pHYs');
    $p = unpack('Nx/Ny/Cunit', substr($png, $phys + 4, 9));
    expect($p['unit'])->toBe(1);
    // pixels / (pixels per metre) = 0.02 m
    expect(abs($ihdr['w'] / $p['x'] - 0.02))->toBeLessThan(0.0001);
});

test('individual preview and download return the 2x2cm png', function () {
    $m = qrMachine('WWD-001');
    $admin = qrUser('admin');

    $this->actingAs($admin)->get(route('machine-qr.preview', $m))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->actingAs($admin)->get(route('machine-qr.download', $m))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="QR-WWD-001.png"');
});

/* ===================== DOCX ===================== */

test('docx is a4 portrait, one label per machine, qr exactly 2x2cm', function () {
    $a = qrMachine('WWD-001', 'WWD', 'Press <A>');
    $b = qrMachine('WWD-002');
    $admin = qrUser('admin');

    $res = $this->actingAs($admin)->post(route('machine-qr.export'), ['mode' => 'selected', 'ids' => [$a->id, $b->id]]);
    $res->assertOk();
    $files = qrReadZip($res->getContent());

    expect($files)->toHaveKeys(['[Content_Types].xml', 'word/document.xml', 'word/_rels/document.xml.rels', 'word/media/qr1.png', 'word/media/qr2.png']);
    $xml = $files['word/document.xml'];
    expect(simplexml_load_string($xml))->not->toBeFalse();
    expect($xml)->toContain('w:w="11906" w:h="16838"');
    expect($xml)->not->toContain('SCAN FOR MACHINE HISTORY')->not->toContain('Press');
    expect($xml)->toContain('<w:gridCol w:w="1361"/>');
    expect(substr_count($xml, '<wp:extent cx="720000" cy="720000"/>'))->toBe(2);
    expect(substr_count($xml, '<a:ext cx="720000" cy="720000"/>'))->toBe(2);
    expect($xml)->toContain('noChangeAspect="1"');
    expect($files['word/media/qr1.png'])->toStartWith("\x89PNG");
});

test('export by area exports every machine of that area', function () {
    qrMachine('W-1', 'WWD');
    qrMachine('W-2', 'WWD');
    qrMachine('B-1', 'BUL');
    $admin = qrUser('admin');

    $res = $this->actingAs($admin)->post(route('machine-qr.export'), ['mode' => 'area', 'area' => 'WWD']);
    $files = qrReadZip($res->getContent());
    expect(substr_count($files['word/document.xml'], '<wp:inline'))->toBe(2);

    $this->actingAs($admin)->getJson(route('machine-qr.count', ['mode' => 'area', 'area' => 'BUL']))->assertJson(['count' => 1]);
});

test('koordinator export never includes machines from another area', function () {
    $own = qrMachine('W-1', 'WWD');
    $other = qrMachine('B-1', 'BUL');
    $koor = qrUser('koor', 'WWD');

    // selected ids containing a foreign machine: it is silently dropped
    $res = $this->actingAs($koor)->post(route('machine-qr.export'), ['mode' => 'selected', 'ids' => [$own->id, $other->id]]);
    expect(substr_count(qrReadZip($res->getContent())['word/document.xml'], '<wp:inline'))->toBe(1);

    // by-area for a foreign area exports nothing
    $this->actingAs($koor)->from(route('machine-qr.index'))
        ->post(route('machine-qr.export'), ['mode' => 'area', 'area' => 'BUL'])
        ->assertSessionHasErrors('export');

    $this->actingAs($koor)->getJson(route('machine-qr.count', ['mode' => 'area', 'area' => 'BUL']))->assertJson(['count' => 0]);
    $this->actingAs($koor)->get(route('machine-qr.preview', $other))->assertForbidden();
    $this->actingAs($koor)->get(route('machine-qr.download', $other))->assertForbidden();
});

/* ===================== Search / filter / selection ===================== */

test('search, area filter and select-all ids behave and are area scoped', function () {
    qrMachine('WWD-PRESS-1', 'WWD', 'Press');
    qrMachine('WWD-LATHE-1', 'WWD', 'Lathe');
    qrMachine('BUL-PRESS-1', 'BUL', 'Press');
    $admin = qrUser('admin');
    $koor = qrUser('koor', 'WWD');

    $nums = fn ($r) => $r->viewData('machines')->pluck('machine_number')->all();

    expect($nums($this->actingAs($admin)->get(route('machine-qr.index', ['search' => 'press']))))
        ->toBe(['BUL-PRESS-1', 'WWD-PRESS-1']);
    expect($nums($this->actingAs($admin)->get(route('machine-qr.index', ['search' => 'press', 'area' => 'WWD']))))
        ->toBe(['WWD-PRESS-1']);

    $this->actingAs($admin)->getJson(route('machine-qr.ids', ['search' => 'press']))->assertJson(['count' => 2]);
    // koordinator "select all" can only ever see own-area ids
    $this->actingAs($koor)->getJson(route('machine-qr.ids', ['search' => 'press']))->assertJson(['count' => 1]);
    $this->actingAs($koor)->getJson(route('machine-qr.ids', ['area' => 'BUL']))->assertJson(['count' => 0]);
});

/* ===================== Authorization ===================== */

test('only admin and koordinator reach every endpoint, directly by url', function () {
    $m = qrMachine('WWD-001');
    $routes = [
        ['get', route('machine-qr.index')],
        ['get', route('machine-qr.ids')],
        ['get', route('machine-qr.machines')],
        ['get', route('machine-qr.count')],
        ['get', route('machine-qr.preview', $m)],
        ['get', route('machine-qr.download', $m)],
        ['get', route('machine-qr.overrides.history', $m)],
        ['post', route('machine-qr.export')],
        ['post', route('machine-qr.overrides.store')],
        ['delete', route('machine-qr.overrides.reset', $m)],
    ];

    foreach (['pic' => 'WWD', 'sup' => null, 'guest' => null] as $role => $area) {
        $u = qrUser($role, $area);
        foreach ($routes as [$method, $url]) {
            $this->actingAs($u)->{$method}($url)->assertForbidden();
        }
    }

    auth()->logout();
    foreach ($routes as [$method, $url]) {
        expect($this->{$method}($url)->status())->toBeIn([302, 401, 403]);
    }

    $this->actingAs(qrUser('admin'))->get(route('machine-qr.index'))->assertOk();
    $this->actingAs(qrUser('koor', 'WWD'))->get(route('machine-qr.index'))->assertOk();
});

test('menu entry is visible to admin and koordinator only', function () {
    qrMachine('WWD-001');
    $this->actingAs(qrUser('admin'))->get(route('dashboard'))->assertSee(route('machine-qr.index'), false);
    $this->actingAs(qrUser('koor', 'WWD'))->get(route('dashboard'))->assertSee(route('machine-qr.index'), false);
    $this->actingAs(qrUser('sup'))->get(route('dashboard'))->assertDontSee(route('machine-qr.index'), false);
    $this->actingAs(qrUser('pic', 'WWD'))->get(route('dashboard'))->assertDontSee(route('machine-qr.index'), false);
});

/* ===================== Last PM Override ===================== */

test('create, update and reset an override with a full audit trail', function () {
    $m = qrMachine('WWD-001');
    qrPm($m, '2026-03-10');
    $admin = qrUser('admin');

    $this->actingAs($admin)->post(route('machine-qr.overrides.store'), [
        'machine_id' => $m->id, 'override_date' => '2026-02-01', 'reason' => 'Done earlier',
    ])->assertRedirect();
    expect(MachineLastPmOverride::where('machine_id', $m->id)->first()->override_date->toDateString())->toBe('2026-02-01');

    // later than the system date is allowed too, and there is still only one active row
    $this->actingAs($admin)->post(route('machine-qr.overrides.store'), [
        'machine_id' => $m->id, 'override_date' => '2026-05-20', 'reason' => 'Corrected',
    ])->assertRedirect();
    expect(MachineLastPmOverride::count())->toBe(1);
    expect(MachineLastPmOverride::first()->override_date->toDateString())->toBe('2026-05-20');

    $this->actingAs($admin)->delete(route('machine-qr.overrides.reset', $m))->assertRedirect();
    expect(MachineLastPmOverride::count())->toBe(0);

    $logs = MachineLastPmOverrideLog::where('machine_id', $m->id)->orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['CREATE', 'UPDATE', 'RESET']);
    expect($logs[1]->previous_override_date->toDateString())->toBe('2026-02-01');
    expect($logs[1]->new_override_date->toDateString())->toBe('2026-05-20');
    expect($logs[2]->previous_reason)->toBe('Corrected')->and($logs[2]->new_override_date)->toBeNull();
    expect($logs->every(fn ($l) => $l->performed_by === $admin->id))->toBeTrue();

    $this->actingAs($admin)->getJson(route('machine-qr.overrides.history', $m))->assertJsonCount(3, 'logs');
});

test('override validates date and requires a reason and an existing system last pm', function () {
    $m = qrMachine('WWD-001');
    $noPm = qrMachine('WWD-002');
    qrPm($m, '2026-03-10');
    $admin = qrUser('admin');

    $this->actingAs($admin)->post(route('machine-qr.overrides.store'), ['machine_id' => $m->id, 'override_date' => '2026-02-01', 'reason' => ''])
        ->assertSessionHasErrors('reason');
    $this->actingAs($admin)->post(route('machine-qr.overrides.store'), ['machine_id' => $m->id, 'override_date' => 'not-a-date', 'reason' => 'x'])
        ->assertSessionHasErrors('override_date');
    $this->actingAs($admin)->post(route('machine-qr.overrides.store'), ['machine_id' => $noPm->id, 'override_date' => '2026-02-01', 'reason' => 'x'])
        ->assertSessionHasErrors('machine_id');

    expect(MachineLastPmOverride::count())->toBe(0);
    expect(MachineLastPmOverrideLog::count())->toBe(0);
});

test('koordinator can override only machines in their own area', function () {
    $own = qrMachine('W-1', 'WWD');
    $other = qrMachine('B-1', 'BUL');
    qrPm($own, '2026-03-10');
    qrPm($other, '2026-03-10');
    $koor = qrUser('koor', 'WWD');

    $this->actingAs($koor)->post(route('machine-qr.overrides.store'), ['machine_id' => $own->id, 'override_date' => '2026-03-01', 'reason' => 'r'])->assertRedirect();
    $this->actingAs($koor)->post(route('machine-qr.overrides.store'), ['machine_id' => $other->id, 'override_date' => '2026-03-01', 'reason' => 'r'])->assertForbidden();

    // the foreign machine got an override from admin; koordinator can neither reset nor read its history
    MachineLastPmOverride::create(['machine_id' => $other->id, 'override_date' => '2026-03-01', 'reason' => 'r']);
    $this->actingAs($koor)->delete(route('machine-qr.overrides.reset', $other))->assertForbidden();
    $this->actingAs($koor)->get(route('machine-qr.overrides.history', $other))->assertForbidden();
    expect(MachineLastPmOverride::where('machine_id', $other->id)->exists())->toBeTrue();
});

test('override list shows system last pm, status and filters by status and area', function () {
    $a = qrMachine('W-1', 'WWD');
    $b = qrMachine('W-2', 'WWD');
    $c = qrMachine('B-1', 'BUL');
    qrPm($a, '2026-03-10');
    qrPm($a, '2026-04-10');
    MachineLastPmOverride::create(['machine_id' => $a->id, 'override_date' => '2026-01-01', 'reason' => 'r']);
    $admin = qrUser('admin');

    $res = $this->actingAs($admin)->get(route('machine-qr.index', ['tab' => 'override']));
    $res->assertOk()->assertSee('10-04-2026')->assertSee('01-01-2026')->assertSee('Overridden')->assertSee('Normal');
    $nums = fn ($r) => $r->viewData('machines')->pluck('machine_number')->all();

    expect($nums($this->actingAs($admin)->get(route('machine-qr.index', ['tab' => 'override', 'status' => 'overridden']))))->toBe(['W-1']);
    expect($nums($this->actingAs($admin)->get(route('machine-qr.index', ['tab' => 'override', 'status' => 'normal', 'area' => 'WWD']))))->toBe(['W-2']);
});

test('machine history shows override with label, falls back after reset, next pm and transactions untouched', function () {
    $m = qrMachine('WWD-001');
    $pm = qrPm($m, '2026-03-10');
    $admin = qrUser('admin');

    $before = $this->get('/m/WWD-001');
    $before->assertOk()->assertSee('10-03-2026')->assertSee('10-04-2026')->assertDontSee('Manual Override');
    $nextBefore = $before->viewData('nextPm')->toDateString();

    $this->actingAs($admin)->post(route('machine-qr.overrides.store'), ['machine_id' => $m->id, 'override_date' => '2026-02-01', 'reason' => 'r']);
    auth()->logout();

    $show = $this->get('/m/WWD-001');
    $show->assertSee('01-02-2026')->assertSee('Manual Override');
    expect($show->viewData('nextPm')->toDateString())->toBe($nextBefore)->toBe('2026-04-10');

    // list page too
    $this->get(route('machine-history.index'))->assertSee('01-02-2026')->assertSee('Manual Override');

    // original PM transaction + machine row unchanged
    $fresh = PMSchedule::find($pm->id);
    expect(substr((string) $fresh->actual_date, 0, 10))->toBe('2026-03-10')->and($fresh->status)->toBe('FINISHED_ON_TIME');
    expect(Machine::find($m->id)->only('pm_cycle_value', 'pm_cycle_unit'))->toBe(['pm_cycle_value' => 1, 'pm_cycle_unit' => 'month']);

    $this->actingAs($admin)->delete(route('machine-qr.overrides.reset', $m));
    auth()->logout();
    $this->get('/m/WWD-001')->assertSee('10-03-2026')->assertDontSee('Manual Override');
    $this->get(route('machine-history.index'))->assertSee('10-03-2026')->assertDontSee('Manual Override');
});
