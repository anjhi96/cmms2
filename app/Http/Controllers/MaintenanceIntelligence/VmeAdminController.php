<?php

namespace App\Http\Controllers\MaintenanceIntelligence;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VmeCharacterProfile;
use App\Services\Vme\VmeFeature;
use App\Services\Vme\VmeSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * VME management inside the Maintenance Intelligence Center (ADMIN only —
 * enforced by the route group): feature switches, character avatars and
 * diagnostics. Map layout is fully automatic; there is nothing to position.
 */
class VmeAdminController extends Controller
{
    public function index(VmeSnapshotService $snapshots): View
    {
        return view('maintenance-intelligence.vme', [
            'killed' => VmeFeature::killed(),
            'features' => VmeFeature::stored(),
            'effective' => VmeFeature::all(),
            'pics' => User::query()->where('role', User::ROLE_PIC)->where('is_active', true)
                ->with('area:id,name')->orderBy('name')->get(['id', 'name', 'area_id']),
            'profiles' => VmeCharacterProfile::query()->pluck('avatar_key', 'user_id'),
            'avatars' => config('vme.avatars'),
            'diagnostics' => $snapshots->diagnostics(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        foreach (VmeFeature::KEYS as $key) {
            VmeFeature::set($key, $request->boolean($key), $request->user()->id);
        }

        return back()->with('success', 'Pengaturan VME disimpan.');
    }

    public function updateAvatar(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === User::ROLE_PIC, 404);

        $data = $request->validate([
            'avatar_key' => ['required', Rule::in(array_keys(config('vme.avatars')))],
        ]);

        VmeCharacterProfile::updateOrCreate(['user_id' => $user->id], $data);
        VmeSnapshotService::flush();

        return back()->with('success', 'Avatar '.$user->name.' disimpan.');
    }
}
