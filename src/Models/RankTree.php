<?php

namespace Splicewire\Beam\Rank\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rushing\PermissionCascade\Attributes\UseCascadePolicy;
use Rushing\PermissionCascade\Concerns\HasMorphUser;
use Rushing\PermissionCascade\Concerns\HasVisibility;
use Rushing\PermissionCascade\Policies\BaseModelPolicy;
use Splicewire\Beam\Facades\Beam;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * A RankTree (was Shelf) — a named, orderable, shareable, NESTABLE grouping of {@see Rank}s.
 * Generic: a host maps its own vocabulary onto it (audiostud: a "playlist" is a tree of favorite
 * ranks; another host: a "reading list", a "board"). Composed on the substrate primitives —
 * permission-cascade {@see HasVisibility} for share/publish through the cascade,
 * {@see HasMorphUser} for single-owner-via-morph-columns ownership (moved off the multi-owner
 * HasUser pivot), staudenmeir adjacency for nesting. A user's trees nest under a per-user root,
 * private by default, cascade-gated.
 *
 * The attribute below is the ENTIRE authorization surface — no Policy class exists anywhere for
 * this model. `create: true` = self-service tree creation (matching the old Shelf/ShelfPolicy
 * behavior), expressed natively by the cascade-policy attribute.
 */
#[UseCascadePolicy(BaseModelPolicy::class, create: true)]
class RankTree extends Model
{
    use HasMorphUser;
    use HasRecursiveRelationships;
    use HasUuids;
    use HasVisibility;

    protected $guarded = [];

    public function getTable(): string
    {
        return Beam::table('rank_trees');
    }

    /** The ranks filed on this tree, in order. */
    public function ranks(): HasMany
    {
        return $this->hasMany(config('beam.rank.models.rank', Rank::class), 'tree_id')->orderBy('position');
    }

    /**
     * Feed the directory-ACL cascade this tree's ancestors, nearest-first — the containment walk
     * over the adjacency (the package's HasVisibility stays topology-agnostic).
     */
    public function visibilityAncestors(): iterable
    {
        $ancestors = [];
        $node = $this->parent;
        while ($node) {
            $ancestors[] = $node;
            $node = $node->parent;
        }

        return $ancestors;
    }

    /** Whether this is a user's root tree (no parent). */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}
