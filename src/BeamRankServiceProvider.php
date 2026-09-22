<?php

namespace Splicewire\Beam\Rank;

use Rushing\PermissionCascade\Support\CascadePolicyRegistrar;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Splicewire\Beam\Doctor\BeamDoctorManifest;
use Splicewire\Beam\Install\BeamInstallManifest;
use Splicewire\Beam\Rank\Doctor\BeamRankMigrationsAudit;
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
        // Migrations ship PUBLISH-ONLY (spatie/laravel-package-tools defaults runsMigrations to
        // FALSE — no override here), the estate-wide convention. A prior auto-run posture was
        // preserved through the bookmarks-to-rank rename without reference to that convention;
        // corrected here (migration-classification-remediation ticket 08) to match
        // laravel-beam-accounts' pattern exactly.
        //
        // Migrations live under `database/migrations/shared/` — the fleet's shared-by-default
        // ruling (runbook references/multitenancy.md): migrations and models are central/tenant
        // AGNOSTIC unless explicitly determined otherwise and noted at the site. This package's
        // tables (rank_trees, ranks) are deliberately agnostic — no pin, no exception note needed
        // beyond this one — so a publish lands them in the host's `database/migrations/shared/`,
        // the path beam-tenancy's registerSharedMigrationsPath() runs on BOTH passes.
        $package
            ->name('laravel-beam-rank')
            ->hasConfigFile('beam/rank')
            ->hasMigrations([
                'shared/create_rank_trees_table',
                'shared/create_ranks_table',
            ]);
    }

    public function packageBooted(): void
    {
        // The models' #[UseCascadePolicy] attributes are the ENTIRE authorization surface — the
        // registrar wires them onto the Gate; no Policy class ships in this package at all.
        CascadePolicyRegistrar::register(RankTree::class);
        CascadePolicyRegistrar::register(Rank::class);

        // DECLARE the rank-trees/ranks particle surface — registration only, never mounting. Guarded
        // internally on the beam particle infra, so this is a no-op in a headless env or the standalone
        // package test.
        //
        // ⚠️ **This called `Resources::register()` until registry-kernel 71, and a provider is the one
        // place it must never be called from.** A provider boots OUTSIDE every route group, so the
        // mount had no ambient middleware to inherit and the package supplied `['web','auth']` to fill
        // the gap — a guess about the host, and the wrong one anywhere the surface belongs under an API
        // or tenant stack. Laravel MERGES a nested group's middleware into its parent's, so a package
        // that names a stack appends to the host's rather than choosing. Mounting is the host's act now:
        // `audiostud` already calls `Rank\Resources::register([...])` from its own
        // `RankServiceProvider` with an explicit prefix and middleware, which is the ratified shape.
        if (config('beam.rank.register_resources', true)) {
            Resources::declare();
        }

        // Self-register into beam-core's install manifest so `splicewire:beam:install` publishes
        // this package's shared migrations with the rest of the stack, and into the doctor
        // manifest so `StubMigrationsAudit` covers it going forward.
        if ($this->app->bound(BeamInstallManifest::class)) {
            $this->app->make(BeamInstallManifest::class)->register(
                package: 'splicewire/laravel-beam-rank',
                publishTags: ['beam-rank-config', 'beam-rank-migrations'],
                migrates: true,
            );
        }

        if ($this->app->bound(BeamDoctorManifest::class)) {
            $this->app->make(BeamDoctorManifest::class)->register(
                'splicewire/laravel-beam-rank',
                BeamRankMigrationsAudit::class,
            );
        }
    }
}
