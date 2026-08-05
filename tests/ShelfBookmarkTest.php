<?php

use Splicewire\Beam\Bookmarks\Bookmarks;
use Splicewire\Beam\Bookmarks\Data\BookmarkData;
use Splicewire\Beam\Bookmarks\Data\ShelfData;
use Splicewire\Beam\Bookmarks\Models\Bookmark;
use Splicewire\Beam\Bookmarks\Models\Shelf;
use Splicewire\Beam\Bookmarks\Policies\ShelfPolicy;
use Splicewire\Beam\Bookmarks\Tests\Fixtures\Song;
use Splicewire\Beam\Bookmarks\Tests\Fixtures\User;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

beforeEach(function () {
    $this->bm = app(Bookmarks::class);
    $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test']);
    $this->other = User::create(['name' => 'Other', 'email' => 'other@example.test']);
    $this->song = Song::create(['title' => 'A Song']);
});

// ── shelves ────────────────────────────────────────────────────────────────────────────

it('provisions a per-user root shelf once (idempotent)', function () {
    $a = $this->bm->rootFor($this->owner);
    $b = $this->bm->rootFor($this->owner);

    expect($a->is($b))->toBeTrue()
        ->and($a->isRoot())->toBeTrue()
        ->and(Shelf::query()->whereNull('parent_id')->count())->toBe(1);
});

it('nests a named shelf under the root by default', function () {
    $shelf = $this->bm->createShelf($this->owner, 'Roadtrip');
    $root = $this->bm->rootFor($this->owner);

    expect($shelf->parent_id)->toBe($root->getKey())->and($shelf->name)->toBe('Roadtrip');
});

// ── bookmarks (the unified atom) ────────────────────────────────────────────────────────

it('saves a particle bare (the Saved list) — shelf_id null, deduped', function () {
    $a = $this->bm->save($this->owner, $this->song);
    $b = $this->bm->save($this->owner, $this->song); // idempotent

    expect($a->is($b))->toBeTrue()
        ->and($a->shelf_id)->toBeNull()
        ->and(Bookmark::query()->count())->toBe(1);
});

it('files a particle on a shelf with an appended position, deduped', function () {
    $shelf = $this->bm->createShelf($this->owner, 'Roadtrip');
    $s2 = Song::create(['title' => 'B']);

    $b1 = $this->bm->save($this->owner, $this->song, $shelf);
    $b2 = $this->bm->save($this->owner, $s2, $shelf);
    $this->bm->save($this->owner, $this->song, $shelf); // dedupe

    expect($b1->position)->toBe(1)->and($b2->position)->toBe(2)
        ->and(Bookmark::query()->where('shelf_id', $shelf->getKey())->count())->toBe(2);
});

it('lets the same particle live bare AND on several shelves (independent rows)', function () {
    $s1 = $this->bm->createShelf($this->owner, 'One');
    $s2 = $this->bm->createShelf($this->owner, 'Two');

    $this->bm->save($this->owner, $this->song);       // bare
    $this->bm->save($this->owner, $this->song, $s1);  // shelf 1
    $this->bm->save($this->owner, $this->song, $s2);  // shelf 2

    expect(Bookmark::query()->where('bookmarkable_id', (string) $this->song->id)->count())->toBe(3);
});

it('unsaves only the targeted row (bare vs shelf)', function () {
    $shelf = $this->bm->createShelf($this->owner, 'One');
    $this->bm->save($this->owner, $this->song);       // bare
    $this->bm->save($this->owner, $this->song, $shelf);

    $this->bm->unsave($this->owner, $this->song);     // remove bare only

    expect(Bookmark::query()->whereNull('shelf_id')->count())->toBe(0)
        ->and(Bookmark::query()->where('shelf_id', $shelf->getKey())->count())->toBe(1);
});

it('reorders a shelf from an ordered id list', function () {
    $shelf = $this->bm->createShelf($this->owner, 'One');
    $a = $this->bm->save($this->owner, Song::create(['title' => 'a']), $shelf);
    $b = $this->bm->save($this->owner, Song::create(['title' => 'b']), $shelf);

    $this->bm->reorder($shelf, [$b->id, $a->id]);

    expect($a->fresh()->position)->toBe(1)->and($b->fresh()->position)->toBe(0);
});

// ── visibility / publish ────────────────────────────────────────────────────────────────

it('publishes a shelf by widening its visibility tier', function () {
    $shelf = $this->bm->createShelf($this->owner, 'Public one');
    expect($shelf->visibility)->toBeNull();

    $this->bm->publish($shelf, 'platform');

    expect($shelf->fresh()->visibility)->toBe('platform');
});

it('inherits a published ancestor tier down the shelf chain (cascade)', function () {
    $parent = $this->bm->createShelf($this->owner, 'Parent');
    $this->bm->publish($parent, 'platform');
    $child = $this->bm->createShelf($this->owner, 'Child', $parent);

    // child has no explicit tier → inherits parent's 'platform' → any member may view.
    expect(app(ShelfPolicy::class)->view($this->other, $child->fresh()))->toBeTrue();
});

// ── particle resource scope/project (read/hydrate) ─────────────────────────────────────

it('scopes shelves to own ∪ published, hiding others private shelves', function () {
    $mine = $this->bm->createShelf($this->owner, 'Mine');
    $published = $this->bm->createShelf($this->owner, 'Public');
    $this->bm->publish($published, 'platform');
    $privateOfOwner = $this->bm->createShelf($this->owner, 'Secret');

    $this->actingAs($this->other);
    $ids = ShelfData::scope(Shelf::query())->pluck('id')->all();

    expect($ids)->toContain($published->id)
        ->and($ids)->not->toContain($mine->id, $privateOfOwner->id);
});

it('projects a shelf with its bookmark count', function () {
    $shelf = $this->bm->createShelf($this->owner, 'One');
    $this->bm->save($this->owner, $this->song, $shelf);

    $data = ShelfData::project($shelf->fresh());

    expect($data->name)->toBe('One')->and($data->bookmarkCount)->toBe(1);
});

it('declares the bookmarks resource filterable with shelfId + bookmarkableType facets (data-filters)', function () {
    // Filtering rides the particle stack's data-filters mechanism, not a hand-rolled scope: the
    // resource is filterable and declares its facets via #[Filterable]. The end-to-end
    // ?filter[shelfId]=X HTTP proof (+ the host's owner-scoping data-filters query) is tracer 10.
    $resource = (new ReflectionClass(BookmarkData::class))
        ->getAttributes(ParticleResource::class)[0]->newInstance();
    expect($resource->filterable)->toBeTrue();

    $facets = [];
    foreach ((new ReflectionClass(BookmarkData::class))->getConstructor()->getParameters() as $p) {
        if ($p->getAttributes(Rushing\DataFilters\Attributes\Filterable::class)) {
            $facets[] = $p->getName();
        }
    }
    expect($facets)->toContain('shelfId', 'bookmarkableType');

    // The underlying membership (what the shelfId facet filters on) is still exact at the model.
    $shelf = $this->bm->createShelf($this->owner, 'One');
    $this->bm->save($this->owner, $this->song);                            // bare (Saved)
    $this->bm->save($this->owner, Song::create(['title' => 'x']), $shelf);  // on the shelf
    expect(Bookmark::query()->where('shelf_id', $shelf->getKey())->count())->toBe(1)
        ->and(Bookmark::query()->whereNull('shelf_id')->count())->toBe(1);
});
