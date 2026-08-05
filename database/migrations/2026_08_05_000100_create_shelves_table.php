<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Beam;

/**
 * `beam_shelves` — a named, nestable, shareable grouping of bookmarks (ADR-0009, tracer 09).
 * Net-new, create-only, current-schema guarded (tenant-safe). `visibility` is the permission-cascade
 * reach tier (host vocabulary); `parent_id` is the adjacency parent (nested under a per-user root).
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
            $table->string('name');
            $table->string('visibility')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->target());
    }

    private function target(): string
    {
        return Beam::table('shelves');
    }

    private function exists(string $schema, string $table): bool
    {
        return DB::selectOne('select 1 from information_schema.tables where table_schema = ? and table_name = ?', [$schema, $table]) !== null;
    }
};
