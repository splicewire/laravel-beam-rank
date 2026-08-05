<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Beam;

/**
 * `beam_bookmarks` — THE atom (ADR-0009, tracer 09): a user saved a particle, optionally filed on
 * a shelf at a position. `shelf_id = null` is the ungrouped "Saved" list. Morph keys are strings
 * (cross-host uuid/bigint). Unique per (user, bookmarkable, shelf) so a particle appears once per
 * shelf and once bare. Net-new, create-only, current-schema guarded (tenant-safe).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = DB::selectOne('select current_schema() as schema')->schema;
        if ($this->exists($schema, $this->target())) {
            return;
        }

        Schema::create($this->target(), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->string('user_id');
            $table->string('bookmarkable_type');
            $table->string('bookmarkable_id');
            $table->uuid('shelf_id')->nullable()->index();
            $table->unsignedInteger('position')->nullable();
            $table->timestamps();

            $table->index(['user_type', 'user_id']);
            $table->index(['bookmarkable_type', 'bookmarkable_id']);
            $table->unique(['user_type', 'user_id', 'bookmarkable_type', 'bookmarkable_id', 'shelf_id'], 'bookmarks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->target());
    }

    private function target(): string
    {
        return Beam::table('bookmarks');
    }

    private function exists(string $schema, string $table): bool
    {
        return DB::selectOne('select 1 from information_schema.tables where table_schema = ? and table_name = ?', [$schema, $table]) !== null;
    }
};
