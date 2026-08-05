<?php

namespace Splicewire\Beam\Bookmarks\Data;

use Spatie\LaravelData\Data;

/** The shelf write shape (create/rename/publish): only these are mass-fillable. */
class ShelfInputData extends Data
{
    public function __construct(
        public string $name,
        public ?string $visibility = null,
        public ?string $parentId = null,
    ) {}
}
