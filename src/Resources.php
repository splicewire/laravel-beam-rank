<?php

namespace Splicewire\Beam\Rank;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Particle\Attributes\AttributedParticleDiscovery;
use Splicewire\Beam\Particle\OperationKind;
use Splicewire\Beam\Particle\ParticleOperation;
use Splicewire\Beam\Particle\ParticleOperationRegistry;
use Splicewire\Beam\Rank\Data\RankData;
use Splicewire\Beam\Rank\Data\RankTreeData;
use Splicewire\Beam\Rank\Models\RankTree;

/**
 * Register + mount the rank-trees/ranks particle surface. Reads are the declarative
 * {@see RankTreeData}/{@see RankData} resources (discovered here); dedup-aware toggles + reorder
 * are operations over the {@see Ranks} action. Guarded on the beam particle infra so the package
 * boots (and its standalone suite runs) without laravel-beam's route macros present — a host with
 * beam mounts the surface; the package unit-tests scope/project + the action directly.
 */
class Resources
{
    public static function register(array $opts = []): void
    {
        if (! class_exists(ParticleOperationRegistry::class) || ! Route::hasMacro('particleResource')) {
            return; // beam particle infra absent (e.g. standalone package test env) — nothing to mount.
        }

        $groupPrefix = $opts['group_prefix'] ?? config('beam.rank.resources.group_prefix', 'resources');
        $middleware = $opts['middleware'] ?? config('beam.rank.resources.middleware', ['web', 'auth']);

        app(AttributedParticleDiscovery::class)->discover([RankTreeData::class, RankData::class]);

        // The reorder write op is an inline particle operation; `Route::particleOps` (HTTP-02) registers it
        // AND mounts it.
        $reorderOp = new ParticleOperation(
            resource: 'rank-trees', name: 'reorder', kind: OperationKind::Write, model: RankTree::class, ability: 'update',
            handle: function (RankTree $tree, Request $request) {
                app(Ranks::class)->reorder($tree, (array) $request->input('ids', []));

                return ['data' => ['id' => $tree->getKey(), 'ordered' => count((array) $request->input('ids', []))]];
            },
        );

        Route::middleware($middleware)->prefix($groupPrefix)->group(function () use ($reorderOp) {
            Route::particleResource('rank-trees', 'rank-trees', ['only' => ['index', 'store', 'update', 'destroy']]);
            Route::particleResource('ranks', 'ranks', ['only' => ['index', 'destroy']]);
            Route::particleOps('rank-trees', 'rank-trees', [$reorderOp]);

            // Dedup-aware toggle/untoggle over the action (a bare create can't dedup on the full
            // unique tuple) — stay bespoke, not ops.
            Route::post('ranks/toggle', fn (Request $r) => ['data' => self::toggle($r, false)]);
            Route::post('ranks/untoggle', fn (Request $r) => ['data' => self::toggle($r, true)]);
        });
    }

    private static function toggle(Request $request, bool $remove): array
    {
        $rankable = self::resolveMorph((string) $request->input('rankable_type'), (string) $request->input('rankable_id'));
        $tree = $request->filled('tree_id') ? RankTree::query()->findOrFail($request->input('tree_id')) : null;
        $type = (string) $request->input('type');
        $action = app(Ranks::class);

        if ($remove) {
            return ['removed' => $action->untoggle($request->user(), $rankable, $type, $tree)];
        }

        $rank = $action->toggle($request->user(), $rankable, $type, $tree, $request->input('position'));

        return ['id' => $rank->id, 'type' => $rank->type, 'tree_id' => $rank->tree_id, 'position' => $rank->position];
    }

    private static function resolveMorph(string $type, string $id): Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        return $class::query()->findOrFail($id);
    }
}
