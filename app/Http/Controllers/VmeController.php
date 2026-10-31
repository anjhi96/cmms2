<?php

namespace App\Http\Controllers;

use App\Services\Vme\VmeAccess;
use App\Services\Vme\VmeFeature;
use App\Services\Vme\VmeLayout;
use App\Services\Vme\VmeSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Virtual Maintenance Environment — read-only viewer endpoints. Behind
 * auth + role middleware (not public like /monitor); every handler also
 * checks the feature switch/kill switch, so VME OFF = 404 here and nothing
 * else in FreeDOMS is affected.
 */
class VmeController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureEnabled($request);

        return view('vme.index', [
            'snapshotUrl' => route('vme.snapshot'),
            'configUrl' => route('vme.config'),
        ]);
    }

    public function snapshot(Request $request, VmeSnapshotService $service): JsonResponse|Response
    {
        $user = $this->ensureEnabled($request);

        $payload = $service->forViewer(VmeAccess::allowedAreaIds($user), $user->isAdmin());

        return $this->respond($request, $payload, $payload['version']);
    }

    public function config(Request $request): JsonResponse|Response
    {
        $user = $this->ensureEnabled($request);

        $payload = [
            'features' => VmeFeature::all(),
            'poll_seconds' => (int) config('vme.poll_seconds'),
            'stale_after_seconds' => (int) config('vme.stale_after_seconds'),
            'patrol_seconds' => (int) config('vme.patrol_seconds'),
            'layout' => VmeLayout::make()->toArray(),
            'avatars' => collect(config('vme.avatars'))->map(fn ($a) => collect($a)->except('label')->all())->all(),
        ];

        return $this->respond($request, $payload, sha1(json_encode($payload)));
    }

    private function ensureEnabled(Request $request)
    {
        $user = $request->user();

        abort_unless($user && VmeAccess::canView($user), 403, 'Unauthorized');
        abort_unless(VmeFeature::enabled(VmeFeature::DISPLAY), 404);

        return $user;
    }

    /** JSON with an ETag; 304 when the client already holds this version. */
    private function respond(Request $request, array $payload, string $version): JsonResponse|Response
    {
        $etag = '"'.$version.'"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, no-cache'];

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response()->json($payload, 200, $headers);
    }
}
