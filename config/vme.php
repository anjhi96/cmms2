<?php

/*
|--------------------------------------------------------------------------
| Virtual Maintenance Environment (VME)
|--------------------------------------------------------------------------
|
| Read-only 2D visualisation of FreeDOMS activity. Laravel stays the source
| of truth; nothing in here changes an operational record.
|
| Feature switches (display / activity_sync / human_movement) live in the
| vme_settings table and default to OFF. VME_KILL_SWITCH is the
| environment-level override: when true, every VME endpoint is disabled no
| matter what the database says.
*/

return [

    'kill_switch' => (bool) env('VME_KILL_SWITCH', false),

    /*
    | Patrol: some work is done by walking around the Area (an Oil Audit goes from
    | point to point). While such an activity is active the character stays
    | `patrol_seconds` at a point, walks to the next one, and repeats. Only
    | activities without a machine patrol; a machine/group keeps the PIC there.
    */
    'patrol_seconds' => max(1, (int) env('VME_PATROL_SECONDS', 3)),
    'patrol_job_types' => ['oil_audit'],

    // Client polling interval (clamped to 5–10 s) and server-side snapshot cache.
    'poll_seconds' => max(5, min(10, (int) env('VME_POLL_SECONDS', 8))),
    'snapshot_ttl' => (int) env('VME_SNAPSHOT_TTL', 5),
    // The client flags data as stale when no successful poll for this long.
    'stale_after_seconds' => (int) env('VME_STALE_AFTER', 30),

    /*
    | Map geometry (visual pixels). Areas are laid out as columns of the
    | production zone in Area-name order, so adding an Area adds a column
    | without any code change. Machine positions are stored relative to
    | their Area column, so adding areas never shifts them.
    */
    'layout' => [
        'margin' => 24,
        'min_width' => 960,
        // Office and Workshop are compact fixed-size rooms, not half the world each.
        'office_width' => 400,
        'workshop_width' => 400,
        'zone_gap' => 16,
        'office_height' => 130,
        'corridor_height' => 70,
        'area_width' => 300,
        'area_height' => 440,
        // Area-relative anchors.
        'base_origin' => ['x' => 22, 'y' => 52],
        'base_cols' => 5,
        'base_dx' => 46,
        'base_dy' => 46,
        'oil_station' => ['x' => 245, 'y' => 66],
        // Where an area-level activity with no machine/group is performed.
        'default_anchor' => ['x' => 130, 'y' => 150],
        'group_origin' => ['x' => 34, 'y' => 175],
        'group_cols' => 3,
        'group_dx' => 80,
        'group_dy' => 46,
        // Machines are placed automatically, only while a PIC works on them, on a
        // grid wide enough that the PIC standing beside one never covers the next.
        'machine_origin' => ['x' => 40, 'y' => 270],
        'machine_cols' => 3,
        'machine_rows' => 3,
        'machine_dx' => 90,
        'machine_dy' => 70,
        // Patrol route inside an Area (Area-relative, visited in order and looping).
        'patrol' => [[279, 76], [215, 150], [95, 140], [80, 225], [150, 335], [245, 395], [255, 260]],
        // Where a character stands relative to the machine it works at.
        // Wide enough that the character does not overlap the machine it works at.
        'stand_offset' => ['x' => 34, 'y' => 10],
    ],

    /*
    | Avatar presets (static assets — generated as pixel art in the browser).
    | A user without a vme_character_profiles row gets a preset derived from
    | their id, so no setup is needed for a new PIC.
    */
    'avatars' => [
        'a1' => ['label' => 'Biru', 'skin' => '#f1c27d', 'hair' => '#2b2118', 'shirt' => '#1e6fd0', 'pants' => '#2f3a52'],
        'a2' => ['label' => 'Hijau', 'skin' => '#c68642', 'hair' => '#111111', 'shirt' => '#2e9a55', 'pants' => '#3a3a3a'],
        'a3' => ['label' => 'Oranye', 'skin' => '#e0ac69', 'hair' => '#5a3a1a', 'shirt' => '#e07a1f', 'pants' => '#34455f'],
        'a4' => ['label' => 'Merah', 'skin' => '#8d5524', 'hair' => '#1a1a1a', 'shirt' => '#c0392b', 'pants' => '#2d2d2d'],
        'a5' => ['label' => 'Ungu', 'skin' => '#f1c27d', 'hair' => '#8a5a2b', 'shirt' => '#7d4fc4', 'pants' => '#3b3b55'],
        'a6' => ['label' => 'Kuning', 'skin' => '#ffdbac', 'hair' => '#3c2a14', 'shirt' => '#d9b81f', 'pants' => '#3a4a3a'],
        'a7' => ['label' => 'Teal', 'skin' => '#c68642', 'hair' => '#222222', 'shirt' => '#1b9aaa', 'pants' => '#2a2f3a'],
        'a8' => ['label' => 'Abu', 'skin' => '#e0ac69', 'hair' => '#6b6b6b', 'shirt' => '#6c7a89', 'pants' => '#303030'],
    ],

    /*
    | A Manual Activity whose location text is one of these (case-insensitive,
    | or starts with it) is performed in the Workshop zone.
    */
    'workshop_labels' => ['workshop', 'bengkel'],

    // Animations the client sprite sheet implements.
    // Keep in sync with WORK_ANIM_KEYS in resources/js/vme/animations.js (a vitest checks it).
    'animations' => [
        'work_tool', 'work_pm', 'work_oil_sample', 'work_oil_action', 'work_grease',
        'work_clean', 'work_inspect', 'work_measure', 'work_panel',
    ],
    // Used when a job type has no (or an unknown) animation.
    'fallback_animation' => 'work_inspect',
];
