<?php

namespace Splicewire\Beam\Rank;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Facades\Particle;
use Splicewire\Beam\Particle\Attributes\AttributedParticleDiscovery;
use Splicewire\Beam\Particle\OperationKind;
use Splicewire\Beam\Particle\ParticleOperation;
use Splicewire\Beam\Particle\ParticleOperationRegistry;
use Splicewire\Beam\Rank\Data\RankData;
use Splicewire\Beam\Rank\Data\RankRemovedData;
use Splicewire\Beam\Rank\Data\RankTreeData;
use Splicewire\Beam\Rank\Ops\RateRank;
use Splicewire\Beam\Rank\Ops\ReorderRanks;
use Splicewire\Beam\Rank\Ops\ToggleRank;
use Splicewire\Beam\Rank\Ops\UntoggleRank;

/**
 * Register + mount the rank-trees/ranks particle surface. Reads are the declarative
 * {@see RankTreeData}/{@see RankData} resources (discovered here); writes are the `src/Ops/`
 * single-operation classes (ADR-0160/HTTP-10 — one class per operation, logic lives ONCE, in Ops).
 * Guarded on the beam particle infra so the package boots (and its standalone suite runs) without
 * laravel-beam's route macros present — a host with beam mounts the surface; the package
 * unit-tests scope/project + the action + the op handlers directly.
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

        Route::middleware($middleware)->prefix($groupPrefix)->group(function () {
            Particle::mount('rank-trees', 'rank-trees')->only(['index', 'store', 'update', 'destroy']);
            Particle::mount('ranks', 'ranks')->only(['index', 'destroy']);

            // The reorder write op is a `#[ParticleOp]` Ops class; `Particle::ops()`
            // discovers (registers) it AND mounts it.
            Particle::ops('rank-trees', 'rank-trees', [ReorderRanks::class]);

            // Dedup-aware toggle/untoggle/rate over the action (a bare create can't dedup on the
            // full unique tuple). Collection-level — the target arrives as body morph keys, so
            // there's no `{id}` segment for an op mount and the routes stay bespoke — but each
            // body delegates to its Ops class, the single home of the handler logic.
            Route::post('ranks/toggle', fn (Request $r) => ['data' => ToggleRank::bare($r)]);
            Route::post('ranks/untoggle', fn (Request $r) => ['data' => UntoggleRank::bare($r)]);
            Route::post('ranks/rate', fn (Request $r) => ['data' => RateRank::bare($r)]);
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
     * `Particle::ops()` mounts). Ops default to the `view` ability — anyone who can SEE a record may
     * rank it — overridable per host via `$opts['ability']`.
     */
    public static function attachTo(string $resourceKey, string $model, array $opts = []): void
    {
        if (! class_exists(ParticleOperationRegistry::class) || ! class_exists(Particle::class)) {
            return; // beam particle infra absent — nothing to mount.
        }

        $urlKey = $opts['url_key'] ?? $resourceKey;
        $groupPrefix = $opts['group_prefix'] ?? config('beam.rank.resources.group_prefix', 'resources');
        $middleware = $opts['middleware'] ?? config('beam.rank.resources.middleware', ['web', 'auth']);
        $ops = self::operationsFor($resourceKey, $model, $opts);

        Route::middleware($middleware)->prefix($groupPrefix)->group(function () use ($urlKey, $resourceKey, $ops) {
            Particle::ops($urlKey, $resourceKey, $ops);
        });
    }

    /**
     * Build the three per-model rank operations (pure — the mountable half of {@see attachTo},
     * separately callable so the op contract is testable without the beam route macros).
     *
     * The (resource, model) pair is RUNTIME input, so these declarations genuinely cannot ride a
     * static `#[ParticleOp]` attribute — the construction stays a thin runtime
     * {@see ParticleOperation}, but every handler is the corresponding Ops class's `handle`
     * (first-class callable), so the logic lives once, in Ops.
     *
     * @param  class-string<Model>  $model
     * @return array<int, ParticleOperation>
     */
    public static function operationsFor(string $resourceKey, string $model, array $opts = []): array
    {
        $ability = $opts['ability'] ?? 'view';

        return [
            new ParticleOperation(
                resource: $resourceKey, name: 'rank-toggle', kind: OperationKind::Write, model: $model,
                ability: $ability, handle: ToggleRank::handle(...), output: RankData::class,
            ),
            new ParticleOperation(
                resource: $resourceKey, name: 'rank-untoggle', kind: OperationKind::Write, model: $model,
                ability: $ability, handle: UntoggleRank::handle(...), output: RankRemovedData::class,
            ),
            new ParticleOperation(
                resource: $resourceKey, name: 'rank-rate', kind: OperationKind::Write, model: $model,
                ability: $ability, handle: RateRank::handle(...), output: RankData::class,
            ),
        ];
    }
}
