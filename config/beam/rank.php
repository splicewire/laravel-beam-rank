<?php

use Splicewire\Beam\Rank\Models\Rank;
use Splicewire\Beam\Rank\Models\RankTree;
use Splicewire\Beam\Rank\RankType;

return [
    // Host-overridable model bindings (the model-binding seam). A host that subclasses these
    // points the config at its own class; the actions + resources resolve through here.
    'models' => [
        'tree' => RankTree::class,
        'rank' => Rank::class,
    ],

    // Load the package migrations. A host that publishes/owns its own copies sets this false.
    'register_migrations' => env('BEAM_RANK_REGISTER_MIGRATIONS', true),

    // The name of the auto-provisioned per-user root tree (user-rooted subtree). The ungrouped
    // list is bare ranks (tree_id null); the root tree is the parent for named trees.
    'root_name' => 'Saved',

    // Mount the rank-trees/ranks particle resources. A host that wires them itself (or a
    // headless site) sets false.
    'register_resources' => true,

    // Route group for the mounted resources (host convention).
    'resources' => [
        'group_prefix' => 'resources',
        'middleware' => ['web', 'auth'],
    ],

    // Default min/max bounds per SCALAR type, used when a caller doesn't pass bounds explicitly.
    // `value` stays a bare number on the row — a scale is a translation capability on the Ranks
    // action, never a row-level invariant. Keyed by type so a host-minted scalar type can carry
    // its own bounds.
    'scales' => [
        RankType::RANK => ['min' => 0, 'max' => 10],
    ],

    // ActivityLog recording (RankRecorder), gated per event class. Toggle events default OFF —
    // they are the highest-volume event class in the fleet and no activitylog pruning exists
    // anywhere yet; a host wanting a toggle feed ("X liked your song") opts in. Scalar rate
    // old→new diffs are low-volume with standalone product value, so they default ON.
    'log_activity' => [
        'toggle' => env('BEAM_RANK_LOG_TOGGLES', false),
        'rate' => env('BEAM_RANK_LOG_RATES', true),
    ],

    // Table-prefix note: prefixing is beam core's job — the models call Beam::table() directly.
];
