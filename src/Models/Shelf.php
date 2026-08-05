<?php

namespace Splicewire\Beam\Bookmarks\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rushing\PermissionCascade\Concerns\HasUser;
use Rushing\PermissionCascade\Concerns\HasVisibility;
use Splicewire\Beam\Beam;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * A shelf (ADR-0009, tracer 09) — a named, orderable, shareable, NESTABLE grouping of
 * {@see Bookmark}s. Generic: a host maps its own vocabulary onto it (audiostud: a "playlist" is a
 * shelf of song bookmarks; another host: a "reading list", a "board"). Composed on the SAME
 * substrate primitives BeamSilo is built from — permission-cascade {@see HasVisibility} for
 * share/publish through the cascade, {@see HasUser} for ownership, staudenmeir adjacency for
 * nesting — rather than extending Silo's heavier scout/sluggable/sync surface. A user's shelves
 * nest under a per-user root shelf (Option C), private by default, cascade-gated.
 */
class Shelf extends Model
{
    use HasRecursiveRelationships;
    use HasUser;
    use HasUuids;
    use HasVisibility;

    protected $guarded = [];

    public function getTable(): string
    {
        return Beam::table('shelves');
    }

    /** The bookmarks filed on this shelf, in order. */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(config('beam.bookmarks.models.bookmark', Bookmark::class), 'shelf_id')->orderBy('position');
    }

    /**
     * Feed the directory-ACL cascade this shelf's ancestors, nearest-first — the containment walk
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

    /** Whether this is a user's root shelf (no parent). */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}
