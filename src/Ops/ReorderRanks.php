<?php

namespace Splicewire\Beam\Rank\Ops;

use Illuminate\Http\Request;
use Splicewire\Beam\Particle\Attributes\ParticleOp;
use Splicewire\Beam\Particle\OperationKind;
use Splicewire\Beam\Rank\Data\RankTreeReorderData;
use Splicewire\Beam\Rank\Models\RankTree;
use Splicewire\Beam\Rank\Ranks;

/**
 * The tree reorder write op — a `#[ParticleOp]` class (ADR-0160/HTTP-10, the beam-media
 * DownloadMedia/IngestMedia idiom): declaration + handler co-located, mounted at
 * `POST rank-trees/{id}/op/reorder` with no provider glue by
 * `Route::particleOps('rank-trees', 'rank-trees', [ReorderRanks::class])`. Positions come from the
 * caller's ordered rank-id list (index ⇒ position); the write is cosmetic and never logged
 * ({@see Ranks::reorder()}).
 */
#[ParticleOp(
    resource: 'rank-trees',
    name: 'reorder',
    kind: OperationKind::Write,
    model: RankTree::class,
    ability: 'update',
    output: RankTreeReorderData::class,
)]
class ReorderRanks
{
    public static function handle(RankTree $tree, Request $request, mixed $actor = null): RankTreeReorderData
    {
        $ids = (array) $request->input('ids', []);

        app(Ranks::class)->reorder($tree, $ids);

        return new RankTreeReorderData(id: (string) $tree->getKey(), ordered: count($ids));
    }
}
