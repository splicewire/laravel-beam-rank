<?php

namespace Splicewire\Beam\Bookmarks;

use Illuminate\Support\ServiceProvider;

/**
 * Save lists of particles (ADR-0009, tracer 09): a generic Bookmark (saved particle) + a Shelf
 * (named, nestable, shareable grouping), on the permission-cascade visibility substrate +
 * staudenmeir adjacency, exposed as declarative particle resources (shelves + bookmarks). Tables
 * are prefixed by beam core (Beam::table). A host maps its own vocabulary onto a Shelf (tracer 10).
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

        // Mount the shelves/bookmarks particle surface. Guarded internally on the beam particle
        // infra, so this is a no-op in a headless env or the standalone package test.
        if (config('beam.bookmarks.register_resources', true)) {
            Resources::register();
        }
    }
}
