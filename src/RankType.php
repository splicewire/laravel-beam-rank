<?php

namespace Splicewire\Beam\Rank;

/**
 * The out-of-the-box rank-type vocabulary — string constants, deliberately NOT a backed enum
 * (matching the taste `RankTree::visibility` set: an open column, host vocabulary). A host mints
 * its own type by passing any string; nothing here needs to change.
 *
 * TERMINOLOGY COLLISION, on purpose: "a Rank row" is any row in `beam_ranks`, of any type;
 * {@see RankType::RANK} is the one SCALAR type (a numeric rating on a translatable min/max
 * scale). Always disambiguate in prose: "a Rank row of type `rank`".
 *
 * {@see RankType::FRIEND} and {@see RankType::FOLLOW} are both UNILATERAL declarations —
 * `follow` subscribes attention, `friend` declares affinity. Mutual friendship is emergent (two
 * reciprocal rows); an unreciprocated row is a one-way friend, not a pending request. No consent
 * state ships anywhere in the `ranks` schema — a host needing approve/deny (friend handshake,
 * private-account follow) manages the Rank under a `splicewire/laravel-beam-workflows` workflow,
 * never a status column here. Full glossary: the package `CONTEXT.md`.
 */
class RankType
{
    public const LIKE = 'like';

    public const DISLIKE = 'dislike';

    public const FAVORITE = 'favorite';

    public const FRIEND = 'friend';

    public const FOLLOW = 'follow';

    public const IGNORE = 'ignore';

    public const SILENCE = 'silence';

    public const BLOCK = 'block';

    /** The scalar type: its rows carry a numeric `value` on a translatable min/max scale. */
    public const RANK = 'rank';
}
