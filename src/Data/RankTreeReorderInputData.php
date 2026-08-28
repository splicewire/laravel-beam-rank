<?php

namespace Splicewire\Beam\Rank\Data;

use Splicewire\Beam\Data\BeamData;

/**
 * The caller's ordered rank-id list — index ⇒ position, which is the whole reorder payload.
 *
 * Declared as a DTO rather than left to `$request->input('ids')` because the op's INPUT is its
 * contract: `Ranks::reorder()` treats position as the list index, so the ORDER of this array is
 * the semantics and a client that cannot see the field from the published schema cannot send it.
 * The output twin is {@see RankTreeReorderData}.
 *
 * An empty list is legal and is a no-op reorder — refusing it would make "clear then re-apply"
 * an error the caller cannot avoid.
 *
 * @property list<string> $ids
 */
class RankTreeReorderInputData extends BeamData
{
    /**
     * @param  list<string>  $ids
     */
    public function __construct(
        public array $ids = [],
    ) {}
}
