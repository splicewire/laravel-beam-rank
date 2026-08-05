<?php

namespace Splicewire\Beam\Bookmarks\Support;

use Splicewire\Beam\Beam;

/**
 * The table-prefix seam. In a real beam host `splicewire/laravel-beam`'s Beam::table() owns the
 * prefix; standalone (or a host without it) the package falls back to the `beam_` convention via
 * config. Keeps the package installable + testable without dragging laravel-beam's full tree.
 */
class Tables
{
    public static function name(string $name): string
    {
        if (class_exists(Beam::class)) {
            return Beam::table($name);
        }

        return config('beam.bookmarks.table_prefix', 'beam_').$name;
    }
}
