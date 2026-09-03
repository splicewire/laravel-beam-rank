<?php

namespace Splicewire\Beam\Rank\Query;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Rushing\DataFilters\Query\ResourceQuery;
use Splicewire\Beam\Rank\Data\RankData;

/**
 * The base query behind the `ranks` data-filters resource — the one {@see RankData}'s `#[ParticleResource]`
 * promises by being `filterable` and, until beam-docs-satellite 65, never shipped.
 *
 * ## The gap 65 measured
 *
 * `ParticleController::index()` applies a resource's `scope` closure only on the NON-filterable path; a
 * filterable resource's whole read gate is its data-filters base query. {@see RankData::scope()} declares
 * the owner constraint — `user_type` + `user_id` of the actor — and its docblock then said *"the host binds
 * the actual owner row-gate in its `ResourceQuery` (audiostud's `App\Read\RanksQuery::baseQuery`)"*. That
 * is a package NOMINATING a gate a host must AUTHORIZE, and any host that mounted `ranks` without writing
 * that class got a 500 (`No data-filters resource is registered under [ranks]`) rather than a scoped list.
 *
 * api-surface-coherence 135's posture — a read falls through to the SCOPE — presupposes a scope exists at
 * the tier that declares the resource. So the scope moves here: the `scope()` the Data class already
 * declared is CONSUMED (it was vocabulary with no reader on this path), exactly as beam-core's
 * `HookResourceQuery` reads `HookData::scope()`. Nothing is restated; the day `RankData::scope()` changes,
 * this query moves with it.
 *
 * ## The host still wins
 *
 * `BeamRankServiceProvider` registers this under `ranks` only when nothing else has — audiostud's
 * `RankServiceProvider` binds its narrower `RanksQuery` (owner + `type = favorite`) over it, and a
 * `DataFilter::resource('ranks', [...])` with config overwrites plainly. This class is the FLOOR every
 * host inherits, not a ceiling.
 */
class RankResourceQuery extends ResourceQuery
{
    protected function baseQuery(Request $request): Builder
    {
        return RankData::scope(($this->definition->requireModel())::query());
    }
}
