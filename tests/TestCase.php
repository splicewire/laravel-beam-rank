<?php

namespace Splicewire\Beam\Rank\Tests;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\PermissionCascade\PermissionCascadeServiceProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Splicewire\Beam\Beam;
use Splicewire\Beam\Rank\BeamRankServiceProvider;
use Splicewire\Beam\Rank\Models\Rank;
use Splicewire\Beam\Rank\Models\RankTree;
use Splicewire\Beam\Rank\Tests\Fixtures\Song;
use Splicewire\Beam\Rank\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Relation::enforceMorphMap([
            'user' => User::class,
            'song' => Song::class,
            'rank-tree' => RankTree::class,
            'rank' => Rank::class,
            'role' => Role::class,
        ]);

        $this->createSpatieSchema();
        $this->createFixtureSchema();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            PermissionCascadeServiceProvider::class,
            BeamRankServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $c = $app['config'];
        $c->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $c->set('database.default', 'testing');
        $c->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $c->set('auth.providers.users.model', User::class);
        $c->set('permission-cascade.user_model', User::class);
        // Single-tenant test: teams off; the package migrations are Postgres-guarded (schema is hand-built below).
        $c->set('permission-cascade.manage_spatie_teams', false);
        $c->set('permission.teams', false);
        $c->set('beam.rank.register_migrations', false);
        // The particle surface needs laravel-beam's route macros (absent here) — Resources::register no-ops.
        $c->set('beam.rank.register_resources', false);
    }

    protected function createFixtureSchema(): void
    {
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->timestamps();
        });

        Schema::create('songs', function (Blueprint $t): void {
            $t->id();
            $t->string('title')->nullable();
            $t->timestamps();
        });

        Schema::create(Beam::table('rank_trees'), function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('parent_id')->nullable()->index();
            $t->string('user_type')->nullable();
            $t->string('user_id')->nullable();
            $t->string('name');
            $t->string('visibility')->nullable()->index();
            $t->timestamps();
            $t->index(['user_type', 'user_id']);
        });

        Schema::create(Beam::table('ranks'), function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('user_type');
            $t->string('user_id');
            $t->string('type')->index();
            $t->string('rankable_type');
            $t->string('rankable_id');
            $t->uuid('tree_id')->nullable()->index();
            $t->unsignedInteger('position')->nullable();
            $t->double('value')->nullable();
            $t->timestamps();
            $t->unique(['user_type', 'user_id', 'type', 'rankable_type', 'rankable_id', 'tree_id'], 'ranks_unique');
        });
    }

    protected function createSpatieSchema(): void
    {
        Schema::create('permissions', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });
        Schema::create('model_has_permissions', function (Blueprint $t): void {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
            $t->primary(['permission_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_roles', function (Blueprint $t): void {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
            $t->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('role_has_permissions', function (Blueprint $t): void {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['permission_id', 'role_id']);
        });
    }
}
