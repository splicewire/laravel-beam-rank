<?php

namespace Splicewire\Beam\Bookmarks;

use Illuminate\Support\ServiceProvider;

/**
 * Playlists + bookmarks (ADR-0009, tracer 09), composed on the permission-cascade visibility
 * substrate + staudenmeir adjacency. Ships models/migrations/config only; the host UI + faceting
 * is the consuming satellite (tracer 10).
 */
class BeamBookmarksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/beam/bookmarks.php', 'beam.bookmarks');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/beam/bookmarks.php' => $this->app->configPath('beam/bookmarks.php'),
            ], 'beam-bookmarks-config');
        }

        if (config('beam.bookmarks.register_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }
}
