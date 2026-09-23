<?php

namespace Splicewire\Beam\Rank\Tests;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Rushing\DataFilters\Facades\DataFilter;
use Rushing\DataFilters\Query\ResourceQuery;
use Rushing\DataFilters\ServiceProvider as DataFiltersServiceProvider;
use Rushing\PermissionCascade\Support\PermissionNamer;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;
use Schemastud\DataSchemas\LaravelDataSchemasServiceProvider;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\FrameServiceProvider;
use Schemastud\Frame\Registry\CompositeResourceRegistry;
use Spatie\Permission\Models\Permission;
use Splicewire\Beam\BeamServiceProvider;
use Splicewire\Beam\Frame\ParticleResourceRegistryAdapter;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Rank\Data\RankData;
use Splicewire\Beam\Rank\Models\Rank;
use Splicewire\Beam\Rank\Models\RankTree;
use Splicewire\Beam\Rank\Ranks;
use Splicewire\Beam\Rank\RankType;
use Splicewire\Beam\Rank\Tests\Fixtures\Song;
use Splicewire\Beam\Rank\Tests\Fixtures\User;

class RankFrameResourceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PopcornServiceProvider::class,
            LaravelDataSchemasServiceProvider::class,
            FrameServiceProvider::class,
            DataFiltersServiceProvider::class,
            BeamServiceProvider::class,
            ...parent::getPackageProviders($app),
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('beam.rank.register_resources', true);
        $app['config']->set('frame.middleware', ['web', 'auth']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Project the real declarations through Frame's existing producer slot without
        // changing the current Particle adapter's presentation-based eligibility.
        foreach (['ranks', 'rank-trees'] as $key) {
            $resource = app(ParticleResourceRegistry::class)->get($key);
            app(CompositeResourceRegistry::class)->register($resource->toResourceDefinition());
        }
    }

    private function user(string $name): User
    {
        return User::create(['name' => $name, 'email' => strtolower($name).'@example.test']);
    }

    public static function lifecycles(): array
    {
        return [
            'ranks' => ['ranks', [false, false, true, false]],
            'rank trees' => ['rank-trees', [true, true, true, false]],
        ];
    }

    #[DataProvider('lifecycles')]
    public function test_the_declarations_express_the_supported_resource_lifecycle(string $key, array $expected): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertFalse(app(ParticleResourceRegistryAdapter::class)->has($key));
        $definition = app(ResourceRegistry::class)->get($key);
        $this->assertSame($expected, [$definition->creatable, $definition->editable, $definition->deletable, $definition->showable]);
    }

    public function test_own_lists_are_available_without_opening_record_details(): void
    {
        $actor = $this->user('Ada');
        $tree = app(Ranks::class)->createTree($actor, 'Mine');
        $rank = app(Ranks::class)->toggle($actor, Song::create(['title' => 'One']), RankType::FAVORITE, $tree);
        $this->actingAs($actor)->getJson('/frame/resources/ranks')->assertOk()
            ->assertJsonPath('data.0.id', $rank->getKey());
        $this->getJson('/frame/resources/rank-trees')->assertOk();

        $rankDetail = $this->getJson('/frame/resources/ranks/records/'.$rank->getKey());
        $treeDetail = $this->getJson('/frame/resources/rank-trees/records/'.$tree->getKey());
        $this->assertSame([405, 405], [$rankDetail->status(), $treeDetail->status()]);
    }

    public function test_a_guest_scope_does_not_match_an_empty_owner_identity(): void
    {
        $actor = $this->user('Ada');
        $rank = app(Ranks::class)->toggle($actor, Song::create(['title' => 'One']), RankType::FAVORITE);
        DB::table($rank->getTable())->where('id', $rank->getKey())->update(['user_id' => '']);

        $this->assertNull(auth()->user());
        $this->assertSame([], RankData::scope(Rank::query())->get()->modelKeys());
        $this->actingAs(new User(['name' => 'Without an identifier']));
        $this->assertSame([], RankData::scope(Rank::query())->get()->modelKeys());
    }

    public function test_generic_rank_update_cannot_bypass_the_gesture_actions(): void
    {
        $actor = $this->user('Ada');
        $rank = app(Ranks::class)->toggle($actor, Song::create(['title' => 'One']), RankType::FAVORITE);
        $before = $rank->fresh()->getRawOriginal();
        $this->actingAs($actor)->getJson('/frame/resources/ranks')->assertOk();

        $response = $this->putJson('/frame/resources/ranks/records/'.$rank->getKey(), ['type' => RankType::RANK, 'value' => 42]);
        $this->assertSame($before, $rank->fresh()->getRawOriginal());
        $response->assertStatus(405);
    }

    public function test_rank_lists_follow_the_current_actor_and_match_both_owner_columns(): void
    {
        $ada = $this->user('Ada');
        $bo = $this->user('Bo');
        $song = Song::create(['title' => 'One']);
        $adaFavorite = app(Ranks::class)->toggle($ada, $song, RankType::FAVORITE);
        $adaLike = app(Ranks::class)->toggle($ada, $song, RankType::LIKE);
        $boFavorite = app(Ranks::class)->toggle($bo, $song, RankType::FAVORITE);
        $otherMorph = $boFavorite->replicate();
        $otherMorph->user_type = 'role';
        $otherMorph->user_id = (string) $ada->getKey();
        $otherMorph->save();

        $response = $this->actingAs($ada)->getJson('/frame/resources/ranks')->assertOk()->assertJsonPath('total', 2);
        $this->assertEqualsCanonicalizing([$adaFavorite->getKey(), $adaLike->getKey()], $response->json('data.*.id'));
        $this->actingAs($bo)->getJson('/frame/resources/ranks')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $boFavorite->getKey());
    }

    public function test_host_favorite_query_intersects_owner_and_filters_across_pages_and_summary(): void
    {
        $ada = $this->user('Ada');
        $bo = $this->user('Bo');
        $tree = app(Ranks::class)->createTree($ada, 'Mine');
        $foreignTree = app(Ranks::class)->createTree($bo, 'Other');
        $ids = [];
        foreach (['One', 'Two', 'Three'] as $title) {
            $song = Song::create(['title' => $title]);
            $ids[] = app(Ranks::class)->toggle($ada, $song, RankType::FAVORITE, $tree)->getKey();
        }
        app(Ranks::class)->toggle($ada, $song, RankType::LIKE, $tree);
        app(Ranks::class)->toggle($bo, $song, RankType::FAVORITE, $foreignTree);
        DataFilter::resource('ranks', [
            'data' => RankData::class,
            'query' => FavoriteRanksQuery::class,
            'model' => Rank::class,
        ]);

        $query = http_build_query(['filter' => ['type' => 'favorite', 'rankableType' => 'song'], 'per_page' => 2]);
        $first = $this->actingAs($ada)->getJson('/frame/resources/ranks?'.$query)->assertOk()
            ->assertJsonPath('page', 1)->assertJsonPath('perPage', 2)->assertJsonPath('total', 3);
        $second = $this->getJson('/frame/resources/ranks?'.$query.'&page=2')->assertOk()
            ->assertJsonPath('page', 2)->assertJsonPath('perPage', 2)->assertJsonPath('total', 3);
        $this->assertSame(array_slice($ids, 0, 2), $first->json('data.*.id'));
        $this->assertSame([$ids[2]], $second->json('data.*.id'));
        $this->getJson('/frame/resources/ranks?filter[type]=like')->assertOk()
            ->assertJsonPath('data', [])->assertJsonPath('total', 0);
        $this->getJson('/frame/resources/ranks?filter[treeId]='.$foreignTree->getKey())->assertOk()
            ->assertJsonPath('data', [])->assertJsonPath('total', 0);
        $this->getJson('/frame/resources/ranks?filter[treeId]='.$tree->getKey())->assertOk()
            ->assertJsonPath('total', 3);
        $this->getJson('/frame/resources/ranks/summary')->assertOk()->assertJsonPath('figures.0.value', 3);

        app(Ranks::class)->toggle($bo, Song::create(['title' => 'Foreign addition']), RankType::FAVORITE);
        $this->getJson('/frame/resources/ranks/summary')->assertOk()->assertJsonPath('figures.0.value', 3);
    }

    public function test_only_the_owner_can_delete_a_rank_and_generic_create_stays_closed(): void
    {
        $ada = $this->user('Ada');
        $bo = $this->user('Bo');
        $song = Song::create(['title' => 'One']);
        $own = app(Ranks::class)->toggle($ada, $song, RankType::FAVORITE);
        $foreign = app(Ranks::class)->toggle($bo, $song, RankType::FAVORITE);
        $before = Rank::orderBy('id')->get()->map->getRawOriginal()->all();
        $this->actingAs($ada)->getJson('/frame/resources/ranks')->assertOk()
            ->assertJsonPath('data.0.id', $own->getKey());
        $this->postJson('/frame/resources/ranks', ['type' => 'like', 'rankable_type' => 'song', 'rankable_id' => $song->getKey()])
            ->assertForbidden();
        $this->deleteJson('/frame/resources/ranks/records/'.$foreign->getKey())->assertForbidden();
        $this->assertSame($before, Rank::orderBy('id')->get()->map->getRawOriginal()->all());

        $this->deleteJson('/frame/resources/ranks/records/'.$own->getKey())->assertNoContent();
        $this->getJson('/frame/resources/ranks')->assertOk()->assertJsonPath('data', []);
        $this->assertSame([$foreign->getKey()], Rank::all()->modelKeys());
    }

    public function test_tree_create_rename_publish_and_unpublish_use_the_declared_lifecycle(): void
    {
        $ada = $this->user('Ada');
        $bo = $this->user('Bo');
        $created = $this->actingAs($ada)->postJson('/frame/resources/rank-trees', ['name' => 'Mine'])->assertOk();
        $id = $created->json('data.id');
        $tree = RankTree::findOrFail($id);
        $root = app(Ranks::class)->rootFor($ada);
        $this->assertSame($root->getKey(), $tree->parent_id);
        $this->assertSame($ada->getMorphClass(), $tree->user_type);
        $this->assertSame((string) $ada->getKey(), $tree->user_id);
        $created->assertJsonPath('data.parentId', $root->getKey())->assertJsonPath('data.rankCount', 0);

        $url = '/frame/resources/rank-trees/records/'.$id;
        $this->putJson($url, ['name' => 'Published', 'visibility' => 'platform'])->assertOk();
        $this->assertContains($id, $this->actingAs($bo)->getJson('/frame/resources/rank-trees')->assertOk()->json('data.*.id'));
        $this->actingAs($ada)->putJson($url, ['name' => 'Renamed'])->assertOk();
        $this->assertSame('platform', $tree->fresh()->visibility);
        $this->putJson($url, ['name' => 'Private again', 'visibility' => null])->assertOk();
        $this->assertNull($tree->fresh()->visibility);
        $this->assertNotContains($id, $this->actingAs($bo)->getJson('/frame/resources/rank-trees')->assertOk()->json('data.*.id'));
        $ownList = $this->actingAs($ada)->getJson('/frame/resources/rank-trees')->assertOk()->json('data');
        $this->assertSame('Private again', collect($ownList)->firstWhere('id', $id)['name']);
    }

    public function test_published_tree_reads_do_not_grant_foreign_mutation(): void
    {
        $ada = $this->user('Ada');
        $bo = $this->user('Bo');
        $published = app(Ranks::class)->createTree($ada, 'Published');
        app(Ranks::class)->publish($published, 'platform');
        $private = app(Ranks::class)->createTree($ada, 'Private');
        $boTree = app(Ranks::class)->createTree($bo, 'Other');
        $before = RankTree::orderBy('id')->get()->map->getRawOriginal()->all();
        $ids = $this->actingAs($bo)->getJson('/frame/resources/rank-trees')->assertOk()->json('data.*.id');
        $this->assertContains($published->getKey(), $ids);
        $this->assertContains($boTree->getKey(), $ids);
        $this->assertNotContains($private->getKey(), $ids);
        $this->getJson('/frame/resources/rank-trees/summary')->assertOk()->assertJsonPath('figures.0.value', 3);
        foreach ([$published, $private] as $tree) {
            $url = '/frame/resources/rank-trees/records/'.$tree->getKey();
            $this->putJson($url, ['name' => 'Changed'])->assertForbidden();
            $this->deleteJson($url)->assertForbidden();
        }
        $this->assertSame($before, RankTree::orderBy('id')->get()->map->getRawOriginal()->all());
        $this->actingAs($ada)->deleteJson('/frame/resources/rank-trees/records/'.$published->getKey())->assertNoContent();
        $this->assertNull($published->fresh());
        $this->assertNotNull($boTree->fresh());
    }

    public function test_rank_metadata_requires_the_existing_permission_then_exposes_only_its_vocabulary(): void
    {
        $ada = $this->user('Ada');
        $rank = app(Ranks::class)->toggle($ada, Song::create(['title' => 'One']), RankType::FAVORITE);
        $calls = 0;
        DataFilter::options('other-owners', function () use (&$calls): array {
            $calls++;

            return [['value' => 'other', 'label' => 'Other actor']];
        });
        $this->actingAs($ada)->getJson('/frame/resources/ranks')->assertOk()->assertJsonPath('data.0.id', $rank->getKey());
        // Current shared limitation: the owner-scoped list is readable, but metadata additionally
        // asks the model's viewAny policy. Ticket 17 tracks reconciling that capability boundary.
        $this->getJson('/frame/resources/ranks/filters/schema')->assertForbidden();
        $this->getJson('/frame/resources/ranks/filters/options/other-owners')->assertForbidden();
        $this->assertSame(0, $calls);
        $permission = app(PermissionNamer::class)->assemble(Rank::class, 'own', 'view');
        $ada->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $properties = $this->getJson('/frame/resources/ranks/filters/schema')->assertOk()->json('data.properties');
        $filters = array_filter($properties, fn (array $property): bool => isset($property['x-filter']));
        $this->assertEqualsCanonicalizing(['rankableType', 'treeId', 'type'], array_keys($filters));
        $this->getJson('/frame/resources/ranks/filters/users/schema')->assertNotFound();
        $this->getJson('/frame/resources/ranks/filters/options/other-owners')->assertNotFound();
        $this->assertSame(0, $calls);
    }

    public function test_guest_sockets_refuse_reads_metadata_and_writes(): void
    {
        $ada = $this->user('Ada');
        $rank = app(Ranks::class)->toggle($ada, Song::create(['title' => 'One']), RankType::FAVORITE);
        $tree = app(Ranks::class)->createTree($ada, 'Mine');
        foreach (['ranks' => $rank, 'rank-trees' => $tree] as $key => $record) {
            foreach (['', '/summary', '/filters/schema', '/filters/variants', '/filters/options/other-owners'] as $suffix) {
                $this->getJson('/frame/resources/'.$key.$suffix)->assertUnauthorized();
            }
            $this->postJson('/frame/resources/'.$key, ['name' => 'Injected'])->assertUnauthorized();
            $this->putJson('/frame/resources/'.$key.'/records/'.$record->getKey(), ['name' => 'Changed'])->assertUnauthorized();
            $this->deleteJson('/frame/resources/'.$key.'/records/'.$record->getKey())->assertUnauthorized();
        }
        $this->assertSame([$rank->getKey()], Rank::all()->modelKeys());
        $this->assertSame('Mine', $tree->fresh()->name);
    }
}

class FavoriteRanksQuery extends ResourceQuery
{
    protected function baseQuery(Request $request): Builder
    {
        // Deliberately omit owner predicates: the resource declaration must supply that boundary.
        return Rank::query()->where('type', RankType::FAVORITE);
    }
}
