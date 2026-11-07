<?php

/*
 * Weekly PM Plan defaults. A new week's plan starts with these per-day
 * settings; the coordinator can still change them on the page, and a week
 * that already exists keeps what was saved for it.
 *
 * Days are Monday..Friday in order. Types are machine families (first word of
 * the machine type, see App\Services\WeeklyPlan\MachineFamily).
 */

return [

    // Area opened from the sidebar when the user may see more than one area.
    'default_area' => 'WWD',

    // Per-area defaults; an area without an entry gets 'fallback'.
    'days' => [
        'WWD' => [
            ['limit' => 4, 'types' => [['type' => 'NDE', 'count' => 2], ['type' => 'SHUIXING', 'count' => 2]]], // Senin
            ['limit' => 4, 'types' => [['type' => 'NDE', 'count' => 2], ['type' => 'SHUIXING', 'count' => 2]]], // Selasa
            ['limit' => 4, 'types' => [['type' => 'NDE', 'count' => 2], ['type' => 'SHUIXING', 'count' => 2]]], // Rabu
            ['limit' => 4, 'types' => [['type' => 'NDE', 'count' => 2], ['type' => 'NDB', 'count' => 2]]],      // Kamis
            ['limit' => 0, 'types' => []],                                                                          // Jumat
        ],
    ],

    'fallback' => ['limit' => 4, 'types' => []],

];
