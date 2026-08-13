<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Beam;

/**
 * `beam_ranks` — THE atom (was `beam_bookmarks`): one actor attached one typed gesture to one
 * `rankable` target, optionally filed on a RankTree at a `position`. `tree_id = null` is the
 * ungrouped list. Morph keys are strings (cross-host uuid/bigint).
 *
 * `type` is the open-vocabulary gesture column (like/dislike/favorite/friend/follow/ignore/
 * silence/block/rank — a host adds its own string), and it JOINS the uniqueness tuple: the same
 * actor can independently hold `like` AND `favorite` on the same target as two distinct rows.
 * `value` is the scalar payload carried only by rows of the scalar `rank` type (a "Rank row of
 * type rank" — toggle types never touch it). Net-new, create-only, current-schema guarded.
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
            $table->string('type')->index();
            $table->string('rankable_type');
            $table->string('rankable_id');
            $table->uuid('tree_id')->nullable()->index();
            $table->unsignedInteger('position')->nullable();
            $table->double('value')->nullable();
            $table->timestamps();

            $table->index(['user_type', 'user_id']);
            $table->index(['rankable_type', 'rankable_id']);
            $table->unique(['user_type', 'user_id', 'type', 'rankable_type', 'rankable_id', 'tree_id'], 'ranks_unique');
        });

        // `ranks_unique` never fires for UNGROUPED rows — `tree_id` is in the tuple and NULLs
        // compare distinct — which would leave bare same-type dedup resting solely on the
        // application's firstOrNew (a concurrent-toggle race). A partial unique index closes
        // it where the driver supports one (pgsql + sqlite — the fleet's two drivers); an
        // unsupported driver keeps the app-side dedup only.
        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'create unique index ranks_unique_untreed on '.$this->target()
                .' (user_type, user_id, type, rankable_type, rankable_id) where tree_id is null'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists($this->target());
    }

    private function target(): string
    {
        return Beam::table('ranks');
    }

    private function exists(string $schema, string $table): bool
    {
        return DB::selectOne('select 1 from information_schema.tables where table_schema = ? and table_name = ?', [$schema, $table]) !== null;
    }
};
