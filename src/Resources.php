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
use Splicewire\Beam\Rank\Models\Rank;
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

            // Dedup-aware toggle/untoggle/rate over the action (a bare create can't dedup on the
            // full unique tuple) — stay bespoke, not ops.
            Route::post('ranks/toggle', fn (Request $r) => ['data' => self::toggle($r, false)]);
            Route::post('ranks/untoggle', fn (Request $r) => ['data' => self::toggle($r, true)]);
            Route::post('ranks/rate', fn (Request $r) => ['data' => self::rate($r)]);
        });
    }

    /**
     * Mount the per-model rank operations for one host model — the additive sibling of the global
     * surface above (a record page wants the clean nested route; a "my activity" page wants the
     * global filterable listing). Mirrors `laravel-beam-accounts`'s `Sharing::attachTo()`; called
     * via {@see Rank::attachTo()}.
     *
     * `Resources::attachTo('songs', Composition::class)` mounts
     * `songs/{song}/op/rank-toggle|rank-untoggle|rank-rate` (the `{uri}/{id}/op/{name}` shape the
     * particleOp macro owns). Ops default to the `view` ability — anyone who can SEE a record may
     * rank it — overridable per host via `$opts['ability']`.
     */
    public static function attachTo(string $resourceKey, string $model, array $opts = []): void
    {
        if (! class_exists(ParticleOperationRegistry::class) || ! Route::hasMacro('particleOps')) {
            return; // beam particle infra absent — nothing to mount.
        }

        $urlKey = $opts['url_key'] ?? $resourceKey;
        $groupPrefix = $opts['group_prefix'] ?? config('beam.rank.resources.group_prefix', 'resources');
        $middleware = $opts['middleware'] ?? config('beam.rank.resources.middleware', ['web', 'auth']);
        $ops = self::operationsFor($resourceKey, $model, $opts);

        Route::middleware($middleware)->prefix($groupPrefix)->group(function () use ($urlKey, $resourceKey, $ops) {
            Route::particleOps($urlKey, $resourceKey, $ops);
        });
    }

    /**
     * Build the three per-model rank operations (pure — the mountable half of {@see attachTo},
     * separately callable so the op contract is testable without the beam route macros).
     *
     * @param  class-string<Model>  $model
     * @return array<int, ParticleOperation>
     */
    public static function operationsFor(string $resourceKey, string $model, array $opts = []): array
    {
        $ability = $opts['ability'] ?? 'view';

        return [
            new ParticleOperation(
                resource: $resourceKey, name: 'rank-toggle', kind: OperationKind::Write, model: $model, ability: $ability,
                handle: function (Model $resource, Request $request) {
                    $data = $request->validate([
                        'type' => ['required', 'string'],
                        'tree_id' => ['nullable', 'string'],
                        'position' => ['nullable', 'integer'],
                    ]);
                    $tree = isset($data['tree_id']) ? self::treeModel()::query()->findOrFail($data['tree_id']) : null;
                    $rank = app(Ranks::class)->toggle($request->user(), $resource, $data['type'], $tree, $data['position'] ?? null);

                    return ['data' => ['id' => $rank->id, 'type' => $rank->type, 'tree_id' => $rank->tree_id, 'position' => $rank->position]];
                },
            ),
            new ParticleOperation(
                resource: $resourceKey, name: 'rank-untoggle', kind: OperationKind::Write, model: $model, ability: $ability,
                handle: function (Model $resource, Request $request) {
                    $data = $request->validate([
                        'type' => ['required', 'string'],
                        'tree_id' => ['nullable', 'string'],
                    ]);
                    $tree = isset($data['tree_id']) ? self::treeModel()::query()->findOrFail($data['tree_id']) : null;

                    return ['data' => ['removed' => app(Ranks::class)->untoggle($request->user(), $resource, $data['type'], $tree)]];
                },
            ),
            new ParticleOperation(
                resource: $resourceKey, name: 'rank-rate', kind: OperationKind::Write, model: $model, ability: $ability,
                handle: function (Model $resource, Request $request) {
                    $data = $request->validate([
                        'value' => ['required', 'numeric'],
                        'min' => ['nullable', 'numeric'],
                        'max' => ['nullable', 'numeric'],
                    ]);
                    $rank = app(Ranks::class)->rate(
                        $request->user(),
                        $resource,
                        (float) $data['value'],
                        isset($data['min']) ? (float) $data['min'] : null,
                        isset($data['max']) ? (float) $data['max'] : null,
                    );

                    return ['data' => ['id' => $rank->id, 'type' => $rank->type, 'value' => $rank->value]];
                },
            ),
        ];
    }

    private static function toggle(Request $request, bool $remove): array
    {
        $rankable = self::resolveMorph((string) $request->input('rankable_type'), (string) $request->input('rankable_id'));
        $tree = $request->filled('tree_id') ? self::treeModel()::query()->findOrFail($request->input('tree_id')) : null;
        $type = (string) $request->input('type');
        $action = app(Ranks::class);

        if ($remove) {
            return ['removed' => $action->untoggle($request->user(), $rankable, $type, $tree)];
        }

        $rank = $action->toggle($request->user(), $rankable, $type, $tree, $request->input('position'));

        return ['id' => $rank->id, 'type' => $rank->type, 'tree_id' => $rank->tree_id, 'position' => $rank->position];
    }

    private static function rate(Request $request): array
    {
        $rankable = self::resolveMorph((string) $request->input('rankable_type'), (string) $request->input('rankable_id'));
        $tree = $request->filled('tree_id') ? self::treeModel()::query()->findOrFail($request->input('tree_id')) : null;

        $rank = app(Ranks::class)->rate(
            $request->user(),
            $rankable,
            (float) $request->input('value'),
            $request->filled('min') ? (float) $request->input('min') : null,
            $request->filled('max') ? (float) $request->input('max') : null,
            $tree,
        );

        return ['id' => $rank->id, 'type' => $rank->type, 'value' => $rank->value, 'tree_id' => $rank->tree_id];
    }

    private static function resolveMorph(string $type, string $id): Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        return $class::query()->findOrFail($id);
    }

    /**
     * The tree model behind the `beam.rank.models.tree` seam — the same seam {@see Ranks}
     * honors, so a host substituting its own tree model is respected on every lookup path.
     *
     * @return class-string<RankTree>
     */
    private static function treeModel(): string
    {
        return config('beam.rank.models.tree', RankTree::class);
    }
}
