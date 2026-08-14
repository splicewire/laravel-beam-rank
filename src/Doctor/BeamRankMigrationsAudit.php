<?php

namespace Splicewire\Beam\Rank\Doctor;

use Splicewire\Beam\Doctor\Support\StubMigrationsAudit;
use Splicewire\Beam\Rank\BeamRankServiceProvider;

class BeamRankMigrationsAudit extends StubMigrationsAudit
{
    protected function packageName(): string
    {
        return 'splicewire/laravel-beam-rank';
    }

    protected function serviceProviderClass(): string
    {
        return BeamRankServiceProvider::class;
    }
}
