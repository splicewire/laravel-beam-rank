<?php

namespace Splicewire\Beam\Bookmarks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Bookmarks\Data\BookmarkData;
use Splicewire\Beam\Bookmarks\Data\ShelfData;
use Splicewire\Beam\Bookmarks\Models\Shelf;
use Splicewire\Beam\Particle\Attributes\AttributedParticleDiscovery;
use Splicewire\Beam\Particle\OperationKind;
use Splicewire\Beam\Particle\ParticleOperation;
use Splicewire\Beam\Particle\ParticleOperationRegistry;

/**
 * Register + mount the shelves/bookmarks particle surface (ADR-0009, tracer 09). Reads are the
 * declarative {@see ShelfData}/{@see BookmarkData} resources (discovered here); dedup-aware saves
 * + reorder are operations over the {@see Bookmarks} action. Guarded on the beam particle infra so
 * the package boots (and its standalone suite runs) without laravel-beam's route macros present —
 * a host with beam mounts the surface; the package unit-tests scope/project + the action directly.
 */
class Resources
{
    public static function register(array $opts = []): void
    {
        if (! class_exists(ParticleOperationRegistry::class) || ! Route::hasMacro('particleResource')) {
            return; // beam particle infra absent (e.g. standalone package test env) — nothing to mount.
        }

        $groupPrefix = $opts['group_prefix'] ?? config('beam.bookmarks.resources.group_prefix', 'resources');
        $middleware = $opts['middleware'] ?? config('beam.bookmarks.resources.middleware', ['web', 'auth']);

        app(AttributedParticleDiscovery::class)->discover([ShelfData::class, BookmarkData::class]);

        // The reorder write op is an inline particle operation; `Route::particleOps` (HTTP-02) registers it
        // AND mounts it (was: an imperative `$registry->register(...)` + a bare `Route::particleOp(...)`).
        $reorderOp = new ParticleOperation(
            resource: 'shelves', name: 'reorder', kind: OperationKind::Write, model: Shelf::class, ability: 'update',
            handle: function (Shelf $shelf, Request $request) {
                app(Bookmarks::class)->reorder($shelf, (array) $request->input('ids', []));

                return ['data' => ['id' => $shelf->getKey(), 'ordered' => count((array) $request->input('ids', []))]];
            },
        );

        Route::middleware($middleware)->prefix($groupPrefix)->group(function () use ($reorderOp) {
            Route::particleResource('shelves', 'shelves', ['only' => ['index', 'store', 'update', 'destroy']]);
            Route::particleResource('bookmarks', 'bookmarks', ['only' => ['index', 'destroy']]);
            Route::particleOps('shelves', 'shelves', [$reorderOp]);

            // Dedup-aware save/unsave over the action (a bare create can't dedup) — stay bespoke, not ops.
            Route::post('bookmarks/save', fn (Request $r) => ['data' => self::save($r, false)]);
            Route::post('bookmarks/unsave', fn (Request $r) => ['data' => self::save($r, true)]);
        });
    }

    private static function save(Request $request, bool $remove): array
    {
        $bookmarkable = self::resolveMorph((string) $request->input('bookmarkable_type'), (string) $request->input('bookmarkable_id'));
        $shelf = $request->filled('shelf_id') ? Shelf::query()->findOrFail($request->input('shelf_id')) : null;
        $action = app(Bookmarks::class);

        if ($remove) {
            return ['removed' => $action->unsave($request->user(), $bookmarkable, $shelf)];
        }

        $bookmark = $action->save($request->user(), $bookmarkable, $shelf, $request->input('position'));

        return ['id' => $bookmark->id, 'shelf_id' => $bookmark->shelf_id, 'position' => $bookmark->position];
    }

    private static function resolveMorph(string $type, string $id): Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        return $class::query()->findOrFail($id);
    }
}
