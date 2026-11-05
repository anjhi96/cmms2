<?php

use App\Models\Area;
use App\Models\Machine;
use App\Models\User;

function isMachines(int $n, string $area = 'WWD'): void
{
    Area::firstOrCreate(['name' => $area], ['slug' => strtolower($area), 'is_active' => true]);
    for ($i = 1; $i <= $n; $i++) {
        Machine::create([
            'machine_number' => sprintf('IS-%03d', $i), 'area' => $area, 'machine_type' => 'PRESS',
            'status' => 'ACTIVE', 'pm_cycle_value' => 1, 'pm_cycle_unit' => 'month',
        ]);
    }
}

/** Strips <noscript> so only the JS-visible markup is inspected. */
function isVisibleHtml(string $html): string
{
    return preg_replace('#<noscript>.*?</noscript>#s', '', $html);
}

dataset('list pages', [
    'machine master' => ['machines.index', ['area' => 'WWD', 'search' => 'IS-'], 'admin'],
    'machine history' => ['machine-history.index', ['search' => 'IS-'], null],
    'machine qr' => ['machine-qr.index', ['search' => 'IS-'], 'admin'],
    'machine qr override' => ['machine-qr.index', ['tab' => 'override', 'search' => 'IS-'], 'admin'],
    'machine report' => ['reports.machine', [], 'admin'],
]);

test('list pages render 20 rows, an infinite-scroll sentinel that keeps the filters, and no numbered links', function (string $route, array $query, ?string $role) {
    isMachines(25);
    if ($role) {
        $this->actingAs(User::factory()->admin()->create());
    }

    $html = $this->get(route($route, $query))->assertOk()->getContent();
    $visible = isVisibleHtml($html);

    expect($visible)->toContain('data-infinite-sentinel=')
        ->and($visible)->toContain('data-infinite-list=')
        ->and($visible)->not->toContain('Pagination Navigation');

    preg_match('/data-next-url="([^"]+)"/', $html, $m);
    expect($m)->not->toBeEmpty();
    $next = html_entity_decode($m[1]);
    expect($next)->toContain('page=2');
    foreach ($query as $k => $v) {
        expect($next)->toContain($k.'=');
    }
})->with('list pages');

test('the next-page url returns the remaining rows and no further sentinel url', function () {
    isMachines(25);
    $this->actingAs(User::factory()->admin()->create());

    $page1 = $this->get(route('machines.index', ['search' => 'IS-']))->getContent();
    preg_match('/data-next-url="([^"]+)"/', $page1, $m);
    $page2 = $this->get(html_entity_decode($m[1]))->assertOk()->getContent();

    expect(substr_count($page1, 'IS-0'))->toBeGreaterThanOrEqual(20);
    expect($page2)->toContain('IS-025')->and($page2)->not->toContain('IS-001<');
    expect(isVisibleHtml($page2))->not->toContain('data-next-url');
});

test('a single page of results shows no loader url', function () {
    isMachines(5);
    $this->actingAs(User::factory()->admin()->create());

    $html = $this->get(route('machines.index'))->getContent();
    expect(isVisibleHtml($html))->not->toContain('data-next-url');
});

test('every list page that used links() now goes through the shared component', function () {
    $offenders = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $f) {
        if (! str_ends_with($f->getFilename(), '.blade.php') || str_contains($f->getPathname(), 'components/infinite-scroll')) {
            continue;
        }
        if (preg_match('/->links\(/', file_get_contents($f->getPathname()))) {
            $offenders[] = str_replace(resource_path('views').'/', '', $f->getPathname());
        }
    }
    expect($offenders)->toBe([]);
});
