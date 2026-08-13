> You are in **splicewire/laravel-beam-rank** — the Rank substrate: typed actor→target gestures for beam sites.

Two concepts, deliberately generic: a `Rank` (one actor attached one typed gesture — like,
dislike, favorite, friend, follow, ignore, silence, block, or the scalar `rank` — to one
`rankable` target, optionally filed on a tree) and a `RankTree` (a named, orderable, shareable,
nestable grouping of ranks), on the permission-cascade ownership/visibility substrate
(`HasMorphUser` + `HasVisibility`) and staudenmeir adjacency. Mutation history rides beam-core's
ActivityLog substrate (`RankRecorder`). Declarative particle resources (`rank-trees` + `ranks`)
for read/write/hydrate/edit, plus a per-model `Rank::attachTo()` mount. A host maps its own
vocabulary onto the types and trees (audiostud: "Save" is a `favorite`; a "playlist" is a tree) —
the package never speaks a host's UI words.

Mind the deliberate word collision: "a Rank row" is any row of any type; the `rank` TYPE is the
scalar rating. Disambiguate in prose ("a Rank row of type `rank`"). Domain glossary: `CONTEXT.md`.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
