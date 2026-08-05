<?php

namespace Splicewire\Beam\Bookmarks\Data;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\Data;
use Splicewire\Beam\Bookmarks\Models\Bookmark;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * The `bookmarks` particle resource (ADR-0009, tracer 09) — the current user's saved particles,
 * FILTERABLE by shelf_id: `?shelf_id=X` = that shelf in order, `?shelf_id=` (empty) = the bare
 * "Saved" list, absent = everything. Read + destroy here; dedup-aware save/reorder are operations
 * over the Bookmarks action.
 */
#[ParticleResource(key: 'bookmarks', model: Bookmark::class, filterable: false, defaultSort: 'position')]
class BookmarkData extends Data
{
    public function __construct(
        public string $id,
        public string $bookmarkable_type,
        public string $bookmarkable_id,
        public ?string $shelf_id,
        public ?int $position,
    ) {}

    public static function scope(Builder $query): Builder
    {
        $actor = Auth::user();
        $query->where('user_type', $actor?->getMorphClass() ?? 'user')->where('user_id', (string) Auth::id());

        if (request()->has('shelf_id')) {
            $shelfId = request()->query('shelf_id');
            $shelfId === null || $shelfId === '' ? $query->whereNull('shelf_id') : $query->where('shelf_id', $shelfId);
        }

        return $query;
    }

    public static function project(Bookmark $bookmark): self
    {
        return new self(
            id: $bookmark->id,
            bookmarkable_type: $bookmark->bookmarkable_type,
            bookmarkable_id: $bookmark->bookmarkable_id,
            shelf_id: $bookmark->shelf_id,
            position: $bookmark->position,
        );
    }
}
