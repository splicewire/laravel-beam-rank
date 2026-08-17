<?php

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Splicewire\Beam\Rank\Data\RankData;
use Splicewire\Beam\Rank\Models\Rank;
use Splicewire\Beam\Rank\Models\RankTree;
use Splicewire\Beam\Rank\Ops\RateRank;
use Splicewire\Beam\Rank\Ops\ToggleRank;
use Splicewire\Beam\Rank\Ops\UntoggleRank;
use Splicewire\Beam\Rank\RankType;
use Splicewire\Beam\Rank\Tests\Fixtures\Song;
use Splicewire\Beam\Rank\Tests\Fixtures\User;

/**
 * The Ops layer — the HTTP boundary over `Ranks`.
 *
 * `Ranks` itself is well covered by `RankTreeRankTest`; what had never been exercised is the boundary
 * these four classes actually own: request **validation**, `tree_id` and morph-key **resolution**, and
 * the `RankData` **projection** that is the package's declared output surface. Those are precisely the
 * parts a host reaches through HTTP, and they were the only ones with no test naming them at all.
 *
 * The `bare()` variants matter most here. They exist because a collection-level route has no `{id}`
 * segment, so the target arrives as body morph keys and is resolved through `Relation::getMorphedModel`
 * — a lookup that fails differently from a route-model binding and is invisible to any test that goes
 * through `Ranks` directly.
 */
beforeEach(function () {
    $this->actor = User::create(['name' => 'Actor', 'email' => 'actor@example.test']);
    $this->song = Song::create(['title' => 'A Song']);
});

/** Build a request carrying an authenticated actor, the shape the Ops read. */
function opRequest(array $body, ?User $user = null): Request
{
    $request = Request::create('/', 'POST', $body);
    $request->setUserResolver(fn () => $user ?? test()->actor);

    return $request;
}

it('toggles through the per-model op and projects RankData', function () {
    $data = ToggleRank::handle($this->song, opRequest(['type' => RankType::LIKE]));

    expect($data)->toBeInstanceOf(RankData::class);
    expect(Rank::query()->count())->toBe(1);
});

it('rejects a toggle with no type, at the boundary rather than in the database', function () {
    expect(fn () => ToggleRank::handle($this->song, opRequest([])))
        ->toThrow(ValidationException::class);

    expect(Rank::query()->count())->toBe(0);
});

it('resolves a tree_id through the configured tree model seam', function () {
    // Ownership is stamped by RankTree's boot hook from the authed user (user_type/user_id), not
    // passed in — the same path the HTTP route takes.
    $this->actingAs($this->actor);
    $tree = RankTree::create(['name' => 'Shelf']);

    ToggleRank::handle($this->song, opRequest(['type' => RankType::FAVORITE, 'tree_id' => $tree->id]));

    expect(Rank::query()->first()->tree_id)->toBe($tree->id);
});

it('fails loudly on an unknown tree_id instead of silently filing bare', function () {
    // The dangerous alternative is a null-coalesce that drops an unresolvable tree and files the
    // gesture ungrouped — the row would look successful and land in the wrong place.
    expect(fn () => ToggleRank::handle($this->song, opRequest([
        'type' => RankType::LIKE,
        'tree_id' => '00000000-0000-0000-0000-000000000000',
    ])))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('resolves the target from body morph keys on the bare collection route', function () {
    $data = ToggleRank::bare(opRequest([
        'type' => RankType::LIKE,
        'rankable_type' => 'song',
        'rankable_id' => (string) $this->song->id,
    ]));

    expect($data)->toBeInstanceOf(RankData::class);
    expect(Rank::query()->first()->rankable_id)->toBe((string) $this->song->id);
});

it('fails on an unknown morph alias rather than resolving to nothing', function () {
    expect(fn () => ToggleRank::bare(opRequest([
        'type' => RankType::LIKE,
        'rankable_type' => 'not-a-registered-alias',
        'rankable_id' => (string) $this->song->id,
    // An unregistered alias falls through Relation::getMorphedModel() to a bare class-string lookup,
    // so it surfaces as an Error rather than a ModelNotFoundException — loud either way, which is the
    // property under test: it must not resolve to null and file the gesture against nothing.
    ])))->toThrow(Error::class);
});

it('untoggles through the op, leaving other types on the same target alone', function () {
    ToggleRank::handle($this->song, opRequest(['type' => RankType::LIKE]));
    ToggleRank::handle($this->song, opRequest(['type' => RankType::FAVORITE]));

    UntoggleRank::handle($this->song, opRequest(['type' => RankType::LIKE]));

    expect(Rank::query()->pluck('type')->all())->toBe([RankType::FAVORITE]);
});

it('rates through the op and upserts the single scalar row', function () {
    RateRank::handle($this->song, opRequest(['value' => 7]));
    RateRank::handle($this->song, opRequest(['value' => 3]));

    $rows = Rank::query()->where('type', RankType::RANK)->get();

    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()->value)->toBe(3.0);
});

it('rejects a rate with no value at the boundary', function () {
    expect(fn () => RateRank::handle($this->song, opRequest([])))
        ->toThrow(ValidationException::class);
});
