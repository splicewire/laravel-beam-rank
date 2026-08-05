<?php

namespace Splicewire\Beam\Bookmarks\Data;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Operators\Exact;
use Spatie\LaravelData\Data;
use Splicewire\Beam\Bookmarks\Models\Bookmark;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * The `bookmarks` particle resource (ADR-0009, tracer 09) — the current user's saved particles.
 * Filtering rides the particle stack's data-filters mechanism (NOT a hand-rolled query): the
 * resource is `filterable`, and `shelfId` / `bookmarkableType` are declared facets, so a listener
 * narrows with `?filter[shelfId]=X` (a shelf's items in order) — absent = all their bookmarks
 * including the bare "Saved" list (shelfId null).
 *
 * OWNER-SCOPING NOTE (tracer 10): a `filterable:true` resource's read gate is the host's
 * data-filters query — ParticleController skips `scope()` for the filterable path. The `scope()`
 * below documents the required owner constraint, but the host must enforce `user = actor` in the
 * bound DataFilterRecordHydrator query for `bookmarks`. No audiostud resource is `filterable:true`
 * yet, so that hydrator/owner-scope binding + the end-to-end HTTP filter proof are tracer 10's job.
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
