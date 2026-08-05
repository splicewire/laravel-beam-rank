<?php

use Splicewire\Beam\Bookmarks\Models\Bookmark;
use Splicewire\Beam\Bookmarks\Playlists;
use Splicewire\Beam\Bookmarks\Tests\Fixtures\Song;
use Splicewire\Beam\Bookmarks\Tests\Fixtures\User;

beforeEach(function () {
    $this->pl = app(Playlists::class);
    $this->user = User::create(['name' => 'U', 'email' => 'u@example.test']);
    $this->song = Song::create(['title' => 'A']);
});

it('pins a bookmarkable once (deduped)', function () {
    $this->pl->bookmark($this->user, $this->song);
    $this->pl->bookmark($this->user, $this->song); // dedupe

    expect(Bookmark::query()->count())->toBe(1)
        ->and(Bookmark::query()->first()->bookmarkable_id)->toBe((string) $this->song->id);
});

it('unpins a bookmark', function () {
    $this->pl->bookmark($this->user, $this->song);

    expect($this->pl->unbookmark($this->user, $this->song))->toBe(1)
        ->and(Bookmark::query()->count())->toBe(0);
});
