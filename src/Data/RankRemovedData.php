<?php

namespace Splicewire\Beam\Rank\Data;

use Splicewire\Beam\Data\BeamData;

/**
 * Output of the untoggle operation — how many Rank rows the gesture removal deleted. A Data class
 * (not a hand-rolled array) so the op output follows the property-case convention the package's
 * read surface ({@see RankData}) already sets.
 */
class RankRemovedData extends BeamData
{
    public function __construct(
        public int $removed,
    ) {}
}
