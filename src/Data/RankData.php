<?php

namespace Splicewire\Beam\Rank\Data;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Operators\Exact;
use Splicewire\Beam\Data\BeamData;
use Splicewire\Beam\Particle\Attributes\ParticleResource;
use Splicewire\Beam\Rank\Models\Rank;

/**
 * The `ranks` particle resource — the current user's ranks across every type and target. The
 * whole read surface is declared here, NOT in a hand-rolled query: the resource is `filterable`,
 * `treeId` / `rankableType` / `type` are `#[Filterable]` facets (a host narrows with
 * `?filter[type]=favorite` — absent = every rank including the bare ungrouped list), and
 * `#[Sortable(default: true)]` on `position` supplies the default order. data-filters
 * auto-generates the query from these annotations.
 *
 * OWNER-SCOPING NOTE: a `filterable:true` resource's read gate is its data-filters query —
 * ParticleController skips `scope()` for the filterable path. The `scope()` below declares the owner
 * constraint, and {@see \Splicewire\Beam\Rank\Query\RankResourceQuery} is the package-tier query that
 * READS it (beam-docs-satellite 65 — an earlier version of this note said "the host binds the actual
 * owner row-gate in its `ResourceQuery`", which nominated a gate no host but audiostud ever wrote). A
 * host may still bind a narrower query over it (audiostud's `App\Read\RanksQuery` adds `type = favorite`).
 */
#[ParticleResource(key: 'ranks', backing: Rank::class, filterable: true)]
class RankData extends BeamData
{
    public function __construct(
        public string $id,
        #[Filterable(operator: Exact::class)]
        public string $rankableType,
        public string $rankableId,
        #[Filterable(operator: Exact::class)]
        public ?string $treeId,
        #[Filterable(operator: Exact::class)]
        public string $type,
        public ?float $value,
        #[Sortable(default: true)]
        public ?int $position,
    ) {}

    public static function scope(Builder $query): Builder
    {
        $actor = Auth::user();

        return $query
            ->where('user_type', $actor?->getMorphClass() ?? 'user')
            ->where('user_id', (string) Auth::id());
    }

    public static function project(Rank $rank): self
    {
        return new self(
            id: $rank->id,
            rankableType: $rank->rankable_type,
            rankableId: $rank->rankable_id,
            treeId: $rank->tree_id,
            type: $rank->type,
            value: $rank->value,
            position: $rank->position,
        );
    }
}
