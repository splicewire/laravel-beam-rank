# laravel-beam-rank

The **Rank substrate** for beam sites: any actor attaches a **typed gesture** to any target. Two
concepts, deliberately generic:

- **`Rank`** — the atom: one *actor* (`user`, a polymorphic morph pair) attached one gesture of a
  `type` to one *rankable* target, optionally filed on a `RankTree` at a `position`.
  `tree_id = null` is the ungrouped list. `type` joins the uniqueness tuple, so the same actor can
  independently `like` **and** `favorite` the same target (two rows). Rows of the scalar `rank`
  type carry a numeric `value` on a translatable min/max scale. Morph keys are strings
  (cross-host: uuid or bigint).
- **`RankTree`** — a named, orderable, shareable, **nestable** grouping of ranks. Owned via
  permission-cascade's `HasMorphUser` (single owner, morph columns), shared/published via
  `HasVisibility`, nested via staudenmeir adjacency. Nested under a per-user root, private by
  default, publishable by widening its visibility tier.

> **Word collision, on purpose:** "a Rank row" means any row in `beam_ranks`, of any type; the
> `rank` *type* is the one scalar rating. Docs here always disambiguate ("a Rank row of type
> `rank`").

## Vocabulary

`RankType` ships string constants — `LIKE`, `DISLIKE`, `FAVORITE`, `FRIEND`, `FOLLOW`, `IGNORE`,
`SILENCE`, `BLOCK`, `RANK` — never a backed enum: a host mints its own type by passing any string,
with zero package changes. A host also maps its product vocabulary onto the OTB types (audiostud:
its "Save" button is a `favorite`; a "playlist" is a tree of favorites). The package never speaks
a host's UI words, and a host's UI never speaks the package's.

`friend` and `follow` are **unilateral declarations**. Mutual friendship is emergent (two
reciprocal rows), never a stored state. No consent machinery ships in the schema — approve/deny
flows (friend handshake, private-account follow) are `splicewire/laravel-beam-workflows` workflows
managed *over* a Rank. Full glossary: [`CONTEXT.md`](CONTEXT.md).

## Imperative API

`Splicewire\Beam\Rank\Ranks`: `rootFor` / `createTree` / `toggle` / `untoggle` / `rate` /
`translate` / `reorder` / `publish`. Model classes resolve through `config('beam.rank.models.*')`
(host-subclassable). `toggle`/`untoggle` dedup on the full unique tuple; `rate` clamps to explicit
or configured (`beam.rank.scales`) bounds and upserts the single scalar row; `translate` linearly
rescales a value between arbitrary min/max pairs (store 0–10, render 5 stars).

## History (rides ActivityLog)

`RankRecorder` extends beam-core's `Activity\ActivityRecorder` (log name `beam-rank`) — the
"activity onto the log substrate" base, NOT `RevisionRecorder`: a rank gesture is activity, not a
reversible attribute revision, so the revert/undo semantics never applied. **Subject is the
rankable target**, so `history($record)` is the full cross-actor, cross-type feed on that record
and survives ranks being toggled off; `correlation` threads one rank's lifecycle. Two fixed
payload shapes: toggles record existence transitions (`[] → {type}` / `{type} → []`), rates record
old→new values. Config-gated: `beam.rank.log_activity.toggle` defaults **off** (highest-volume
event class; the fleet has no activitylog pruning), `.rate` defaults **on**.

## Particle resources (read / write / hydrate / edit)

Declarative `#[ParticleResource]` Data classes (`Data\RankTreeData`, `Data\RankData`) — `scope()` +
`project()` conventions, discovered + mounted by `Rank\Resources::register()`:

- **`rank-trees`** — index (own ∪ reach-visible via `scopeForUser`), store / update (rename +
  publish via the `visibility` field + `parent_id`) / destroy, plus the `reorder` op. Authorization
  is the model's own `#[UseCascadePolicy(BaseModelPolicy::class, create: true)]` attribute —
  **no Policy class ships in this package**; `prepare()` defaults a new tree under the user's root
  (ownership stamps via the `HasMorphUser` creating hook).
- **`ranks`** — index scoped to the current user, **filterable by `type`, `treeId`, and
  `rankableType`** (`?filter[type]=favorite`) + destroy. Dedup-aware `toggle` / `untoggle` / `rate`
  are bespoke collection-level routes over the `Ranks` action (a bare create can't dedup), each
  delegating to its `Ops\*` class.

Write handlers live one-per-class in `src/Ops/` (`ToggleRank`, `UntoggleRank`, `RateRank`,
`ReorderRanks` — the last a `#[ParticleOp]` class). Op outputs are Data classes on the camelCase
property convention (`RankData`, `RankRemovedData`, `RankTreeReorderData`), never hand-rolled
snake_case arrays.

**Per-model mount:** `Rank::attachTo('songs', Composition::class)` mounts
`songs/{song}/op/rank-toggle`, `.../rank-untoggle`, `.../rank-rate` — operations scoped to one
host model (default ability `view`: rank what you can see), **additive** to the global surface
(a record page wants the nested route; a "my activity" page wants the global filterable listing).
Mirrors `laravel-beam-accounts`'s `Sharing::attachTo()`.

Tables are prefixed by beam core (`Beam::table()` → `beam_rank_trees` / `beam_ranks`).

## Config

`config/beam/rank.php`: `models.{tree,rank}`, `register_migrations`, `register_resources`,
`resources.{group_prefix,middleware}`, `root_name`, `scales` (per-type min/max defaults for the
scalar path), `log_activity.{toggle,rate}`.

## Ownership posture

The `user_type`/`user_id` columns write through one blessed seam —
`Rushing\PermissionCascade\Facades\Ownership::assign()` (the trait's `assignUser()` is
sugar) — and are defined as rebuildable projections of a future custody chain
(`rushing/laravel-lineage`, chartered separately). No `transferOwnership()` exists on purpose: the
first real transfer feature mints it, with event-complete custody capture.

## Testing

```
composer test   # pest (trees, the typed atom, scalar rate/translate, recorder payloads, attachTo ops, publish/inheritance, resource scope/project)
composer pint
```

The HTTP mount is exercised by the consuming satellite (audiostud) — mirroring how
laravel-beam-accounts defers its ledger-resource route test to the host.
