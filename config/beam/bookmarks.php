<?php

use Splicewire\Beam\Bookmarks\Models\Bookmark;
use Splicewire\Beam\Bookmarks\Models\Playlist;
use Splicewire\Beam\Bookmarks\Models\PlaylistItem;

return [
    // Host-overridable model bindings (the model-binding seam). A host that subclasses these
    // points the config at its own class; the actions resolve through here.
    'models' => [
        'playlist' => Playlist::class,
        'playlist_item' => PlaylistItem::class,
        'bookmark' => Bookmark::class,
    ],

    // Load the package migrations. A host that publishes/owns its own copies sets this false.
    'register_migrations' => env('BEAM_BOOKMARKS_REGISTER_MIGRATIONS', true),

    // The name of the auto-provisioned per-user root playlist (Option C: user-rooted subtree).
    'root_name' => 'My playlists',
];
