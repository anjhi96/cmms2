<?php

use App\Services\SparepartForecast\ForecastEngine;

function fcPm(int $id, string $date, array $usage = []): array
{
    return ['pm_id' => $id, 'date' => $date, 'usage' => $usage];
}

/** $n monthly PMs starting 2026-01-05; $usage is a callback(index) => usage map. */
function fcHistory(int $n, callable $usage, int $startId = 1): array
{
    $h = [];
    for ($i = 0; $i < $n; $i++) {
        $h[] = fcPm($startId + $i, date('Y-m-d', strtotime('2026-01-05 +'.($i * 30).' days')), $usage($i));
    }

    return $h;
}

it('gives no numbers for a machine with too little history', function () {
    $result = (new ForecastEngine)->forecastMachine([fcPm(1, '2026-01-01', [10 => 2]), fcPm(2, '2026-02-01', [10 => 2])], 3);

    expect($result['status'])->toBe('INSUFFICIENT_DATA')
        ->and($result['items'])->toBe([])
        ->and($result['note'])->toContain('Insufficient Historical Data');
});

it('requires usage records, not just finished PMs', function () {
    $result = (new ForecastEngine)->forecastMachine(fcHistory(5, fn () => []), 2);

    expect($result['status'])->toBe('INSUFFICIENT_DATA')->and($result['items'])->toBe([]);
});

it('computes probability, expected and recommended quantity from frequency', function () {
    // Part 1 used on 3 of 4 PMs (qty 2); part 2 on 1 of 4 PMs (qty 5).
    $history = [
        fcPm(1, '2026-01-01', [1 => 2, 2 => 5]),
        fcPm(2, '2026-02-01', [1 => 2]),
        fcPm(3, '2026-03-01', []),
        fcPm(4, '2026-04-01', [1 => 2]),
    ];

    $r = (new ForecastEngine)->forecastMachine($history, 2);
    $a = $r['items'][1];
    $b = $r['items'][2];

    expect($r['status'])->toBe('SUFFICIENT')
        ->and($a['usage_pm_count'])->toBe(3)
        ->and($a['probability'])->toBe(0.75)
        ->and($a['avg_qty'])->toBe(2.0)
        ->and($a['expected_qty'])->toBe(3.0)   // 2 PM × 0.75 × 2
        ->and($a['level'])->toBe('LIKELY')
        ->and($a['recommended_qty'])->toBe(3)
        ->and($a['mean_interval_days'])->toBe(45.0) // Jan 1 -> Feb 1 (31) and Feb 1 -> Apr 1 (59)
        // POSSIBLE parts are estimated but not recommended.
        ->and($b['probability'])->toBe(0.25)
        ->and($b['expected_qty'])->toBe(2.5)
        ->and($b['level'])->toBe('POSSIBLE')
        ->and($b['recommended_qty'])->toBe(0);
});

it('forecasts zero demand when no PM is scheduled', function () {
    $r = (new ForecastEngine)->forecastMachine(fcHistory(4, fn () => [1 => 1]), 0);

    expect($r['items'][1]['expected_qty'])->toBe(0.0)->and($r['items'][1]['recommended_qty'])->toBe(0);
});

it('detects an increasing usage trend', function () {
    // 8 PMs: used only in the newer half.
    $r = (new ForecastEngine)->forecastMachine(fcHistory(8, fn ($i) => $i >= 4 ? [1 => 1] : [2 => 1]), 1);

    expect($r['items'][1]['trend'])->toBe('INCREASING')->and($r['items'][2]['trend'])->toBe('DECREASING');
});

it('only looks at the most recent window of PMs', function () {
    // 20 PMs; part 1 only in the first 8 (outside the 12-PM window).
    $r = (new ForecastEngine)->forecastMachine(fcHistory(20, fn ($i) => $i < 8 ? [1 => 1] : [2 => 1]), 1);

    expect($r['items'])->not->toHaveKey(1)->and($r['items'][2]['pm_considered'])->toBe(ForecastEngine::WINDOW);
});

it('reports accuracy as unavailable when there is not enough testing data', function () {
    $eval = (new ForecastEngine)->evaluate([1 => fcHistory(5, fn () => [1 => 1])]);

    expect($eval['status'])->toBe('NOT_AVAILABLE')
        ->and($eval['mae'])->toBeNull()->and($eval['wape'])->toBeNull()->and($eval['bias'])->toBeNull();
});

it('evaluates with a temporal split and computes MAE, WAPE and bias', function () {
    // 6 machines × 10 PMs, part used every PM with qty 1: train 8 / test 2 each.
    $byMachine = [];
    foreach (range(1, 6) as $machine) {
        $byMachine[$machine] = fcHistory(10, fn () => [1 => 1], $machine * 100);
    }

    $perfect = (new ForecastEngine)->evaluate($byMachine);
    expect($perfect['status'])->toBe('AVAILABLE')
        ->and($perfect['train_pm_count'])->toBe(48)->and($perfect['test_pm_count'])->toBe(12)
        ->and($perfect['mae'])->toBe(0.0)->and($perfect['wape'])->toBe(0.0)->and($perfect['bias'])->toBe(0.0);
    expect($perfect['train_to'] < $perfect['test_to'])->toBeTrue();

    // Machine 1 really used qty 2 on its two test PMs: predicted 2, actual 4.
    $byMachine[1][8]['usage'] = [1 => 2];
    $byMachine[1][9]['usage'] = [1 => 2];
    $off = (new ForecastEngine)->evaluate($byMachine);

    expect($off['compared_items'])->toBe(6)
        ->and($off['mae'])->toBe(round(2 / 6, 4))
        ->and($off['wape'])->toBe(round(2 / 14, 4))
        ->and($off['bias'])->toBe(round(-2 / 14, 4));
});

it('does not let test-period usage influence the training forecast', function () {
    $a = fcHistory(10, fn () => [1 => 1]);
    $b = $a;
    $b[9]['usage'] = [1 => 50, 2 => 9]; // only the held-out PM changes

    $eng = new ForecastEngine;
    expect($eng->forecastMachine(array_slice($a, 0, 8), 2))->toBe($eng->forecastMachine(array_slice($b, 0, 8), 2));
});
