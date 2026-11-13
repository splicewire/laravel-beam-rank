<?php

namespace Splicewire\Beam\Rank\Routing;

use Illuminate\Routing\Router;
use Splicewire\Beam\Particle\ParticleOperationRegistry;
use Splicewire\Beam\Rank\Resources;
use Splicewire\Beam\Routing\ApiRoot;

/**
 * The rank package's routes at its own root (ADR-0243, owner-review-20261112 13d): `beam.rank.api_root`, default
 * `beam/rank`. The host calls this inside its authentication and tenant boundary, beside the legacy
 * `Resources::register()` it calls today, which stays until ticket 16. The package adds no middleware here: the
 * host's group is the boundary. Route names start `beam.rank.`.
 *
 * `Resources::attachTo()` is not mirrored: it mounts rank ops onto a host model's resource, which the host declares,
 * so those routes move with the host's own resources (14a), not with this package.
 */
final class RankRoutes
{
    /** rank-trees, ranks, the reorder op and the toggle/untoggle/rate routes: what `Resources::register()` mounts. */
    public static function mount(array $opts = []): void
    {
        Resources::declare();

        if (! class_exists(ParticleOperationRegistry::class)) {
            return; // beam particle infra genuinely absent, as Resources::register().
        }

        $idConstraint = $opts['idConstraint'] ?? 'uuid';

        ApiRoot::mount(app(Router::class), 'beam.rank.api_root', 'rank', function (Router $router) use ($idConstraint): void {
            $router->name('beam.rank.')->group(fn () => Resources::routes($idConstraint));
        });
    }
}
