<?php

namespace Splicewire\Beam\Bookmarks;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Splicewire\Beam\Bookmarks\Models\Bookmark;
use Splicewire\Beam\Bookmarks\Models\Playlist;
use Splicewire\Beam\Bookmarks\Models\PlaylistItem;

/**
 * The playlist + bookmark lifecycle (ADR-0009, tracer 09): a per-user root, nested playlists,
 * ordered/deduped items, single-item bookmarks. Model classes resolve through
 * `config('beam.bookmarks.models.*')` so a host can subclass. Ownership is the permission-cascade
 * userable pivot; new playlists are private by default (visibility null ⇒ steward + grants only).
 */
class Playlists
{
    /** The user's root container playlist, provisioned once (Option C: user-rooted subtree). */
    public function rootFor(Authenticatable $user): Playlist
    {
        $existing = $this->playlistModel()::query()
            ->whereNull('parent_id')
            ->whereHas('user', fn ($q) => $q->where('user_id', $user->getAuthIdentifier()))
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->newOwned($user, config('beam.bookmarks.root_name', 'My playlists'), null);
    }

    /** Create a playlist owned by `$user`, nested under `$parent` (or the user's root by default). */
    public function create(Authenticatable $user, string $name, ?Playlist $parent = null): Playlist
    {
        $parent ??= $this->rootFor($user);

        return $this->newOwned($user, $name, $parent->getKey());
    }

    /** Add `$itemable` to `$playlist` (deduped); appends at the end unless `$position` is given. */
    public function addItem(Playlist $playlist, Model $itemable, ?int $position = null): PlaylistItem
    {
        $item = $this->itemModel()::query()->firstOrNew([
            'playlist_id' => $playlist->getKey(),
            'playlistable_type' => $itemable->getMorphClass(),
            'playlistable_id' => (string) $itemable->getKey(),
        ]);

        if (! $item->exists) {
            $item->position = $position ?? (((int) $this->itemModel()::query()->where('playlist_id', $playlist->getKey())->max('position')) + 1);
            $item->save();
        } elseif ($position !== null) {
            $item->update(['position' => $position]);
        }

        return $item;
    }

    public function removeItem(Playlist $playlist, Model $itemable): int
    {
        return $this->itemModel()::query()
            ->where('playlist_id', $playlist->getKey())
            ->where('playlistable_type', $itemable->getMorphClass())
            ->where('playlistable_id', (string) $itemable->getKey())
            ->delete();
    }

    /** Reorder a playlist from an ordered list of PlaylistItem ids (index ⇒ position). */
    public function reorder(Playlist $playlist, array $orderedItemIds): void
    {
        foreach (array_values($orderedItemIds) as $i => $id) {
            $this->itemModel()::query()
                ->where('playlist_id', $playlist->getKey())
                ->whereKey($id)
                ->update(['position' => $i]);
        }
    }

    /** Widen a playlist's publication tier (host vocabulary, e.g. 'public'/'platform'). */
    public function publish(Playlist $playlist, string $tier): Playlist
    {
        $playlist->update(['visibility' => $tier]);

        return $playlist;
    }

    /** Pin `$bookmarkable` for `$user` (deduped). */
    public function bookmark(Authenticatable $user, Model $bookmarkable): Bookmark
    {
        return $this->bookmarkModel()::query()->firstOrCreate([
            'owner_type' => $this->morphClass($user),
            'owner_id' => (string) $user->getAuthIdentifier(),
            'bookmarkable_type' => $bookmarkable->getMorphClass(),
            'bookmarkable_id' => (string) $bookmarkable->getKey(),
        ]);
    }

    public function unbookmark(Authenticatable $user, Model $bookmarkable): int
    {
        return $this->bookmarkModel()::query()
            ->where('owner_type', $this->morphClass($user))
            ->where('owner_id', (string) $user->getAuthIdentifier())
            ->where('bookmarkable_type', $bookmarkable->getMorphClass())
            ->where('bookmarkable_id', (string) $bookmarkable->getKey())
            ->delete();
    }

    private function newOwned(Authenticatable $user, string $name, ?string $parentId): Playlist
    {
        $playlist = $this->playlistModel()::query()->create(['name' => $name, 'parent_id' => $parentId]);
        $playlist->user()->syncWithoutDetaching([$user->getAuthIdentifier()]);

        return $playlist->fresh();
    }

    private function morphClass(object $model): string
    {
        return method_exists($model, 'getMorphClass') ? $model->getMorphClass() : $model::class;
    }

    /** @return class-string<Playlist> */
    private function playlistModel(): string
    {
        return config('beam.bookmarks.models.playlist', Playlist::class);
    }

    private function itemModel(): string
    {
        return config('beam.bookmarks.models.playlist_item', PlaylistItem::class);
    }

    private function bookmarkModel(): string
    {
        return config('beam.bookmarks.models.bookmark', Bookmark::class);
    }
}
