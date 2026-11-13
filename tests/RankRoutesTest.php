<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Schemastud\DataSchemas\LaravelDataSchemasServiceProvider;
use Splicewire\Beam\BeamServiceProvider;
use Splicewire\Beam\Rank\Resources;
use Splicewire\Beam\Rank\Routing\RankRoutes;

// owner-review-20261112 13d (ADR-0243): the package mounts at its own root, beam/rank, beside the mount the host makes
// today. The host's group is the boundary; legacy routes keep their URIs and names until ticket 16.

// This harness does not boot laravel-beam (its particle routes are tested through the handlers), so the route macros a
// mount needs are absent; a route-table test boots the provider that owns them, as a host does.
beforeEach(function () {
    app()->register(BeamServiceProvider::class);
    // The particle controller resolves the schema registry when Laravel gathers its middleware.
    app()->register(LaravelDataSchemasServiceProvider::class);
});

/** method + path-under-root => action, for every route whose URI starts with $root; sorted. */
function rankRouteTable(string $root): array
{
    $table = [];
    foreach (app(Router::class)->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), $root)) {
            continue;
        }
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            // Closure routes (toggle/untoggle/rate) have no action name to compare; their presence is the table entry.
            $table[$method.' '.substr($route->uri(), strlen($root))] = $route->getActionName();
        }
    }
    ksort($table);

    return $table;
}

it('registers the default root beside the legacy mount, inside the host boundary', function () {
    Resources::register(['group_prefix' => 'resources', 'middleware' => []]);
    Route::domain('workspace.example')->middleware('auth')->group(fn () => RankRoutes::mount());

    $current = app(Router::class)->getRoutes()->match(Request::create('http://workspace.example/beam/rank/rank-trees'));
    $toggle = app(Router::class)->getRoutes()->match(Request::create('http://workspace.example/beam/rank/ranks/toggle', 'POST'));

    expect(array_keys(rankRouteTable('beam/rank/')))->toBe(array_keys(rankRouteTable('resources/')))
        ->and(rankRouteTable('beam/rank/'))->toHaveKeys(['POST ranks/toggle', 'POST ranks/untoggle', 'POST ranks/rate', 'GET rank-trees'])
        ->and($current->getActionName())->toBe(app(Router::class)->getRoutes()->match(Request::create('/resources/rank-trees'))->getActionName())
        ->and($current->gatherMiddleware())->toContain('auth')
        ->and($toggle->gatherMiddleware())->toContain('auth')
        ->and($current->getDomain())->toBe('workspace.example')
        ->and($current->getName())->toStartWith('beam.rank.');

    $this->getJson('http://workspace.example/beam/rank/rank-trees')->assertUnauthorized();
});

it('moves only the package root when the host overrides it, and leaves attachTo with the host', function () {
    Resources::register(['group_prefix' => 'resources', 'middleware' => []]);
    config(['beam.rank.api_root' => '/custom/rank/']);

    RankRoutes::mount();

    expect(array_keys(rankRouteTable('custom/rank/')))->toBe(array_keys(rankRouteTable('resources/')))
        ->and(rankRouteTable('beam/rank/'))->toBeEmpty()
        ->and(array_filter(array_keys(rankRouteTable('custom/rank/')), fn ($key) => str_contains($key, 'rank-toggle')))->toBeEmpty();
});
