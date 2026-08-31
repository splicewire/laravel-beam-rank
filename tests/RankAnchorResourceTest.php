<?php

use Splicewire\Beam\Particle\ParticleResource;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Rank\Resources;
use Splicewire\Beam\Rank\Tests\Fixtures\Song;

/**
 * particle-operation-surface 19, RULING 1 — `Resources::attachTo()` declares its `$resourceKey` as a
 * real `ParticleResource` from the same call that registers its three ops, so `{id}` resolves THROUGH
 * the registry rather than through `RecordSubject`'s bare
 * `$operation->model::query()->findOrFail($id)` fallback, which applies none of a resource's `scope`,
 * `routeKey` or `includes`.
 *
 * The gap mattered here specifically. These ops default to `ability: 'view'` — *anyone who can SEE a
 * record may rank it* — and "can see" is precisely what a resource's `scope` closure answers. Asking
 * the `view` policy about a row the resource's own read gate would never have returned is the same
 * shape of divergence that made subject resolution go through the resource in the first place.
 *
 * ⚠️ **These three ops are LATENT, and the record should say so.** Beam's `RecordSubject.php:26-30`
 * counts this factory among a live anchor population of 13+. Swept 2026-08-31 across the real package
 * `src` roots, every `~/Herd` host's `app` and `routes`, and every starter: `Resources::attachTo()` /
 * `Rank::attachTo()` has **zero** call sites. Every hit is a docblock. A factory no host calls
 * declares nothing, so none of these three has ever been registered anywhere, let alone been an
 * anchor.
 *
 * ## ⚠️ This harness does not bind the registry as a singleton, and that had to be fixed to measure
 * anything
 *
 * `ParticleResourceRegistry` is bound `singleton` by `BeamServiceProvider:548`, and this package's
 * `TestCase::getPackageProviders()` deliberately does NOT boot it (beam-rank's suite runs where beam's
 * provider is absent, by design). Without that binding the container **auto-resolves a fresh instance
 * per `app()` call** — measured here: two resolutions returned object ids 1458 and 1453. So
 * `attachTo()` wrote its declaration into one throwaway object and the assertion read another, and the
 * first version of this file failed with "No particle resource registered for key [songs]" against
 * code that is correct at every real host.
 *
 * The `beforeEach` below binds the singleton the way `BeamServiceProvider` does. It is worth stating
 * rather than quietly adding: **`attachTo()`'s anchor is only meaningful where beam's own provider is
 * booted**, which is true of every host and false of this bare package harness.
 */
beforeEach(function () {
    app()->singleton(ParticleResourceRegistry::class);
});

it('declares the resource key it attaches ops to, when nothing else has', function () {
    $resources = app(ParticleResourceRegistry::class);

    expect($resources->has('songs'))->toBeFalse();

    Resources::attachTo('songs', Song::class);

    $resource = $resources->get('songs');

    expect($resource)->toBeInstanceOf(ParticleResource::class)
        ->and($resource->modelClass())->toBe(Song::class);
});

it('opens no affordance on a host-supplied model whose capability it cannot know', function () {
    Resources::attachTo('songs-affordances', Song::class);

    $resource = app(ParticleResourceRegistry::class)->get('songs-affordances');

    // `BackingResolver::assertAffordancesWithinCapability()` THROWS at registration for an affordance
    // opened past a backing's capability, and `$model` arrives from the host. A closed declaration is
    // also the true one: a rank op never writes the ranked model — it writes a `Rank` row against it.
    expect($resource->readOnly)->toBeTrue()
        ->and($resource->editable)->toBeFalse()
        ->and($resource->deletable)->toBeFalse()
        ->and($resource->showable)->toBeFalse()
        ->and($resource->isFramed())->toBeFalse();
});

/**
 * The guard, and why it is not optional. Registering at an already-taken key does NOT throw — it
 * REPLACES, silently. The rank surface is explicitly ADDITIVE to a resource the host already
 * declares (`Rank::attachTo('songs', …)` presumes a real `songs` resource), so without `has()` this
 * factory would be free to overwrite exactly the declaration it is meant to attach to, `scope` gate
 * and all, purely on provider boot order.
 */
it('yields to a declaration the host already made, rather than replacing it', function () {
    $resources = app(ParticleResourceRegistry::class);

    $resources->register(new ParticleResource(
        key: 'songs-host-owned',
        backing: Song::class,
        label: 'Songs',
        scope: fn ($query) => $query->whereNotNull('title'),
    ));

    Resources::attachTo('songs-host-owned', Song::class);

    $resource = $resources->get('songs-host-owned');

    expect($resource->label)->toBe('Songs')
        ->and($resource->isFramed())->toBeTrue()
        ->and($resource->scope)->not->toBeNull()
        ->and($resource->readOnly)->toBeFalse();
});
