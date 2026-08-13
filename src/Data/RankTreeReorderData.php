<?php

namespace Splicewire\Beam\Rank\Data;

use Spatie\LaravelData\Data;

/**
 * Output of the rank-trees reorder operation — the reordered tree's id and how many rank ids the
 * caller's ordered list carried. A Data class so the op output follows the property-case
 * convention the package's read surface ({@see RankTreeData}/{@see RankData}) already sets.
 */
class RankTreeReorderData extends Data
{
    public function __construct(
        public string $id,
        public int $ordered,
    ) {}
}
