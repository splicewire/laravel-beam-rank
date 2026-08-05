# laravel-beam-bookmarks

Taxonomized **playlists** + lightweight **bookmarks/pins** for beam sites — composed on the
`rushing/laravel-permission-cascade` visibility substrate and staudenmeir adjacency, not a
reinvented hierarchy.

## Design

- **`Playlist`** composes the same primitives `BeamSilo` is built from — `HasVisibility`
  (share/publish through the cascade), `HasUser` (userable ownership), and staudenmeir
  `HasRecursiveRelationships` (nesting) — as a purpose-built model, *without* Silo's scout /
  sluggable / sync weight. Each user gets a **per-user root** playlist (Option C: a user-rooted
  subtree); child playlists nest under it, **private by default**, cascade-gated; a nested
  playlist inherits its ancestor's effective tier while its own `visibility` is NULL.
- **`PlaylistItem`** — an ordered, deduped, polymorphic `playlistable` entry (string morph key, so
  it holds uuid- and bigint-keyed hosts alike).
- **`Bookmark`** — the lighter single-item save: one `owner` pins one `bookmarkable`, deduped.

Lifecycle lives in the **`Playlists`** action: `rootFor` / `create` / `addItem` / `removeItem` /
`reorder` / `publish` / `bookmark` / `unbookmark`. Model classes resolve through
`config('beam.bookmarks.models.*')` so a host can subclass.

## Tables (prefixed)

`beam_playlists`, `beam_playlist_items`, `beam_bookmarks` — via the `Support\Tables` seam
(`Beam::table()` when the host has `splicewire/laravel-beam`, else the `beam_` convention). Ownership
uses permission-cascade's `userables` pivot.

## Not here

- **SchemaVersionedSyncable / federation** — the models are federation-*ready* (uuid + morph shape)
  but the sync-contract wiring is deferred to when federation is actually built.
- **Host UI + `Composition` faceting + public feeds** — the consuming satellite (tracer 10).

## Testing

```
composer test   # pest — 8 tests (root, nesting, order/dedupe, private-default, publish-widens, inheritance, bookmarks)
composer pint
```
