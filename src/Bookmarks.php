<?php

namespace Splicewire\Beam\Bookmarks;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Splicewire\Beam\Bookmarks\Models\Bookmark;
use Splicewire\Beam\Bookmarks\Models\Shelf;

/**
 * The bookmark + shelf lifecycle (ADR-0009, tracer 09): save particles (bare or onto a shelf),
 * a per-user root shelf, nested shelves, ordering, publish. Model classes resolve through
 * `config('beam.bookmarks.models.*')` so a host can subclass. New shelves are private by default
 * (visibility null ⇒ steward + grants only via the cascade); publish widens the tier.
 */
class Bookmarks
{
    /** The user's root shelf, provisioned once (Option C: user-rooted subtree). */
    public function rootFor(Authenticatable $user): Shelf
    {
        $existing = $this->shelfModel()::query()
            ->whereNull('parent_id')
            ->whereHas('user', fn ($q) => $q->where('user_id', $user->getAuthIdentifier()))
            ->first();

        return $existing ?: $this->newShelf($user, config('beam.bookmarks.root_name', 'Saved'), null);
    }

    /** Create a shelf owned by `$user`, nested under `$parent` (or the user's root by default). */
    public function createShelf(Authenticatable $user, string $name, ?Shelf $parent = null): Shelf
    {
        $parent ??= $this->rootFor($user);

        return $this->newShelf($user, $name, $parent->getKey());
    }

    /**
     * Save `$bookmarkable` for `$user` (deduped), optionally onto `$shelf` at `$position`. Bare
     * (no shelf) is the "Saved" list. Appends at the end of a shelf unless `$position` is given.
     */
    public function save(Authenticatable $user, Model $bookmarkable, ?Shelf $shelf = null, ?int $position = null): Bookmark
    {
        $shelfId = $shelf?->getKey();

        $bookmark = $this->bookmarkModel()::query()->firstOrNew([
            'user_type' => $this->morphClass($user),
            'user_id' => (string) $user->getAuthIdentifier(),
            'bookmarkable_type' => $bookmarkable->getMorphClass(),
            'bookmarkable_id' => (string) $bookmarkable->getKey(),
            'shelf_id' => $shelfId,
        ]);

        if (! $bookmark->exists) {
            $bookmark->position = $shelfId === null ? null : ($position ?? $this->nextPosition($shelfId));
            $bookmark->save();
        } elseif ($position !== null) {
            $bookmark->update(['position' => $position]);
        }

        return $bookmark;
    }

    /** Remove `$bookmarkable` for `$user` — from `$shelf`, or the bare "Saved" list when null. */
    public function unsave(Authenticatable $user, Model $bookmarkable, ?Shelf $shelf = null): int
    {
        return $this->bookmarkModel()::query()
            ->where('user_type', $this->morphClass($user))
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->where('bookmarkable_type', $bookmarkable->getMorphClass())
            ->where('bookmarkable_id', (string) $bookmarkable->getKey())
            ->where(fn ($q) => $shelf ? $q->where('shelf_id', $shelf->getKey()) : $q->whereNull('shelf_id'))
            ->delete();
    }

    /** Reorder a shelf from an ordered list of Bookmark ids (index ⇒ position). */
    public function reorder(Shelf $shelf, array $orderedBookmarkIds): void
    {
        foreach (array_values($orderedBookmarkIds) as $i => $id) {
            $this->bookmarkModel()::query()
                ->where('shelf_id', $shelf->getKey())
                ->whereKey($id)
                ->update(['position' => $i]);
        }
    }

    /** Widen a shelf's publication tier (host vocabulary, e.g. 'public'/'platform'). */
    public function publish(Shelf $shelf, string $tier): Shelf
    {
        $shelf->update(['visibility' => $tier]);

        return $shelf;
    }

    private function newShelf(Authenticatable $user, string $name, ?string $parentId): Shelf
    {
        $shelf = $this->shelfModel()::query()->create(['name' => $name, 'parent_id' => $parentId]);
        $shelf->user()->syncWithoutDetaching([$user->getAuthIdentifier()]);

        return $shelf->fresh();
    }

    private function nextPosition(string $shelfId): int
    {
        return ((int) $this->bookmarkModel()::query()->where('shelf_id', $shelfId)->max('position')) + 1;
    }

    private function morphClass(object $model): string
    {
        return method_exists($model, 'getMorphClass') ? $model->getMorphClass() : $model::class;
    }

    /** @return class-string<Shelf> */
    private function shelfModel(): string
    {
        return config('beam.bookmarks.models.shelf', Shelf::class);
    }

    /** @return class-string<Bookmark> */
    private function bookmarkModel(): string
    {
        return config('beam.bookmarks.models.bookmark', Bookmark::class);
    }
}
