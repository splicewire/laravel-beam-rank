<?php

namespace Splicewire\Beam\Rank;

use Rushing\PermissionCascade\Support\CascadePolicyRegistrar;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Splicewire\Beam\Rank\Models\Rank;
use Splicewire\Beam\Rank\Models\RankTree;

/**
 * The Rank substrate: any actor attaches a typed gesture ({@see RankType}) to any target —
 * a generic {@see Rank} (typed actor→target declaration, plus a scalar rating) + a
 * {@see RankTree} (named, nestable, shareable grouping) — on the permission-cascade
 * HasMorphUser/visibility substrate + staudenmeir adjacency, exposed as declarative particle
 * resources (rank-trees + ranks). Tables are prefixed by beam core (Beam::table). A host maps its
 * own vocabulary onto the types and trees (audiostud: "Save" is a favorite; a "playlist" is a tree).
 */
class BeamRankServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        // `runsMigrations()` is explicit and load-bearing: package-tools defaults it to FALSE
        // (publish-only), and this package keeps the auto-loaded-at-boot idiom — a host opts out
        // via config('beam.rank.register_migrations').
        $package
            ->name('laravel-beam-rank')
            ->hasConfigFile('beam/rank')
            ->hasMigrations([
                '2026_08_11_000100_create_rank_trees_table',
                '2026_08_11_000200_create_ranks_table',
            ])
            ->runsMigrations(config('beam.rank.register_migrations', true));
    }

    public function packageBooted(): void
    {
        // The models' #[UseCascadePolicy] attributes are the ENTIRE authorization surface — the
        // registrar wires them onto the Gate; no Policy class ships in this package at all.
        CascadePolicyRegistrar::register(RankTree::class);
        CascadePolicyRegistrar::register(Rank::class);

        // Mount the rank-trees/ranks particle surface. Guarded internally on the beam particle
        // infra, so this is a no-op in a headless env or the standalone package test.
        if (config('beam.rank.register_resources', true)) {
            Resources::register();
        }
    }
}
