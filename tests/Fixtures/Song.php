<?php

namespace Splicewire\Beam\Rank\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A plain bigint-keyed model to playlist/bookmark (exercises the cross-host string morph key). */
class Song extends Model
{
    protected $guarded = [];

    protected $table = 'songs';
}
