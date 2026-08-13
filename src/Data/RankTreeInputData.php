<?php

namespace Splicewire\Beam\Rank\Data;

use Spatie\LaravelData\Data;
use Splicewire\Beam\Http\Particle\ParticleController;

/** The rank-tree write shape (create/rename/publish): only these are mass-fillable. */
class RankTreeInputData extends Data
{
    public function __construct(
        public string $name,
        public ?string $visibility = null,
        public ?string $parentId = null,
    ) {}

    /**
     * The beam write seam ({@see ParticleController::toAttributes}) fills
     * the model from THIS, not the camelCase `toArray()` — so map the DTO props to the snake_case
     * `beam_rank_trees` columns. Nulls are dropped so a partial write (rename) never clobbers
     * unspecified fields, and a `null` `parentId` on create is left for
     * {@see RankTreeData::prepare} to root.
     */
    public function toModelAttributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'visibility' => $this->visibility,
            'parent_id' => $this->parentId,
        ], fn ($value) => $value !== null);
    }
}
