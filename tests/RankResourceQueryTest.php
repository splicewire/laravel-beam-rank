<?php

use Illuminate\Http\Request;
use Rushing\DataFilters\Contracts\ResourceModelResolver;
use Rushing\DataFilters\Query\ResourceQuery;
use Rushing\DataFilters\Registry\ResourceDefinition;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Rushing\DataFilters\ServiceProvider as DataFiltersServiceProvider;
use Splicewire\Beam\Filters\ResourceFilterDefinition;
use Splicewire\Beam\Particle\Attributes\AttributedParticleDiscovery;
use Splicewire\Beam\Particle\ParticleListQuery;
use Splicewire\Beam\Particle\ParticleResourceModelResolver;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Rank\BeamRankServiceProvider;
use Splicewire\Beam\Rank\Data\RankData;
use Splicewire\Beam\Rank\Models\Rank;

// Resource metadata supplies filters; the shared list composer always applies the owner scope.
beforeEach(function (): void {
    // The harness deliberately does not boot data-filters (it is beam's concern); this file does, because
    // the registration under test is a no-op without it — which would read as a clean pass.
    $this->app->register(DataFiltersServiceProvider::class);
    $this->app->bind(ResourceModelResolver::class, ParticleResourceModelResolver::class);
    $registry = new ParticleResourceRegistry;
    $this->app->instance(ParticleResourceRegistry::class, $registry);
    $registry->register(AttributedParticleDiscovery::resourceFromAttribute(RankData::class));
    $this->app->register(BeamRankServiceProvider::class, force: true);
});

it('derives the ranks vocabulary from its resource declaration', function (): void {
    $resolver = app(ResourceFilterDefinition::class);
    $definition = $resolver->definition('ranks');

    expect($definition->requireModel())->toBe(Rank::class)
        ->and($resolver->query($definition)->filterNames())->toContain('treeId', 'rankableType', 'type');
});

it('scopes the list to the actor before user filters', function (): void {
    $resource = app(ParticleResourceRegistry::class)->get('ranks');
    $builder = app(ParticleListQuery::class)->forList($resource, ['type' => 'favorite'], Request::create('/', 'GET', ['filter' => ['type' => 'favorite']]));

    expect($builder->toSql())->toContain('user_type')->toContain('user_id')
        ->and($builder->getBindings())->toContain('favorite');
});

it('does not overwrite a ranks query a host registered first', function (): void {
    $registry = app(ResourceRegistry::class);
    $registry->registerDefinition(new ResourceDefinition(
        key: 'ranks',
        data: RankData::class,
        query: HostRanksQuery::class,
        model: Rank::class,
    ));

    $this->app->register(BeamRankServiceProvider::class, force: true);

    expect($registry->get('ranks')->query)->toBe(HostRanksQuery::class);
});

class HostRanksQuery extends ResourceQuery {}
