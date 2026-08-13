<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Rushing\DataFilters\Attributes\Filterable;
use Rushing\PermissionCascade\Policies\ConfiguredModelPolicy;
use Splicewire\Beam\Particle\Attributes\ParticleResource;
use Splicewire\Beam\Rank\Data\RankData;
use Splicewire\Beam\Rank\Data\RankTreeData;
use Splicewire\Beam\Rank\Models\Rank;
use Splicewire\Beam\Rank\Models\RankTree;
use Splicewire\Beam\Rank\Ranks;
use Splicewire\Beam\Rank\RankType;
use Splicewire\Beam\Rank\Tests\Fixtures\Song;
use Splicewire\Beam\Rank\Tests\Fixtures\User;

beforeEach(function () {
    $this->ranks = app(Ranks::class);
    $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test']);
    $this->other = User::create(['name' => 'Other', 'email' => 'other@example.test']);
    $this->song = Song::create(['title' => 'A Song']);
});

// ── trees ──────────────────────────────────────────────────────────────────────────────

it('provisions a per-user root tree once (idempotent)', function () {
    $a = $this->ranks->rootFor($this->owner);
    $b = $this->ranks->rootFor($this->owner);

    expect($a->is($b))->toBeTrue()
        ->and($a->isRoot())->toBeTrue()
        ->and(RankTree::query()->whereNull('parent_id')->count())->toBe(1);
});

it('nests a named tree under the root by default', function () {
    $tree = $this->ranks->createTree($this->owner, 'Roadtrip');
    $root = $this->ranks->rootFor($this->owner);

    expect($tree->parent_id)->toBe($root->getKey())->and($tree->name)->toBe('Roadtrip');
});

it('owns a created tree via the morph columns, stamped from the passed user', function () {
    $tree = $this->ranks->createTree($this->owner, 'Mine'); // no one authed — console-safe

    expect($tree->user_type)->toBe($this->owner->getMorphClass())
        ->and((string) $tree->user_id)->toBe((string) $this->owner->getKey())
        ->and($tree->user->is($this->owner))->toBeTrue();
});

it('stamps ownership on a raw authed create via the boot hook (the HTTP-path equivalent)', function () {
    $this->actingAs($this->owner);

    $tree = RankTree::create(['name' => 'Raw']);

    expect($tree->user_type)->toBe($this->owner->getMorphClass())
        ->and((string) $tree->user_id)->toBe((string) $this->owner->getKey());
});

// ── ranks (the unified atom) ───────────────────────────────────────────────────────────

it('toggles a gesture bare (the ungrouped list) — tree_id null, deduped', function () {
    $a = $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE);
    $b = $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE); // idempotent

    expect($a->is($b))->toBeTrue()
        ->and($a->tree_id)->toBeNull()
        ->and($a->type)->toBe('favorite')
        ->and(Rank::query()->count())->toBe(1);
});

it('lets the same actor hold like AND favorite on the same target as two independent rows', function () {
    $like = $this->ranks->toggle($this->owner, $this->song, RankType::LIKE);
    $favorite = $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE);

    expect($like->is($favorite))->toBeFalse()
        ->and(Rank::query()->where('rankable_id', (string) $this->song->id)->count())->toBe(2);
});

it('blocks a duplicate same-type row at the unique tuple', function () {
    $tree = $this->ranks->createTree($this->owner, 'One');
    $this->ranks->toggle($this->owner, $this->song, RankType::LIKE, $tree);

    Rank::create([
        'user_type' => $this->owner->getMorphClass(),
        'user_id' => (string) $this->owner->getKey(),
        'type' => RankType::LIKE,
        'rankable_type' => $this->song->getMorphClass(),
        'rankable_id' => (string) $this->song->id,
        'tree_id' => $tree->getKey(),
    ]);
})->throws(QueryException::class);

it('round-trips a host-minted type string with zero package changes', function () {
    $rank = $this->ranks->toggle($this->owner, $this->song, 'starred');

    expect($rank->fresh()->type)->toBe('starred')
        ->and(Rank::query()->where('type', 'starred')->count())->toBe(1);
});

it('files a rank on a tree with an appended position, deduped', function () {
    $tree = $this->ranks->createTree($this->owner, 'Roadtrip');
    $s2 = Song::create(['title' => 'B']);

    $r1 = $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE, $tree);
    $r2 = $this->ranks->toggle($this->owner, $s2, RankType::FAVORITE, $tree);
    $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE, $tree); // dedupe

    expect($r1->position)->toBe(1)->and($r2->position)->toBe(2)
        ->and(Rank::query()->where('tree_id', $tree->getKey())->count())->toBe(2);
});

it('lets the same gesture live bare AND on several trees (independent rows)', function () {
    $t1 = $this->ranks->createTree($this->owner, 'One');
    $t2 = $this->ranks->createTree($this->owner, 'Two');

    $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE);       // bare
    $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE, $t1);  // tree 1
    $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE, $t2);  // tree 2

    expect(Rank::query()->where('rankable_id', (string) $this->song->id)->count())->toBe(3);
});

it('untoggles only the targeted row (bare vs tree), per type', function () {
    $tree = $this->ranks->createTree($this->owner, 'One');
    $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE);       // bare
    $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE, $tree);
    $this->ranks->toggle($this->owner, $this->song, RankType::LIKE);           // different type, bare

    $this->ranks->untoggle($this->owner, $this->song, RankType::FAVORITE);     // remove bare favorite only

    expect(Rank::query()->whereNull('tree_id')->where('type', 'favorite')->count())->toBe(0)
        ->and(Rank::query()->where('tree_id', $tree->getKey())->count())->toBe(1)
        ->and(Rank::query()->where('type', 'like')->count())->toBe(1);
});

it('reorders a tree from an ordered id list', function () {
    $tree = $this->ranks->createTree($this->owner, 'One');
    $a = $this->ranks->toggle($this->owner, Song::create(['title' => 'a']), RankType::FAVORITE, $tree);
    $b = $this->ranks->toggle($this->owner, Song::create(['title' => 'b']), RankType::FAVORITE, $tree);

    $this->ranks->reorder($tree, [$b->id, $a->id]);

    expect($a->fresh()->position)->toBe(1)->and($b->fresh()->position)->toBe(0);
});

// ── vocabulary ─────────────────────────────────────────────────────────────────────────

it('ships the nine OTB type constants', function () {
    expect(RankType::LIKE)->toBe('like')
        ->and(RankType::DISLIKE)->toBe('dislike')
        ->and(RankType::FAVORITE)->toBe('favorite')
        ->and(RankType::FRIEND)->toBe('friend')
        ->and(RankType::FOLLOW)->toBe('follow')
        ->and(RankType::IGNORE)->toBe('ignore')
        ->and(RankType::SILENCE)->toBe('silence')
        ->and(RankType::BLOCK)->toBe('block')
        ->and(RankType::RANK)->toBe('rank');
});

// ── authorization (cascade-policy attributes — no policy classes) ──────────────────────

it('authorizes both models through their cascade-policy attributes with no policy class', function () {
    expect(Gate::getPolicyFor(RankTree::class))->toBeInstanceOf(ConfiguredModelPolicy::class)
        ->and(Gate::getPolicyFor(Rank::class))->toBeInstanceOf(ConfiguredModelPolicy::class);

    $tree = $this->ranks->createTree($this->owner, 'Mine');

    // RankTree: self-service create (the attribute's create: true), steward-managed thereafter.
    expect(Gate::forUser($this->owner)->allows('create', RankTree::class))->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('update', $tree))->toBeTrue()
        ->and(Gate::forUser($this->other)->allows('update', $tree))->toBeFalse();

    // Rank: NO create override — a raw store is deny-default (rows ride the Ranks action).
    expect(Gate::forUser($this->owner)->allows('create', Rank::class))->toBeFalse();
});

// ── visibility / publish ───────────────────────────────────────────────────────────────

it('publishes a tree by widening its visibility tier', function () {
    $tree = $this->ranks->createTree($this->owner, 'Public one');
    expect($tree->visibility)->toBeNull();

    $this->ranks->publish($tree, 'platform');

    expect($tree->fresh()->visibility)->toBe('platform');
});

it('inherits a published ancestor tier down the tree chain (cascade)', function () {
    $parent = $this->ranks->createTree($this->owner, 'Parent');
    $this->ranks->publish($parent, 'platform');
    $child = $this->ranks->createTree($this->owner, 'Child', $parent);

    // child has no explicit tier → inherits parent's 'platform' → any member may view.
    expect(Gate::forUser($this->other)->allows('view', $child->fresh()))->toBeTrue();
});

// ── particle resource scope/project (read/hydrate) ─────────────────────────────────────

it('scopes trees to own ∪ published, hiding others private trees', function () {
    $mine = $this->ranks->createTree($this->owner, 'Mine');
    $published = $this->ranks->createTree($this->owner, 'Public');
    $this->ranks->publish($published, 'platform');
    $privateOfOwner = $this->ranks->createTree($this->owner, 'Secret');

    $this->actingAs($this->other);
    $ids = RankTreeData::scope(RankTree::query())->pluck('id')->all();

    expect($ids)->toContain($published->id)
        ->and($ids)->not->toContain($mine->id, $privateOfOwner->id);
});

it('projects a tree with its rank count', function () {
    $tree = $this->ranks->createTree($this->owner, 'One');
    $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE, $tree);

    $data = RankTreeData::project($tree->fresh());

    expect($data->name)->toBe('One')->and($data->rankCount)->toBe(1);
});

it('declares the ranks resource filterable with treeId + rankableType + type facets (data-filters)', function () {
    $resource = (new ReflectionClass(RankData::class))
        ->getAttributes(ParticleResource::class)[0]->newInstance();
    expect($resource->filterable)->toBeTrue()
        ->and($resource->key)->toBe('ranks');

    $facets = [];
    foreach ((new ReflectionClass(RankData::class))->getConstructor()->getParameters() as $p) {
        if ($p->getAttributes(Filterable::class)) {
            $facets[] = $p->getName();
        }
    }
    expect($facets)->toContain('treeId', 'rankableType', 'type');

    // The underlying membership (what the type facet filters on) is still exact at the model.
    $this->ranks->toggle($this->owner, $this->song, RankType::LIKE);
    $this->ranks->toggle($this->owner, $this->song, RankType::FAVORITE);
    expect(Rank::query()->where('type', 'favorite')->count())->toBe(1)
        ->and(Rank::query()->where('type', 'like')->count())->toBe(1);
});
