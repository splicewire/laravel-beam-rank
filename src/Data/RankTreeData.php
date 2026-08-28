<?php

namespace Splicewire\Beam\Rank\Data;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Rushing\DataFilters\Attributes\Sortable;
use Splicewire\Beam\Data\BeamData;
use Splicewire\Beam\Particle\Attributes\ParticleResource;
use Splicewire\Beam\Rank\Models\RankTree;
use Splicewire\Beam\Rank\Ranks;

/**
 * The `rank-trees` particle resource — declarative read/write/hydrate: `scope` = own ∪
 * reach-visible (published) via the cascade; `prepare` defaults a new tree under the user's root
 * (ownership stamps via the HasMorphUser creating hook); the cascade policy declared by the
 * model's own `#[UseCascadePolicy]` attribute is the deny-default write gate — no Policy class
 * exists anywhere.
 */
#[ParticleResource(key: 'rank-trees', backing: RankTree::class, input: RankTreeInputData::class, filterable: false)]
class RankTreeData extends BeamData
{
    public function __construct(
        public string $id,
        public ?string $parentId,
        #[Sortable(default: true)]
        public string $name,
        public ?string $visibility,
        public int $rankCount,
    ) {}

    /** Own ∪ reach-visible trees (published trees surface to others via scopeForUser). */
    public static function scope(Builder $query): Builder
    {
        $model = config('beam.rank.models.tree', RankTree::class);

        return Gate::getPolicyFor($model)->scopeForUser($query, Auth::user());
    }

    /** Default a new tree under the user's root before the create write. */
    public static function prepare(RankTree $tree, mixed $input, mixed $actor): void
    {
        if ($tree->parent_id === null && $actor !== null) {
            $tree->parent_id = app(Ranks::class)->rootFor($actor)->getKey();
        }
    }

    public static function project(RankTree $tree): self
    {
        $visibility = $tree->visibility;

        return new self(
            id: $tree->id,
            parentId: $tree->parent_id,
            name: $tree->name,
            visibility: $visibility instanceof \BackedEnum ? $visibility->value : $visibility,
            rankCount: $tree->ranks()->count(),
        );
    }
}
