<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Beam;

/** The beam_playlists table (adjacency parent_id + visibility) (ADR-0009, tracer 09). Net-new, create-only, current-schema guarded (tenant-safe). */
return new class extends Migration
{
    public function up(): void
    {
        $schema = DB::selectOne('select current_schema() as schema')->schema;
        if ($this->exists($schema, $this->target())) {
            return;
        }
        Schema::create($this->target(), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('parent_id')->nullable()->index();
            $table->string('visibility')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->target());
    }

    private function target(): string
    {
        return Beam::table('playlists');
    }

    private function exists(string $schema, string $table): bool
    {
        return DB::selectOne('select 1 from information_schema.tables where table_schema = ? and table_name = ?', [$schema, $table]) !== null;
    }
};
