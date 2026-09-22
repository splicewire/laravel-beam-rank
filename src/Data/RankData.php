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
 * Filter controls derive from the declared vocabulary. Resource scopes apply to all reads.
 */
#[ParticleResource(key: 'ranks', backing: Rank::class)]
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
