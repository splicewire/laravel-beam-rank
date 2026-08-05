<?php

use Splicewire\Beam\Bookmarks\Models\Playlist;
use Splicewire\Beam\Bookmarks\Playlists;
use Splicewire\Beam\Bookmarks\Tests\Fixtures\PlaylistPolicy;
use Splicewire\Beam\Bookmarks\Tests\Fixtures\Song;
use Splicewire\Beam\Bookmarks\Tests\Fixtures\User;

beforeEach(function () {
    $this->pl = app(Playlists::class);
    $this->policy = new PlaylistPolicy;
    $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test']);
    $this->other = User::create(['name' => 'Other', 'email' => 'other@example.test']);
});

it('provisions a per-user root once, private and owned', function () {
    $root = $this->pl->rootFor($this->owner);
    $again = $this->pl->rootFor($this->owner);

    expect($root->isRoot())->toBeTrue()
        ->and($root->id)->toBe($again->id)
        ->and($root->effectiveVisibility())->toBeNull()          // private by default
        ->and(Playlist::query()->whereNull('parent_id')->count())->toBe(1)
        ->and($this->policy->view($this->owner->fresh(), $root))->toBeTrue()   // steward
        ->and($this->policy->view($this->other->fresh(), $root))->toBeFalse(); // private
});

it('nests a created playlist under the user root, private by default', function () {
    $p = $this->pl->create($this->owner, 'Road trip');

    expect($p->parent_id)->toBe($this->pl->rootFor($this->owner)->id)
        ->and($p->name)->toBe('Road trip')
        ->and($p->effectiveVisibility())->toBeNull();
});

it('adds items ordered and deduped, and reorders', function () {
    $p = $this->pl->create($this->owner, 'Mix');
    $a = Song::create(['title' => 'A']);
    $b = Song::create(['title' => 'B']);

    $ia = $this->pl->addItem($p, $a);
    $ib = $this->pl->addItem($p, $b);
    $dup = $this->pl->addItem($p, $a); // dedupe

    expect($p->items()->count())->toBe(2)
        ->and($dup->id)->toBe($ia->id)
        ->and($p->items()->pluck('playlistable_id')->all())->toBe([(string) $a->id, (string) $b->id]);

    $this->pl->reorder($p, [$ib->id, $ia->id]);
    expect($p->items()->pluck('playlistable_id')->all())->toBe([(string) $b->id, (string) $a->id]);
});

it('removes an item', function () {
    $p = $this->pl->create($this->owner, 'Mix');
    $a = Song::create(['title' => 'A']);
    $this->pl->addItem($p, $a);

    expect($this->pl->removeItem($p, $a))->toBe(1)
        ->and($p->items()->count())->toBe(0);
});

it('publishing widens visibility so any member can view', function () {
    $p = $this->pl->create($this->owner, 'Public mix');
    expect($this->policy->view($this->other->fresh(), $p))->toBeFalse(); // private

    $this->pl->publish($p, 'platform');

    expect($p->fresh()->effectiveVisibility())->toBe('platform')
        ->and($this->policy->view($this->other->fresh(), $p->fresh()))->toBeTrue();
});

it('inherits the effective tier from an ancestor playlist while NULL', function () {
    $parent = $this->pl->create($this->owner, 'Shared folder');
    $this->pl->publish($parent, 'platform');
    $child = $this->pl->create($this->owner, 'Nested', $parent->fresh());

    // child.visibility is NULL → inherits parent's platform → any member views.
    expect($child->fresh()->effectiveVisibility())->toBe('platform')
        ->and($this->policy->view($this->other->fresh(), $child->fresh()))->toBeTrue();
});
