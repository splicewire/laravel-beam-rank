<?php

namespace Splicewire\Beam\Bookmarks\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Splicewire\Beam\Bookmarks\Support\Tables;

/**
 * A single-item save/pin (ADR-0009, tracer 09) — the lighter counterpart to a playlist: one
 * `owner` (a User) pins one `bookmarkable` (any model), deduped. Both are string morph keys
 * (cross-host). No hierarchy, no ordering — just "saved / not saved".
 */
class Bookmark extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function getTable(): string
    {
        return Tables::name('bookmarks');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }
}
