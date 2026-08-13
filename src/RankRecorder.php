<?php

namespace Splicewire\Beam\Rank;

use Splicewire\Beam\Revisions\RevisionRecorder;

/**
 * Rank mutation history, riding beam-core's existing ActivityLog substrate ({@see RevisionRecorder})
 * under its own `log_name` — no new audit infra.
 *
 * Subject = the RANKABLE TARGET, never the ephemeral Rank row: `history($composition)` returns the
 * full ranking activity feed on that target across every actor and type, and the feed survives
 * individual ranks being toggled off (a deleted Rank row would make subject-=-Rank a dead end).
 * `correlation` carries the Rank row's own id so one toggle's lifecycle can still be threaded.
 *
 * Two fixed payload shapes (uniform for history UIs):
 *   - toggle types record existence transitions — create: old `[]`, new `['type' => t]`;
 *     delete: old `['type' => t]`, new `[]`.
 *   - the scalar `rank` type records old→new values — `['value' => previous|null]` →
 *     `['value' => new]`.
 *
 * Recording is config-gated in {@see Ranks} (`beam.rank.log_activity.*`): toggles default OFF
 * (highest-volume event class in a fleet with zero activitylog pruning), rates default ON.
 * ActivityLog is evidence-grade only — explicitly declined as a custody record.
 */
class RankRecorder extends RevisionRecorder
{
    protected function logName(): string
    {
        return 'beam-rank';
    }
}
