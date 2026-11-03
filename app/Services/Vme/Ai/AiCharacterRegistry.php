<?php

namespace App\Services\Vme\Ai;

use App\Models\Area;
use App\Services\Vme\VmeFeature;

/**
 * The one place the AI characters are defined: config('vme.ai.characters'). Each entry has a
 * name, role, avatar and an analysis class implementing AiCharacterAnalysis. Adding a character is
 * a config entry plus a class; the map renders whatever this returns.
 *
 * An entry with `per_area => true` (the Koordinator) is expanded into one character per active
 * Area: key "<key>_<area slug>", name "<name> <AREA>", visiting only its own Area.
 */
class AiCharacterRegistry
{
    /**
     * Every defined character (valid class), in desk order, regardless of switches.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $defs = [];
        $desk = 0;
        $areas = null;

        foreach ((array) config('vme.ai.characters', []) as $key => $def) {
            $class = $def['analysis'] ?? null;
            if (! is_string($class) || ! is_subclass_of($class, AiCharacterAnalysis::class)) {
                continue; // a broken definition must not take the map down
            }

            if (empty($def['per_area'])) {
                $defs[$key] = $def + ['key' => $key, 'desk' => $desk++, 'area_id' => null, 'visit_zones' => null];

                continue;
            }

            $areas ??= Area::active()->orderBy('name')->get(['id', 'name', 'slug']);
            $looks = array_values($def['avatars'] ?? [$def['avatar'] ?? null]);

            foreach ($areas->values() as $i => $area) {
                $areaKey = $key.'_'.str_replace('-', '_', $area->slug);
                $defs[$areaKey] = array_merge($def, [
                    'key' => $areaKey,
                    'name' => $def['name'].' '.$area->name,
                    'role' => $def['role'].' di area '.$area->name,
                    'avatar' => $looks[$i % count($looks)],
                    'desk' => $desk++,
                    'area_id' => $area->id,
                    'visit_zones' => ['area:'.$area->id], // only its own Area
                ]);
            }
        }

        return $defs;
    }

    /**
     * Characters that may appear: the AI master switch is ON (and the kill switch is not) and the
     * character itself has not been switched off. With `$onlyAreaId` (an Area filter on the map)
     * only characters that are not tied to another Area remain.
     *
     * @return array<string, array<string, mixed>>
     */
    public function enabled(?int $onlyAreaId = null): array
    {
        if (! VmeFeature::enabled(VmeFeature::AI_CHARACTERS)) {
            return [];
        }

        $out = [];
        $desk = 0;

        foreach ($this->all() as $key => $def) {
            if (! VmeFeature::characterEnabled($key)) {
                continue;
            }
            if ($onlyAreaId !== null && $def['area_id'] !== null && $def['area_id'] !== $onlyAreaId) {
                continue;
            }
            // Desks are numbered over what is shown, so a filtered map has no gaps in its Office.
            $out[$key] = array_merge($def, ['desk' => $desk++]);
        }

        return $out;
    }

    public function find(string $key): ?array
    {
        return $this->enabled()[$key] ?? null;
    }

    public function analysisFor(array $definition): AiCharacterAnalysis
    {
        return app($definition['analysis']);
    }
}
