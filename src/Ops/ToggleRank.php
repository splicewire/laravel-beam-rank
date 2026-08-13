<?php

namespace Splicewire\Beam\Rank\Ops;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Splicewire\Beam\Rank\Data\RankData;
use Splicewire\Beam\Rank\Ops\Concerns\ResolvesRankTargets;
use Splicewire\Beam\Rank\Ranks;

/**
 * The toggle write operation (Ops convention, ADR-0160/HTTP-10): attach a typed gesture to a
 * target, deduped on the full unique tuple by the {@see Ranks} action. This class is the ONE home
 * of the handler logic for both mounts:
 *
 *   - per-model (`Rank::attachTo` → `songs/{song}/op/rank-toggle`): {@see self::handle()} rides a
 *     thin runtime `ParticleOperation` built in `Resources::operationsFor()` — the (resource,
 *     model) pair is runtime input, so the declaration can't be a static `#[ParticleOp]` attribute.
 *   - collection-level (`POST ranks/toggle`, target resolved from body morph keys):
 *     {@see self::bare()} behind the bespoke route (no `{id}` segment, so it can't be an op mount).
 *
 * Output is {@see RankData} — the package's property-case (camelCase) surface convention — never a
 * hand-rolled array.
 */
class ToggleRank
{
    use ResolvesRankTargets;

    public static function handle(Model $rankable, Request $request, mixed $actor = null): RankData
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'tree_id' => ['nullable', 'string'],
            'position' => ['nullable', 'integer'],
        ]);
        $tree = isset($data['tree_id']) ? self::findTree($data['tree_id']) : null;

        $rank = app(Ranks::class)->toggle($request->user(), $rankable, $data['type'], $tree, $data['position'] ?? null);

        return RankData::project($rank);
    }

    /** The collection-level variant: the rankable target arrives as body morph keys, not `{id}`. */
    public static function bare(Request $request): RankData
    {
        $rankable = self::resolveMorph((string) $request->input('rankable_type'), (string) $request->input('rankable_id'));
        $tree = $request->filled('tree_id') ? self::findTree((string) $request->input('tree_id')) : null;

        $rank = app(Ranks::class)->toggle(
            $request->user(),
            $rankable,
            (string) $request->input('type'),
            $tree,
            $request->input('position'),
        );

        return RankData::project($rank);
    }
}
