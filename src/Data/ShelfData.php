<?php

namespace Splicewire\Beam\Bookmarks\Data;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Rushing\DataFilters\Attributes\Sortable;
use Spatie\LaravelData\Data;
use Splicewire\Beam\Bookmarks\Bookmarks;
use Splicewire\Beam\Bookmarks\Models\Shelf;
use Splicewire\Beam\Bookmarks\Policies\ShelfPolicy;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

/**
 * The `shelves` particle resource (ADR-0009, tracer 09) — declarative read/write/hydrate: `scope`
 * = own ∪ reach-visible (published) via the cascade; `prepare` owns the shelf on create + defaults
 * it under the user's root; the shared BaseModelPolicy (ShelfPolicy) is the deny-default write gate.
 */
#[ParticleResource(key: 'shelves', model: Shelf::class, input: ShelfInputData::class, filterable: false)]
class ShelfData extends Data
{
    public function __construct(
        public string $id,
        public ?string $parentId,
        #[Sortable(default: true)]
        public string $name,
        public ?string $visibility,
        public int $bookmarkCount,
    ) {}

    /** Own ∪ reach-visible shelves (published shelves surface to others via scopeForUser). */
    public static function scope(Builder $query): Builder
    {
        return app(ShelfPolicy::class)->scopeForUser($query, Auth::user());
    }

    /** Own the shelf + default it under the user's root before the create write. */
    public static function prepare(Shelf $shelf, mixed $input, mixed $actor): void
    {
        if ($shelf->parent_id === null && $actor !== null) {
            $shelf->parent_id = app(Bookmarks::class)->rootFor($actor)->getKey();
        }
    }

    public static function project(Shelf $shelf): self
    {
        $visibility = $shelf->visibility;

        return new self(
            id: $shelf->id,
            parentId: $shelf->parent_id,
            name: $shelf->name,
            visibility: $visibility instanceof \BackedEnum ? $visibility->value : $visibility,
            bookmarkCount: $shelf->bookmarks()->count(),
        );
    }
}
