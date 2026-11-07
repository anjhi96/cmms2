<?php

/*
 * Small PM: PM work done per row of machines (a row holds several machines).
 * Rows are master data (like Groups); schedules work like Greasing.
 */

return [

    // Areas that get the Small PM menu.
    'areas' => ['WWD'],

    // How many rows the "Generate" button puts into one week.
    'rows_per_week' => 4,

    // Cycle filled in by "Generate"; executing a schedule plans the next one this many weeks after its action date.
    'default_cycle' => '26W',

];
