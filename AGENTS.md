> You are in **rushing/laravel-beam-bookmarks** — save lists of particles for beam sites.

Two concepts, deliberately generic: a `Bookmark` (a saved particle, one user × one bookmarkable
particle, optionally filed on a shelf) and a `Shelf` (a named, orderable, shareable, nestable
grouping of bookmarks), on the permission-cascade visibility substrate (`HasVisibility` +
`HasUser`) and staudenmeir adjacency. Declarative particle resources (shelves + bookmarks) for
read/write/hydrate/edit. A host maps its own vocabulary onto a `Shelf` (audiostud: a "playlist" is
a shelf of song bookmarks).

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
