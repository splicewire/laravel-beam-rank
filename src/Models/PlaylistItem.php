<?php

namespace Splicewire\Beam\Bookmarks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Splicewire\Beam\Bookmarks\Support\Tables;

/**
 * An ordered, deduped entry on a {@see Playlist} (ADR-0009, tracer 09). `playlistable` is any
 * model (a Composition, etc.) — a string morph key so it holds both uuid- and bigint-keyed hosts.
 * Uniqueness (playlist × playlistable) is enforced at the table; `position` orders within a playlist.
 *
 * @property int $position
 */
class PlaylistItem extends Model
{
    protected $guarded = [];

    protected $casts = ['position' => 'integer'];

    public function getTable(): string
    {
        return Tables::name('playlist_items');
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class, 'playlist_id');
    }

    public function playlistable(): MorphTo
    {
        return $this->morphTo();
    }
}
