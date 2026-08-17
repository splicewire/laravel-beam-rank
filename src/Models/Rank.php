<?php

namespace Splicewire\Beam\Rank\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Rushing\PermissionCascade\Attributes\UseCascadePolicy;
use Rushing\PermissionCascade\Concerns\HasMorphUser;
use Rushing\PermissionCascade\Policies\BaseModelPolicy;
use Splicewire\Beam\Facades\Beam;
use Splicewire\Beam\Rank\RankType;
use Splicewire\Beam\Rank\Resources;

/**
 * A Rank (was Bookmark) — THE atom: one actor (`user`, via permission-cascade HasMorphUser)
 * attached one typed gesture to one `rankable` target, optionally filed on a {@see RankTree} at a
 * `position`. `tree_id = null` is the ungrouped list; `type` is open vocabulary ({@see RankType}
 * ships the OTB constants); `value` is the scalar payload carried only by rows of type
 * {@see RankType::RANK}. Both `user` and `rankable` are string morph keys so the atom holds both
 * uuid- and bigint-keyed hosts.
 *
 * The attribute below is the ENTIRE authorization surface — no Policy class exists anywhere for
 * this model. No `create` override: Rank rows are only ever created via the `Ranks` action (or
 * the bespoke toggle/rate operations), never a raw HTTP `store`.
 *
 * @property string $type
 * @property string|null $tree_id
 * @property int|null $position
 * @property float|null $value
 */
#[UseCascadePolicy(BaseModelPolicy::class)]
class Rank extends Model
{
    use HasMorphUser;
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['position' => 'integer', 'value' => 'float'];

    public function getTable(): string
    {
        return Beam::table('ranks');
    }

    /**
     * Mount toggle/untoggle/rate operations scoped to one host model with a single call —
     * `Rank::attachTo('songs', Composition::class)` → `songs/{song}/op/rank-toggle` etc.
     * ADDITIVE to the global `/resources/ranks` surface, never a replacement: a per-model mount
     * can't answer "all of my likes across every type and target", so both stay available.
     * Mirrors `laravel-beam-accounts`'s `Sharing::attachTo()`; delegates to
     * {@see Resources::attachTo()}.
     *
     * @param  class-string<Model>  $model
     */
    public static function attachTo(string $resourceKey, string $model, array $opts = []): void
    {
        Resources::attachTo($resourceKey, $model, $opts);
    }

    public function rankable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tree(): BelongsTo
    {
        return $this->belongsTo(config('beam.rank.models.tree', RankTree::class), 'tree_id');
    }
}
