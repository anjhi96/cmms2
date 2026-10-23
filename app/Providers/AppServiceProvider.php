<?php

namespace App\Providers;

use App\Services\QueueWorkerStatus;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if (env('CODESPACES') === true) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');

            // Pagination links build their base path from the raw request
            // (Request::url()), which ignores forceRootUrl() above and just
            // reflects whatever Host header Codespaces' proxy happens to send
            // to the backend (observed as "localhost:8000"). Route it through
            // url()->current() instead, which does respect forceRootUrl().
            Paginator::currentPathResolver(fn () => url()->current());
        }

        // Heartbeat for the Notifikasi Email page: queue:work fires Looping
        // on every poll, so a fresh timestamp proves a worker is alive.
        // Throttled per process to one cache write every 30s.
        Event::listen(Looping::class, function () {
            static $last = 0;

            if (time() - $last >= 30) {
                $last = time();
                Cache::put(QueueWorkerStatus::KEY, time(), now()->addMinutes(10));
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
