<?php

namespace Splicewire\Beam\Bookmarks\Policies;

use Rushing\PermissionCascade\Policies\BaseModelPolicy;
use Splicewire\Beam\Bookmarks\Models\Shelf;

/**
 * The shelf write gate + read scope (ADR-0009, tracer 09): rides the permission-cascade
 * BaseModelPolicy — steward (owner) manages; index widens to reach-visible (published) shelves
 * via scopeForUser. Used by ShelfData::scope() and the particle write gate.
 */
class ShelfPolicy extends BaseModelPolicy
{
    public static $defaultModelClass = Shelf::class;
}
