<?php

namespace Splicewire\Beam\Rank\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Splicewire\Beam\Http\Particle\ParticleController;
use Splicewire\Beam\Rank\Ranks;
use Splicewire\Beam\Write\Contracts\MapsToModelAttributes;

/**
 * The rank-tree write shape (create/rename/publish/unpublish): only these are mass-fillable.
 *
 * ## The three input states, and which fields can tell them apart
 *
 * A write body can say three different things about a field: it can be *absent* ("leave it alone"),
 * *present-and-null* ("clear it"), or *present with a value*. A promoted property written
 * `public ?T $x = null` can only ever express TWO of them — `DefaultValuesDataPipe` checks
 * `hasDefaultValue` BEFORE `type->isOptional`, so the declared default wins and an absent field
 * arrives as `null`, indistinguishable from a submitted one. On a `!== null` gate the two collapse,
 * and the collapse is one-directional: the column can be set and can never be cleared.
 *
 *   - **`visibility`** is `string|Optional|null` with NO `= null` default (the default is the
 *     sentinel itself). Removing the `= null` is the whole fix; putting it back makes the `Optional`
 *     arm unreachable again. Absent ⇒ untouched · present-and-null ⇒ written as null · value ⇒
 *     written. This is the field where the distinction matters most, and the reason is a safety one:
 *     `visibility` IS the publication tier, null means private ({@see Ranks} — "new trees are private
 *     by default, visibility null ⇒ steward + grants only via the cascade"), and {@see Ranks::publish()}
 *     only ever WIDENS. An explicit `visibility: null` on this write is therefore the ONLY unpublish
 *     there is — and until this conversion it was silently dropped, so a caller who believed they had
 *     made a tree private again left it platform-visible.
 *   - **`name`** is required and NOT NULL in `create_rank_trees_table`. There is nothing to clear.
 *   - **`parentId`** is nullable in the column and is STILL held back, deliberately.
 *     {@see RankTreeData::prepare()} runs on UPDATE as well as create
 *     ({@see ParticleController::updateParticle()}) and re-roots any null `parent_id` under the
 *     actor's root tree, so a "cleared" parent could never persist as null — converting it would
 *     advertise a capability the write pipeline immediately undoes. On create a null still means
 *     "root me", exactly as before.
 */
class RankTreeInputData extends Data implements MapsToModelAttributes
{
    public function __construct(
        public string $name,
        /**
         * The publication tier — nullable in the column, and CLEARABLE, because clearing it is the
         * unpublish. `string|Optional|null` with no `= null` default, so an absent field is the
         * `Optional` sentinel and an explicit null is a real null that reaches the column. See the class
         * docblock; do not restore the default.
         */
        public string|Optional|null $visibility = new Optional,
        public ?string $parentId = null,
    ) {}

    /**
     * The beam write seam ({@see ParticleController::toAttributes}) fills the model from THIS, not the
     * camelCase `toArray()` — so map the DTO props to the snake_case `beam_rank_trees` columns.
     *
     * Two gates, deliberately. `name`/`parent_id` drop their nulls, so a partial write (a rename) never
     * clobbers unspecified fields and a `null` `parentId` on create is left for
     * {@see RankTreeData::prepare} to root. `visibility` is gated on PRESENCE instead, because an
     * explicit null there is a caller unpublishing the tree and that is the one thing the null-dropping
     * gate cannot express.
     *
     * Explicit per-field checks, never `get_object_vars`, which would leak `Optional` sentinels onto the
     * write.
     *
     * @return array<string, mixed>
     */
    public function toModelAttributes(): array
    {
        $attributes = array_filter([
            'name' => $this->name,
            'parent_id' => $this->parentId,
        ], fn ($value) => $value !== null);

        // Absent ⇒ leave the column alone. Present ⇒ write it, INCLUDING a null, which unpublishes the
        // tree back to steward-plus-grants. See the property's note.
        if (! $this->visibility instanceof Optional) {
            $attributes['visibility'] = $this->visibility;
        }

        return $attributes;
    }
}
