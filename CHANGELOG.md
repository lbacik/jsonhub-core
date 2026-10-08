# Changelog

All notable changes to `jsonhub/core` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

Terms such as Revision, Draft, Dialect and Schema Charge are defined in
[CONTEXT.md](CONTEXT.md).

## [0.3.0] - Unreleased

Versioning primitives for globaldb R1. All additions are new types and new
interfaces: no method was removed from, or had its signature changed in, any
v0.2.x public interface. The behaviour changes and requirement bumps below
still need attention when upgrading.

### Upgrade notes

- **Requires PHP >= 8.4 and `ext-mbstring`.** v0.2.x declared no PHP constraint.
- **Definition in-use checks now count every active Entity.**
  `DefinitionRegistry::removeDefinition` and the schema-change check in
  `DefinitionRegistry::updateDefinition` call `EntityRepository::count()` with
  `isSystemQuery: true` (#4). A Definition used only by private Entities, or
  only by Entities of another Owner, can no longer be deleted or have its
  schema changed. Fixes lbacik/globaldb#131. Adapters must implement
  `isSystemQuery` as documented below; if they already do, nothing changes
  for them.
- **`FilterCriteria` has a new trailing optional argument**,
  `definitionRevision` (default `null`). Existing named and positional calls
  keep working.
- **`isSystemQuery` is now documented** on `EntityRepository::readAll()` and
  `count()`: when true, the query covers active Entities of every Owner and
  both visibilities; logically deleted Entities are excluded.

The new repository and Entity contracts (`DefinitionRevisionRepository`,
`DefinitionDraftRepository`, `PinnedEntity`, `SchemaLibraryCheck`) are
additive. `front-v1` does not have to implement them to compile against this
release; globaldb adapters do.

### Added

- **Canonical JSON** (#1), in `JsonHub\Core\CanonicalJson`:
  - `CanonicalJson` with Strict Reading (rejects duplicate member names,
    numbers that do not survive an IEEE double round trip, integers beyond
    ±(2^53−1) and invalid strings, each with a stable code and JSON Pointer)
    and Legacy Reading (accepts any stored JSON; results that pass Strict
    Reading are byte-identical to it).
  - Canonical Form, an RFC 8785 (JCS) subset: members sorted by UTF-16 code
    units, no whitespace, raw UTF-8, numbers by mathematical value.
  - `SchemaCharge` (UTF-8 byte length of the canonical form plus
    `ChargeEncoding`: `1` for the canonical form, `0` for legacy-lossless) and
    `DraftSizeLimit`, a host-supplied byte limit. A charge exactly at the limit
    is accepted, one byte over is rejected, and encoding `0` is exempt.
- **Schema publication policy** (#2), in `JsonHub\Core\SchemaPolicy`:
  - `Dialect` (`legacy`, `draft-04`) and dialect detection from the root
    `$schema`.
  - `SchemaSafetyCheck`: local-only resolvable references, no non-consuming
    reference cycle, no base-URI identifiers (`id`/`$id`), depth limit of 64.
  - Rejection of Later-dialect Keywords at Schema Positions.
  - `SchemaPublicationPolicy`, `SchemaViolation`, `SchemaViolationCode` and
    `SchemaViolations` (capped at 100, the generic root error omitted when
    specific violations exist). No network or disk access.
  - `Contracts\SchemaLibraryCheck`, the contract for the validator-library
    specific part (keyword value checks, `pattern` compilation). Implemented by
    the host.
  - ADR 0001 (draft-04 is defined by the spec, not by the library) and ADR 0002
    (the Safety Check models the validator's reference behaviour).
- **Versioned Definition model** (#3):
  - Contracts `DefinitionRevision`, `DefinitionDraft`,
    `DefinitionRevisionRepository`, `DefinitionDraftRepository` and
    `PinnedEntity` (an Entity exposing its pinned Revision), as new interfaces
    next to the existing ones.
  - `RevisionNumber` value object (positive integer).
  - `DefinitionPlacement`: the Owner/Parent Entity invariant, including Root
    Definitions. A tombstoned Owner is still an Owner. A placement with only
    one of Owner and Parent Entity throws `CreateDefinitionException` with the
    new code `OWNER_PARENT_PAIRING` (3). Nothing in core uses it yet, so
    existing `DefinitionRegistry` behaviour is unchanged.
  - `FilterCriteria::$definitionRevision` for the Entity collection filter.
- `CONTEXT.md` glossary for the terms above.

### Changed

- Development tooling: PHPSpec 8, phpspec-code-coverage 7 and PHP_CodeSniffer 4;
  CI runs on PHP 8.4 only.
- Advisory mutation testing with Infection (manual workflow dispatch, not part
  of the test gate); see [docs/mutation-testing.md](docs/mutation-testing.md)
  (#16).

## [0.2.1]

Earlier releases (v0.1 – v0.2.1) were tagged without a changelog; see the
[tag history](https://github.com/lbacik/jsonhub-core/tags).

[0.3.0]: https://github.com/lbacik/jsonhub-core/compare/v0.2.1...HEAD
[0.2.1]: https://github.com/lbacik/jsonhub-core/releases/tag/v0.2.1
