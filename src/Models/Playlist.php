<?php

namespace Splicewire\Beam\Bookmarks\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rushing\PermissionCascade\Concerns\HasUser;
use Rushing\PermissionCascade\Concerns\HasVisibility;
use Splicewire\Beam\Bookmarks\Support\Tables;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * A playlist (ADR-0009, tracer 09) — composed on the SAME substrate primitives BeamSilo is built
 * from (permission-cascade {@see HasVisibility} for share/publish through the cascade,
 * {@see HasUser} for ownership, staudenmeir adjacency for nesting) rather than extending Silo's
 * heavier scout/sluggable/sync surface. A user's playlists nest under a per-user root playlist
 * (Option C: a user-rooted subtree), private by default, cascade-gated. Items are ordered +
 * deduped via {@see PlaylistItem}.
 */
class Playlist extends Model
{
    use HasRecursiveRelationships;
    use HasUser;
    use HasUuids;
    use HasVisibility;

    protected $guarded = [];

    public function getTable(): string
    {
        return Tables::name('playlists');
    }

    /** Ordered items (the songs/objects on this playlist). */
    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class, 'playlist_id')->orderBy('position');
    }

    /**
     * Feed the directory-ACL cascade this playlist's ancestors, nearest-first — the containment
     * walk over the adjacency (the package's HasVisibility stays topology-agnostic).
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

    /** Whether this is a user's root container (no parent). */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}
