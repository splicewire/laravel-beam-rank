<?php

namespace Splicewire\Beam\Rank\Data;

use Spatie\LaravelData\Data;

/**
 * Output of the untoggle operation — how many Rank rows the gesture removal deleted. A Data class
 * (not a hand-rolled array) so the op output follows the property-case convention the package's
 * read surface ({@see RankData}) already sets.
 */
class RankRemovedData extends Data
{
    public function __construct(
        public int $removed,
    ) {}
}
