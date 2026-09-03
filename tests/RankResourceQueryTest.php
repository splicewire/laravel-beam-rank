<?php

use Illuminate\Http\Request;
use Rushing\DataFilters\ServiceProvider as DataFiltersServiceProvider;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Splicewire\Beam\Rank\BeamRankServiceProvider;
use Splicewire\Beam\Rank\Models\Rank;
use Splicewire\Beam\Rank\Query\RankResourceQuery;

/**
 * beam-docs-satellite 65: the `ranks` filterable resource ships its own owner-scoped base query, so a host
 * that mounts it without writing a query of its own gets a SCOPED list rather than a 500 — and a host that
 * registered its own query first keeps it.
 */
beforeEach(function (): void {
    // The harness deliberately does not boot data-filters (it is beam's concern); this file does, because
    // the registration under test is a no-op without it — which would read as a clean pass.
    $this->app->register(DataFiltersServiceProvider::class);
    $this->app->register(BeamRankServiceProvider::class, force: true);
});

it('registers the ranks data-filters resource with the package query', function (): void {
    $registry = app(ResourceRegistry::class);

    expect($registry->has('ranks'))->toBeTrue()
        ->and($registry->get('ranks')->query)->toBe(RankResourceQuery::class)
        ->and($registry->get('ranks')->model)->toBe(Rank::class);
});

it('scopes the base query to the actor, before any user filter runs', function (): void {
    $query = app(\Rushing\DataFilters\DataFilterManager::class)->query('ranks');
    $builder = (new ReflectionMethod($query, 'baseQuery'))->invoke($query, Request::create('/'));
    $wheres = array_column($builder->toBase()->wheres, 'column');

    expect($wheres)->toContain('user_type')->toContain('user_id');
});

it('does not overwrite a ranks query a host registered first', function (): void {
    $registry = app(ResourceRegistry::class);
    $registry->registerDefinition(new \Rushing\DataFilters\Registry\ResourceDefinition(
        key: 'ranks',
        data: \Splicewire\Beam\Rank\Data\RankData::class,
        query: HostRanksQuery::class,
        model: Rank::class,
    ));

    $this->app->register(BeamRankServiceProvider::class, force: true);

    expect($registry->get('ranks')->query)->toBe(HostRanksQuery::class);
});

class HostRanksQuery extends RankResourceQuery {}
