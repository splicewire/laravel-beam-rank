<?php

namespace Splicewire\Beam\Bookmarks\Tests;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\PermissionCascade\PermissionCascadeServiceProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Splicewire\Beam\Bookmarks\BeamBookmarksServiceProvider;
use Splicewire\Beam\Bookmarks\Models\Playlist;
use Splicewire\Beam\Bookmarks\Models\PlaylistItem;
use Splicewire\Beam\Bookmarks\Support\Tables;
use Splicewire\Beam\Bookmarks\Tests\Fixtures\Song;
use Splicewire\Beam\Bookmarks\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Relation::enforceMorphMap([
            'user' => User::class,
            'song' => Song::class,
            'playlist' => Playlist::class,
            'playlist_item' => PlaylistItem::class,
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
            BeamBookmarksServiceProvider::class,
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
        // Single-tenant test: teams off, and don't run the Postgres-only migrations (schema is hand-built below).
        $c->set('permission-cascade.manage_spatie_teams', false);
        $c->set('permission.teams', false);
        $c->set('beam.bookmarks.register_migrations', false);
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

        // permission-cascade HasUser ownership pivot.
        Schema::create('userables', function (Blueprint $t): void {
            $t->unsignedBigInteger('user_id');
            $t->string('userable_type');
            $t->string('userable_id');
        });

        Schema::create(Tables::name('playlists'), function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->uuid('parent_id')->nullable()->index();
            $t->string('visibility')->nullable();
            $t->timestamps();
        });

        Schema::create(Tables::name('playlist_items'), function (Blueprint $t): void {
            $t->id();
            $t->uuid('playlist_id')->index();
            $t->string('playlistable_type');
            $t->string('playlistable_id');
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
            $t->unique(['playlist_id', 'playlistable_type', 'playlistable_id']);
        });

        Schema::create(Tables::name('bookmarks'), function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('owner_type');
            $t->string('owner_id');
            $t->string('bookmarkable_type');
            $t->string('bookmarkable_id');
            $t->timestamps();
            $t->unique(['owner_type', 'owner_id', 'bookmarkable_type', 'bookmarkable_id']);
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
