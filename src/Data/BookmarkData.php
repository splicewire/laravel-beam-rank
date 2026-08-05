<?php

namespace Splicewire\Beam\Bookmarks\Data;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Operators\Exact;
use Spatie\LaravelData\Data;
use Splicewire\Beam\Bookmarks\Models\Bookmark;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * The `bookmarks` particle resource (ADR-0009, tracer 09) — the current user's saved particles.
 * The whole read surface is declared here, NOT in a hand-rolled query: the resource is `filterable`,
 * `shelfId` / `bookmarkableType` are `#[Filterable]` facets (a listener narrows with
 * `?filter[shelfId]=X` — absent = all their bookmarks including the bare "Saved" list, shelfId null),
 * and `#[Sortable(default: true)]` on `position` supplies the default order. data-filters
 * auto-generates the query from these annotations.
 *
 * OWNER-SCOPING NOTE: a `filterable:true` resource's read gate is the host's data-filters query —
 * ParticleController skips `scope()` for the filterable path. The `scope()` below documents the
 * required owner constraint; the host binds the actual owner row-gate in its `ResourceQuery`
 * (audiostud's `App\Read\BookmarksQuery::baseQuery` — the one thing annotations can't express). That
 * binding + the end-to-end HTTP filter proof landed in tracer 10.
 */
#[ParticleResource(key: 'bookmarks', model: Bookmark::class, filterable: true, defaultSort: 'position')]
class BookmarkData extends Data
{
    public function __construct(
        public string $id,
        #[Filterable(operator: Exact::class)]
        public string $bookmarkableType,
        public string $bookmarkableId,
        #[Filterable(operator: Exact::class)]
        public ?string $shelfId,
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

    public static function project(Bookmark $bookmark): self
    {
        return new self(
            id: $bookmark->id,
            bookmarkableType: $bookmark->bookmarkable_type,
            bookmarkableId: $bookmark->bookmarkable_id,
            shelfId: $bookmark->shelf_id,
            position: $bookmark->position,
        );
    }
}
