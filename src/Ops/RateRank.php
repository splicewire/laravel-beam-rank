<?php

namespace Splicewire\Beam\Rank\Ops;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Splicewire\Beam\Rank\Data\RankData;
use Splicewire\Beam\Rank\Ops\Concerns\ResolvesRankTargets;
use Splicewire\Beam\Rank\Ranks;

/**
 * The rate write operation (Ops convention, ADR-0160/HTTP-10): set the scalar gesture on a target,
 * clamped to explicit or configured bounds by the {@see Ranks} action. One home for the handler
 * logic of both mounts (see {@see ToggleRank} for the per-model vs collection-level split and why
 * the per-model declaration stays a runtime `ParticleOperation`).
 *
 * Output is {@see RankData} — the package's property-case (camelCase) surface convention.
 */
class RateRank
{
    use ResolvesRankTargets;

    public static function handle(Model $rankable, Request $request, mixed $actor = null): RankData
    {
        $data = $request->validate([
            'value' => ['required', 'numeric'],
            'min' => ['nullable', 'numeric'],
            'max' => ['nullable', 'numeric'],
        ]);

        $rank = app(Ranks::class)->rate(
            $request->user(),
            $rankable,
            (float) $data['value'],
            isset($data['min']) ? (float) $data['min'] : null,
            isset($data['max']) ? (float) $data['max'] : null,
        );

        return RankData::project($rank);
    }

    /** The collection-level variant: the rankable target arrives as body morph keys, not `{id}`. */
    public static function bare(Request $request): RankData
    {
        $rankable = self::resolveMorph((string) $request->input('rankable_type'), (string) $request->input('rankable_id'));
        $tree = $request->filled('tree_id') ? self::findTree((string) $request->input('tree_id')) : null;

        $rank = app(Ranks::class)->rate(
            $request->user(),
            $rankable,
            (float) $request->input('value'),
            $request->filled('min') ? (float) $request->input('min') : null,
            $request->filled('max') ? (float) $request->input('max') : null,
            $tree,
        );

        return RankData::project($rank);
    }
}
