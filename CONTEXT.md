# Rank

The social-interaction substrate of the Beam family: typed actor→target declarations plus a scalar
rating, organized into nestable trees. (Renamed in place from `laravel-beam-bookmarks`, history
preserved — bookmarks-to-rank ticket 05.)

## Language

**Rank**:
A typed, unilateral declaration by an actor about a target. The substrate's single edge concept —
both the toggle vocabulary and the scalar rating are Ranks.
_Avoid_: interaction, reaction, edge (in domain prose)

**Friend**:
A unilateral affinity declaration toward another user. Not a handshake — it carries no consent
state and requires nothing from the target.
_Avoid_: friendship request, connection

**Follow**:
A unilateral attention declaration — subscribing to a target's activity.
_Avoid_: subscribe, watch

**Mutual friendship**:
The emergent condition of two reciprocal Friend ranks. Never a stored state of its own.
_Avoid_: accepted friendship, pending friendship

**Consent**:
Approval or denial of a social gesture. Outside Rank's domain entirely: a consensual flow (friend
request/accept, private-account follow approval) is a workflow managed *over* a Rank, never a
status stored *on* one.
_Avoid_: pending, accepted, denied (as Rank states)

**Ownership**:
The materialized current-state fact of who owns a record — always answerable from the record's own
columns, never by walking a chain. What steward and "mine" listings read.
_Avoid_: custody (for current state), possession

**Custody**:
Provenance-of-ownership — the append-only record of how a record's owner came to be its owner.
Outside Rank's domain entirely: a Rank's actor is fixed at creation and never transferred; custody
belongs to the chartered lineage effort (`rushing/laravel-lineage`), of whose substrate it is one
lane.
_Avoid_: ownership history, transfer log (as Rank concepts)
