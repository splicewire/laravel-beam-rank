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
        //
        // Migrations live under `database/migrations/shared/` — the fleet's shared-by-default
        // ruling (runbook references/multitenancy.md): migrations and models are central/tenant
        // AGNOSTIC unless explicitly determined otherwise and noted at the site. This package's
        // tables (rank_trees, ranks) are deliberately agnostic — no pin, no exception note needed
        // beyond this one — so a publish lands them in the host's `database/migrations/shared/`,
        // the path beam-tenancy's registerSharedMigrationsPath() runs on BOTH passes. The auto-run
        // posture (runsMigrations above, vs. package-tools' publish-only default) is likewise
        // DELIBERATE, preserved per the bookmarks-to-rank build SPEC.
        $package
            ->name('laravel-beam-rank')
            ->hasConfigFile('beam/rank')
            ->hasMigrations([
                'shared/2026_08_11_000100_create_rank_trees_table',
                'shared/2026_08_11_000200_create_ranks_table',
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
