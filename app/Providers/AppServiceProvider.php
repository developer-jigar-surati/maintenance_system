<?php

namespace App\Providers;

use App\Support\SocietyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One context per request or queued job. Must be a singleton: the
        // society scope and the society stamp on writes both resolve it, and
        // a fresh instance each time would mean no active society at all.
        $this->app->singleton(SocietyContext::class);
    }

    public function boot(): void
    {
        // Guard against lazy-loading N+1s and silently discarded attributes in
        // development, while leaving production tolerant.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Model::unguard(false);

        // Nothing here may touch the database. boot() runs during
        // `composer install` (package:discover) and during `config:cache`,
        // before a freshly cloned project has a database to connect to, so a
        // query at this point breaks installation outright.

        Vite::prefetch(concurrency: 3);
    }
}
