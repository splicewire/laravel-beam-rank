<?php

namespace Splicewire\Beam\Bookmarks\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Splicewire\Beam\Beam;

/**
 * A bookmark (ADR-0009, tracer 09) — THE atom: one `user` saved one `bookmarkable` particle,
 * optionally filed on a {@see Shelf} at a `position`. `shelf_id = null` is the ungrouped "Saved"
 * list; the same particle can be saved bare AND on several shelves (independent rows). Both `user`
 * and `bookmarkable` are string morph keys so the atom holds both uuid- and bigint-keyed hosts.
 *
 * @property string|null $shelf_id
 * @property int|null $position
 */
class Bookmark extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['position' => 'integer'];

    public function getTable(): string
    {
        return Beam::table('bookmarks');
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(config('beam.bookmarks.models.shelf', Shelf::class), 'shelf_id');
    }
}
