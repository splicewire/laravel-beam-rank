<?php

namespace Splicewire\Beam\Rank\Tests\Doctor;

use Splicewire\Beam\Doctor\Testing\AssertsStubMigrations;
use Splicewire\Beam\Rank\Doctor\BeamRankMigrationsAudit;
use Splicewire\Beam\Rank\Tests\TestCase;

/**
 * beam-rank's own operator check: its migrations must stay publish-only `.stub` files. Mirrors every
 * sibling's version of this test — a thin wrapper over the shared {@see AssertsStubMigrations} engine
 * that declares only "which audit is mine."
 *
 * Filled because rank was the **one** beam-* package shipping a `*MigrationsAudit` with nothing
 * exercising it: the audit class existed, was registered, and had never been run in a test. An audit
 * nobody runs is a convention nobody enforces — and this one guards the publish-only stub convention
 * that the whole estate's install path depends on.
 */
class BeamRankMigrationsAuditTest extends TestCase
{
    use AssertsStubMigrations;

    public function test_beam_rank_migrations_are_publish_only_stubs(): void
    {
        $this->assertMigrationsArePublishOnlyStubs();
    }

    protected function stubMigrationsAuditClass(): string
    {
        return BeamRankMigrationsAudit::class;
    }
}
