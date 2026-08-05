# laravel-beam-bookmarks

Save **lists of particles** for beam sites. Two concepts, deliberately generic:

- **`Bookmark`** — the atom: one *user* saved one *bookmarkable* particle, optionally filed on a
  `Shelf` at a `position`. `shelf_id = null` is the ungrouped **"Saved"** list. The same particle can
  live bare **and** on several shelves (independent rows). Morph keys are strings (cross-host: uuid
  or bigint).
- **`Shelf`** — a named, orderable, shareable, **nestable** grouping of bookmarks. Composed on the
  permission-cascade visibility substrate (`HasVisibility` + `HasUser`) + staudenmeir adjacency —
  the same primitives `BeamSilo` is built from, without its scout/sluggable/sync weight. Nested
  under a per-user root, private by default, publishable by widening its visibility tier.

A host maps its own vocabulary onto a `Shelf`: **audiostud** treats a *playlist* as a shelf of song
bookmarks; another host, a *reading list* or a *board*. The package never says "playlist".

## Particle resources (read / write / hydrate / edit)

Declarative `#[ParticleResource]` Data classes (`Data\ShelfData`, `Data\BookmarkData`) — `scope()` +
`project()` conventions, discovered + mounted by `Bookmarks\Resources::register()`:

- **`shelves`** — index (own ∪ reach-visible via `scopeForUser`), store / update (rename + publish
  via the `visibility` field + `parent_id`) / destroy. Owner-gated by `Policies\ShelfPolicy`
  (BaseModelPolicy steward); `prepare()` owns the shelf + defaults it under the user's root on create.
- **`bookmarks`** — index scoped to the current user, **filterable by `shelf_id`** (`?shelf_id=X` =
  that shelf ordered, `?shelf_id=` = the bare Saved list, absent = everything) + destroy. Dedup-aware
  save/unsave + `reorder` are operations/routes over the `Bookmarks` action (a bare create can't dedup).

Tables are prefixed by beam core (`Beam::table()` → `beam_shelves` / `beam_bookmarks`).

## Imperative API

`Splicewire\Beam\Bookmarks\Bookmarks`: `rootFor` / `createShelf` / `save` / `unsave` / `reorder` /
`publish`. Model classes resolve through `config('beam.bookmarks.models.*')` (host-subclassable).

## Config

`config/beam/bookmarks.php`: `models.{shelf,bookmark}`, `register_migrations`, `register_resources`,
`resources.{group_prefix,middleware}`, `root_name`.

## Testing

```
composer test   # pest (12 tests: shelves, the unified bookmark atom, publish/inheritance, resource scope/project)
composer pint
```
The HTTP mount is exercised by the consuming satellite (audiostud, tracer 10) — mirroring how
laravel-beam-accounts defers its ledger-resource route test to the host.
