<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\MachineLastPmOverride;
use App\Models\MachineLastPmOverrideLog;
use App\Models\PMSchedule;
use App\Models\User;
use App\Services\MachineLastPmService;
use App\Services\MachineQr\MachineQrDocx;
use App\Services\MachineQr\MachineQrRenderer;
use App\Support\AreaAuthorizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Master > Machine QR Management (QR Code Management + Last PM Override).
 * Routes are limited to ADMIN / KOORDINATOR by middleware; every query and
 * every machine lookup below is additionally area-scoped, so a KOORDINATOR can
 * never read, export or override a machine outside their own area.
 */
class MachineQrController extends Controller
{
    private const MAX_EXPORT = 1000;

    public function __construct(
        private readonly MachineQrRenderer $renderer,
        private readonly MachineQrDocx $docx,
        private readonly MachineLastPmService $lastPm,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'override' ? 'override' : 'qr';

        $data = [
            'tab' => $tab,
            'areas' => $this->areaOptions($user),
            'search' => (string) $request->query('search', ''),
            'area' => (string) $request->query('area', ''),
            'status' => (string) $request->query('status', ''),
        ];

        if ($tab === 'qr') {
            $data['machines'] = $this->filtered($user, $request)
                ->orderBy('machine_number')
                ->paginate(20)
                ->withQueryString();
        } else {
            $query = $this->filtered($user, $request)
                ->with('lastPmOverride')
                ->addSelect([
                    'system_last_pm' => PMSchedule::query()
                        ->selectRaw('max(actual_date)')
                        ->whereColumn('pm_schedules.machine_number', 'machines.machine_number'),
                ]);

            if ($data['status'] === 'overridden') {
                $query->whereHas('lastPmOverride');
            } elseif ($data['status'] === 'normal') {
                $query->whereDoesntHave('lastPmOverride');
            }

            $data['machines'] = $query->orderBy('machine_number')->paginate(20)->withQueryString();
        }

        return view('machine-qr.index', $data);
    }

    /** All machine ids matching the current search/area filter (for "Select All"). */
    public function ids(Request $request): JsonResponse
    {
        $ids = $this->filtered($request->user(), $request)->orderBy('machine_number')->pluck('machines.id');

        return response()->json(['ids' => $ids, 'count' => $ids->count()]);
    }

    /** Typeahead for the override form. */
    public function machines(Request $request): JsonResponse
    {
        $rows = $this->filtered($request->user(), $request)
            ->orderBy('machine_number')
            ->limit(20)
            ->get()
            ->map(fn (Machine $m) => [
                'id' => $m->id,
                'machine_number' => $m->machine_number,
                'machine_name' => $m->machine_type,
                'area' => $m->area,
                'system_last_pm' => $this->lastPm->systemLastPm($m)?->toDateString(),
            ]);

        return response()->json($rows);
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->exportQuery($request)->count()]);
    }

    public function preview(Request $request, Machine $machine): Response
    {
        $this->authorizeMachine($request->user(), $machine);

        return $this->png($machine, 'inline');
    }

    public function download(Request $request, Machine $machine): Response
    {
        $this->authorizeMachine($request->user(), $machine);

        return $this->png($machine, 'attachment');
    }

    public function export(Request $request)
    {
        $request->validate([
            'mode' => ['required', 'in:selected,area'],
            'ids' => ['required_if:mode,selected', 'array'],
            'ids.*' => ['integer'],
            'area' => ['required_if:mode,area', 'nullable', 'string'],
        ]);

        $machines = $this->exportQuery($request)->orderBy('area')->orderBy('machine_number')->get();

        if ($machines->isEmpty()) {
            return back()->withErrors(['export' => 'No machines to export.']);
        }
        if ($machines->count() > self::MAX_EXPORT) {
            return back()->withErrors(['export' => 'Too many machines (max '.self::MAX_EXPORT.' per file). Narrow the selection.']);
        }

        $bytes = $this->docx->build($machines);
        $name = 'machine-qr-'.($request->input('mode') === 'area' ? str($request->input('area'))->slug() : 'selected').'-'.now()->format('Ymd-His').'.docx';

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Content-Length' => (string) strlen($bytes),
        ]);
    }

    public function storeOverride(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'machine_id' => ['required', 'integer'],
            'override_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $machine = Machine::findOrFail($data['machine_id']);
        $this->authorizeMachine($request->user(), $machine);

        if ($this->lastPm->systemLastPm($machine) === null) {
            throw ValidationException::withMessages([
                'machine_id' => 'This machine has no System Last PM yet, so it cannot be overridden.',
            ]);
        }

        $existed = MachineLastPmOverride::where('machine_id', $machine->id)->exists();
        $this->lastPm->save($machine, $data['override_date'], trim($data['reason']), $request->user());

        return redirect()
            ->route('machine-qr.index', ['tab' => 'override'] + $request->only('search', 'area', 'status'))
            ->with('success', 'Last PM override '.($existed ? 'updated' : 'created').' for '.$machine->machine_number.'.');
    }

    public function resetOverride(Request $request, Machine $machine): RedirectResponse
    {
        $this->authorizeMachine($request->user(), $machine);

        $reset = $this->lastPm->reset($machine, $request->user());

        return redirect()
            ->route('machine-qr.index', ['tab' => 'override'] + $request->only('search', 'area', 'status'))
            ->with($reset ? 'success' : 'error', $reset
                ? 'Last PM override reset for '.$machine->machine_number.'.'
                : $machine->machine_number.' has no active override.');
    }

    public function history(Request $request, Machine $machine): JsonResponse
    {
        $this->authorizeMachine($request->user(), $machine);

        $logs = MachineLastPmOverrideLog::with('performer')
            ->where('machine_id', $machine->id)
            ->latest('id')
            ->get()
            ->map(fn ($l) => [
                'action' => $l->action,
                'previous_date' => $l->previous_override_date?->format('d-m-Y'),
                'new_date' => $l->new_override_date?->format('d-m-Y'),
                'previous_reason' => $l->previous_reason,
                'new_reason' => $l->new_reason,
                'by' => $l->performer?->name ?? '-',
                'at' => $l->created_at->format('d-m-Y H:i'),
            ]);

        return response()->json(['machine_number' => $machine->machine_number, 'logs' => $logs]);
    }

    /* ------------------------------------------------------------------ */

    private function png(Machine $machine, string $disposition): Response
    {
        $png = $this->renderer->png(MachineQrDocx::url($machine), $machine->machine_number);
        $file = 'QR-'.preg_replace('/[^A-Za-z0-9_\-]/', '_', $machine->machine_number).'.png';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => $disposition.'; filename="'.$file.'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** Machines the user may see at all (role x area). */
    private function scoped(User $user): Builder
    {
        return AreaAuthorizationScope::apply(Machine::query(), $user, 'machines.area');
    }

    private function filtered(User $user, Request $request): Builder
    {
        $query = $this->scoped($user);

        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('machines.machine_number', 'like', '%'.$search.'%')
                    ->orWhere('machines.machine_type', 'like', '%'.$search.'%');
            });
        }

        if (($area = (string) $request->query('area', '')) !== '') {
            $query->where('machines.area', $area);
        }

        return $query->select('machines.*');
    }

    /** Selected ids or whole area, always intersected with the user's area scope. */
    private function exportQuery(Request $request): Builder
    {
        $query = $this->scoped($request->user());

        if ($request->input('mode') === 'area') {
            return $query->where('machines.area', (string) $request->input('area'));
        }

        return $query->whereIn('machines.id', array_map('intval', (array) $request->input('ids', [])));
    }

    private function authorizeMachine(User $user, Machine $machine): void
    {
        abort_unless(
            $this->scoped($user)->whereKey($machine->id)->exists(),
            403,
            'Unauthorized',
        );
    }

    /** @return list<string> */
    private function areaOptions(User $user): array
    {
        return $this->scoped($user)
            ->whereNotNull('machines.area')
            ->distinct()
            ->orderBy('machines.area')
            ->pluck('machines.area')
            ->all();
    }
}
