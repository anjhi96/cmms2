<?php

namespace App\Services\Vme;

use App\Models\VmeSetting;
use Throwable;

/**
 * VME feature switches. Everything defaults to OFF (no row = OFF) and the
 * environment kill switch (config('vme.kill_switch')) overrides the database.
 * Nothing operational ever consults these.
 */
class VmeFeature
{
    public const DISPLAY = 'display';

    public const ACTIVITY_SYNC = 'activity_sync';

    public const HUMAN_MOVEMENT = 'human_movement';

    /** Master switch for the AI characters (VME-07). Each character also has its own switch, default ON. */
    public const AI_CHARACTERS = 'ai_characters';

    public const KEYS = [self::DISPLAY, self::ACTIVITY_SYNC, self::HUMAN_MOVEMENT, self::AI_CHARACTERS];

    public static function killed(): bool
    {
        return (bool) config('vme.kill_switch');
    }

    /** Raw stored switches (ignores the kill switch) — for the admin screen. */
    public static function stored(): array
    {
        $rows = [];

        try {
            $rows = VmeSetting::query()->pluck('value', 'key')->all();
        } catch (Throwable) {
            // Table missing (migration not run yet) => everything OFF.
        }

        $out = [];
        foreach (self::KEYS as $key) {
            $out[$key] = ($rows['vme.'.$key] ?? '0') === '1';
        }

        return $out;
    }

    /** Effective switches after the kill switch. */
    public static function all(): array
    {
        $stored = self::stored();

        if (self::killed()) {
            return array_fill_keys(self::KEYS, false);
        }

        return $stored;
    }

    public static function enabled(string $key): bool
    {
        return self::all()[$key] ?? false;
    }

    /** Per-character switch (default ON); the master switch and the kill switch still apply. */
    public static function characterEnabled(string $character): bool
    {
        $rows = [];

        try {
            $rows = VmeSetting::query()->where('key', 'vme.ai.'.$character)->pluck('value', 'key')->all();
        } catch (Throwable) {
            // Table missing => defaults.
        }

        return ($rows['vme.ai.'.$character] ?? '1') === '1';
    }

    public static function setCharacter(string $character, bool $on, ?int $userId = null): void
    {
        VmeSetting::updateOrCreate(
            ['key' => 'vme.ai.'.$character],
            ['value' => $on ? '1' : '0', 'updated_by' => $userId],
        );

        VmeSnapshotService::flush();
    }

    public static function set(string $key, bool $on, ?int $userId = null): void
    {
        abort_unless(in_array($key, self::KEYS, true), 422, 'Unknown VME feature.');

        VmeSetting::updateOrCreate(
            ['key' => 'vme.'.$key],
            ['value' => $on ? '1' : '0', 'updated_by' => $userId],
        );

        VmeSnapshotService::flush();
    }
}
