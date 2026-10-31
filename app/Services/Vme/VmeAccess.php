<?php

namespace App\Services\Vme;

use App\Models\Area;
use App\Models\User;

/**
 * Who may view the VME map, and which areas of it. Server-side only — the
 * UI hiding a link is never the control.
 */
class VmeAccess
{
    public const VIEWER_ROLES = [User::ROLE_ADMIN, User::ROLE_KOORDINATOR, User::ROLE_SUPERVISOR];

    public static function canView(User $user): bool
    {
        return $user->isActive() && in_array($user->role, self::VIEWER_ROLES, true);
    }

    /**
     * Area ids the viewer may see, mirroring Today Activity's scoping:
     * ADMIN everything, SUPERVISOR their assigned areas (all when none are
     * assigned), KOORDINATOR their own area.
     *
     * @return list<int>|null null = unrestricted
     */
    public static function allowedAreaIds(User $user): ?array
    {
        if ($user->isAdmin()) {
            return null;
        }

        if ($user->isSupervisor()) {
            $names = $user->restrictedAreaNames();

            return $names === null ? null : Area::query()->whereIn('name', $names)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($user->isKoordinator() && $user->area_id) {
            return [(int) $user->area_id];
        }

        return [];
    }
}
