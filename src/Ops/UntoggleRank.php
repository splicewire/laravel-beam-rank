<?php

namespace Splicewire\Beam\Rank\Ops;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Splicewire\Beam\Rank\Data\RankRemovedData;
use Splicewire\Beam\Rank\Ops\Concerns\ResolvesRankTargets;
use Splicewire\Beam\Rank\Ranks;

/**
 * The untoggle write operation (Ops convention, ADR-0160/HTTP-10): remove a typed gesture from a
 * target — from a tree, or the bare list when no tree is named. One home for the handler logic of
 * both mounts (see {@see ToggleRank} for the per-model vs collection-level split and why the
 * per-model declaration stays a runtime `ParticleOperation`).
 *
 * Output is {@see RankRemovedData} (camelCase property-case convention), not a hand-rolled array.
 */
class UntoggleRank
{
    use ResolvesRankTargets;

    public static function handle(Model $rankable, Request $request, mixed $actor = null): RankRemovedData
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'tree_id' => ['nullable', 'string'],
        ]);
        $tree = isset($data['tree_id']) ? self::findTree($data['tree_id']) : null;

        return new RankRemovedData(
            removed: app(Ranks::class)->untoggle($request->user(), $rankable, $data['type'], $tree),
        );
    }

    /** The collection-level variant: the rankable target arrives as body morph keys, not `{id}`. */
    public static function bare(Request $request): RankRemovedData
    {
        $rankable = self::resolveMorph((string) $request->input('rankable_type'), (string) $request->input('rankable_id'));
        $tree = $request->filled('tree_id') ? self::findTree((string) $request->input('tree_id')) : null;

        return new RankRemovedData(
            removed: app(Ranks::class)->untoggle($request->user(), $rankable, (string) $request->input('type'), $tree),
        );
    }
}
