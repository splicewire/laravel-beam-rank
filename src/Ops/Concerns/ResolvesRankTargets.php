<?php

namespace Splicewire\Beam\Rank\Ops\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Splicewire\Beam\Rank\Models\RankTree;
use Splicewire\Beam\Rank\Ranks;

/**
 * Shared target resolution for the rank Ops classes: the morph-key → model lookup the
 * collection-level (bare) handlers need, and the tree lookup through the `beam.rank.models.tree`
 * seam — the same seam {@see Ranks} honors, so a host substituting its own
 * tree model is respected on every lookup path.
 */
trait ResolvesRankTargets
{
    protected static function resolveMorph(string $type, string $id): Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        return $class::query()->findOrFail($id);
    }

    protected static function findTree(string $id): RankTree
    {
        return static::treeModel()::query()->findOrFail($id);
    }

    /** @return class-string<RankTree> */
    protected static function treeModel(): string
    {
        return config('beam.rank.models.tree', RankTree::class);
    }
}
