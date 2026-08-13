<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Beam;

/**
 * `beam_rank_trees` — a named, nestable, orderable, shareable grouping of Ranks (was
 * `beam_shelves`). Net-new, create-only, current-schema guarded (tenant-safe). `visibility` is the
 * permission-cascade reach tier (host vocabulary); `parent_id` is the adjacency parent (nested
 * under a per-user root). NEW versus the shelf shape: `user_type`/`user_id` — single owner via
 * direct morph columns (permission-cascade HasMorphUser), replacing the multi-owner `userables`
 * pivot the Shelf rode.
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
            $table->uuid('parent_id')->nullable()->index();
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->string('name');
            $table->string('visibility')->nullable()->index();
            $table->timestamps();

            $table->index(['user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->target());
    }

    private function target(): string
    {
        return Beam::table('rank_trees');
    }

    private function exists(string $schema, string $table): bool
    {
        return DB::selectOne('select 1 from information_schema.tables where table_schema = ? and table_name = ?', [$schema, $table]) !== null;
    }
};
