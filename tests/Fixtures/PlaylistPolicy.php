<?php

namespace Splicewire\Beam\Bookmarks\Tests\Fixtures;

use Rushing\PermissionCascade\Policies\BaseModelPolicy;
use Splicewire\Beam\Bookmarks\Models\Playlist;

class PlaylistPolicy extends BaseModelPolicy
{
    public static $defaultModelClass = Playlist::class;
}
