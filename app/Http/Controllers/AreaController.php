<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin-only Area Management — the UI that lets a new area (GRIPPER,
 * PACKING, ...) be introduced as pure master data, with no source-code
 * change required anywhere else in the app.
 *
 * Deliberately no destroy(): areas are soft-disabled via `is_active` rather
 * than deleted, since machines/pm_schedules/oil_audits/users/groups all
 * reference an area by name/FK and a hard delete could silently orphan them.
 * `name` is also not editable after creation (see edit()/update()) — every
 * area comparison elsewhere in the app is a string/FK match against the
 * name set at creation time, so renaming it would silently break those
 * matches for existing data.
 */
class AreaController extends Controller
{
    public function index()
    {
        $areas = Area::withCount('users', 'groups')->orderBy('name')->get();

        return view('areas.index', compact('areas'));
    }

    public function create()
    {
        return view('areas.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('areas', 'name')],
        ]);

        $name = strtoupper(trim($validated['name']));

        Area::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);

        return redirect()
            ->route('areas.index')
            ->with('success', 'Area created successfully.');
    }

    public function edit(Area $area)
    {
        return view('areas.edit', compact('area'));
    }

    public function update(Request $request, Area $area)
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $area->update($validated);

        return redirect()
            ->route('areas.index')
            ->with('success', 'Area updated successfully.');
    }
}
