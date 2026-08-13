<?php

namespace Splicewire\Beam\Rank;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Rushing\PermissionCascade\Support\Facades\Ownership;
use Splicewire\Beam\Rank\Models\Rank;
use Splicewire\Beam\Rank\Models\RankTree;

/**
 * The Rank + RankTree lifecycle (was Bookmarks): toggle typed gestures onto targets (bare or onto
 * a tree), a per-user root tree, nested trees, ordering, publish. Model classes resolve through
 * `config('beam.rank.models.*')` so a host can subclass. New trees are private by default
 * (visibility null ⇒ steward + grants only via the cascade); publish widens the tier.
 */
class Ranks
{
    /** The user's root tree, provisioned once (user-rooted subtree). */
    public function rootFor(Authenticatable $user): RankTree
    {
        $existing = $this->treeModel()::query()
            ->whereNull('parent_id')
            ->whereUser($user)
            ->first();

        return $existing ?: $this->newTree($user, config('beam.rank.root_name', 'Saved'), null);
    }

    /** Create a tree owned by `$user`, nested under `$parent` (or the user's root by default). */
    public function createTree(Authenticatable $user, string $name, ?RankTree $parent = null): RankTree
    {
        $parent ??= $this->rootFor($user);

        return $this->newTree($user, $name, $parent->getKey());
    }

    /**
     * Toggle a `$type` gesture on `$rankable` for `$user` (deduped on the full unique tuple,
     * including type), optionally onto `$tree` at `$position`. Bare (no tree) is the ungrouped
     * list. Appends at the end of a tree unless `$position` is given.
     */
    public function toggle(Authenticatable $user, Model $rankable, string $type, ?RankTree $tree = null, ?int $position = null): Rank
    {
        $treeId = $tree?->getKey();

        $rank = $this->rankModel()::query()->firstOrNew([
            'user_type' => $this->morphClass($user),
            'user_id' => (string) $user->getAuthIdentifier(),
            'type' => $type,
            'rankable_type' => $rankable->getMorphClass(),
            'rankable_id' => (string) $rankable->getKey(),
            'tree_id' => $treeId,
        ]);

        if (! $rank->exists) {
            $rank->position = $treeId === null ? null : ($position ?? $this->nextPosition($treeId));
            $rank->save();
        } elseif ($position !== null) {
            $rank->update(['position' => $position]);
        }

        return $rank;
    }

    /** Remove a `$type` gesture on `$rankable` for `$user` — from `$tree`, or the bare list when null. */
    public function untoggle(Authenticatable $user, Model $rankable, string $type, ?RankTree $tree = null): int
    {
        return $this->rankModel()::query()
            ->where('user_type', $this->morphClass($user))
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->where('type', $type)
            ->where('rankable_type', $rankable->getMorphClass())
            ->where('rankable_id', (string) $rankable->getKey())
            ->where(fn ($q) => $tree ? $q->where('tree_id', $tree->getKey()) : $q->whereNull('tree_id'))
            ->delete();
    }

    /** Reorder a tree from an ordered list of Rank ids (index ⇒ position). Cosmetic — never logged. */
    public function reorder(RankTree $tree, array $orderedRankIds): void
    {
        foreach (array_values($orderedRankIds) as $i => $id) {
            $this->rankModel()::query()
                ->where('tree_id', $tree->getKey())
                ->whereKey($id)
                ->update(['position' => $i]);
        }
    }

    /** Widen a tree's publication tier (host vocabulary, e.g. 'public'/'platform'). */
    public function publish(RankTree $tree, string $tier): RankTree
    {
        $tree->update(['visibility' => $tier]);

        return $tree;
    }

    private function newTree(Authenticatable $user, string $name, ?string $parentId): RankTree
    {
        $tree = $this->treeModel()::query()->make(['name' => $name, 'parent_id' => $parentId]);

        // Ownership is stamped explicitly from the PASSED user through the blessed seam — the
        // caller-supplied $user may not be the authenticated principal (console/queue contexts);
        // the HasMorphUser creating hook is the convenience default for the raw HTTP path only.
        Ownership::assign($tree, $user);
        $tree->save();

        return $tree->fresh();
    }

    private function nextPosition(string $treeId): int
    {
        return ((int) $this->rankModel()::query()->where('tree_id', $treeId)->max('position')) + 1;
    }

    private function morphClass(object $model): string
    {
        return method_exists($model, 'getMorphClass') ? $model->getMorphClass() : $model::class;
    }

    /** @return class-string<RankTree> */
    private function treeModel(): string
    {
        return config('beam.rank.models.tree', RankTree::class);
    }

    /** @return class-string<Rank> */
    private function rankModel(): string
    {
        return config('beam.rank.models.rank', Rank::class);
    }
}
