<?php

use Splicewire\Beam\Bookmarks\Models\Bookmark;
use Splicewire\Beam\Bookmarks\Models\Shelf;

return [
    // Host-overridable model bindings (the model-binding seam). A host that subclasses these
    // points the config at its own class; the actions + resources resolve through here.
    'models' => [
        'shelf' => Shelf::class,
        'bookmark' => Bookmark::class,
    ],

    // Load the package migrations. A host that publishes/owns its own copies sets this false.
    'register_migrations' => env('BEAM_BOOKMARKS_REGISTER_MIGRATIONS', true),

    // The name of the auto-provisioned per-user root shelf (Option C: user-rooted subtree). The
    // "Saved" list is bare bookmarks (shelf_id null); the root shelf is the parent for named shelves.
    'root_name' => 'Saved',

    // Mount the shelves/bookmarks particle resources (index/store/destroy + reorder). A host that
    // wires them itself (or a headless site) sets false.
    'register_resources' => true,

    // Route group for the mounted resources (host convention).
    'resources' => [
        'group_prefix' => 'resources',
        'middleware' => ['web', 'auth'],
    ],

    // Table-prefix note: prefixing is beam core's job — the models call Beam::table() directly.
];
