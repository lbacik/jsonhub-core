# JSON Hub Core

Pure domain rules of JSON Hub: Definitions, Entities and the policies that decide whether a JSON Schema may be published. No I/O; it returns decisions and never persists them.

## Definition versioning

**Definition**:
A stable identity with metadata (Owner, slug, Parent Entity) that owns one Definition Draft and an append-only history of Definition Revisions.
_Avoid_: schema, type

**Definition Revision**:
An immutable published schema of a Definition, identified by its own UUID and a Revision Number, and validated under one Dialect.
_Avoid_: version, schema version, snapshot

**Revision Number**:
The position of a Revision in its Definition's history: a positive integer, starting at 1, with no gaps. It carries no compatibility meaning.
_Avoid_: version, revision index

**Current Revision**:
The Revision with the greatest Revision Number; nothing is stored to point at it, and it never moves back to an older Revision.
_Avoid_: latest version, active revision, head

**Definition Draft**:
The one private, editable schema of a Definition, based on a Revision and carrying a version number that advances on every effective change; it is **clean** when its schema equals its base Revision's schema.
_Avoid_: working copy, unpublished revision

**Pin**:
The Revision an Entity is bound to and validated against; only an Entity Migration changes it.
_Avoid_: entity version, schema lock

**Root Definition**:
A system-maintained Definition with neither Owner nor Parent Entity. A Definition whose Owner's account is closed (tombstoned) still has an Owner and is not a Root Definition.
_Avoid_: system definition, base definition

**Definition Placement**:
Where a Definition sits in the hierarchy: either an Owner with a Parent Entity of that same Owner, or neither (a Root Definition).
_Avoid_: ownership, parentage

## Schema publication

**Dialect**:
The JSON Schema dialect a Revision is validated under: `legacy` (never checked by the Publication Policy) or `draft-04`.
_Avoid_: version, schema version

**Publication Policy**:
The full set of rules a schema must pass before a `draft-04` Revision may be created from it: the Safety Check, Dialect detection, the later-dialect keyword rule, and the library-specific checks.
_Avoid_: schema validation, validator

**Safety Check**:
The subset of the Publication Policy that decides whether a schema may be handed to the validator library at all: local-only resolvable references, no non-consuming reference cycle, no base-URI identifiers (`id`/`$id`), depth limit.
_Avoid_: pre-validation, sanity check

**Schema Violation**:
One reason a schema fails a policy, identified by a stable `code` and a JSON Pointer into the candidate schema.
_Avoid_: schema error, validation error

**Schema Position**:
A place in a schema document whose value is itself a schema (the root, `properties/*`, `items`, `allOf/*`, `definitions/*`, `$defs/*`, …), as opposed to a place holding data such as `enum`, `default` or a property name.
_Avoid_: subschema path, node

**Later-dialect Keyword**:
A validation keyword that exists only in dialects after draft-04 (e.g. `if`, `const`, `prefixItems`) and is therefore rejected at a Schema Position.
_Avoid_: unknown keyword, unsupported keyword

**Instance-consuming Keyword**:
A keyword whose subschema applies to a child of the instance rather than to the instance itself (`properties`, `patternProperties`, `additionalProperties`, `items`, `additionalItems`); a reference cycle passing through one is allowed.
_Avoid_: descending keyword

## Canonical JSON

**Canonical Form**:
The single byte representation of a JSON value: an RFC 8785 (JCS) subset with sorted members, no whitespace, raw UTF-8, and numbers written by mathematical value.
_Avoid_: normalized JSON, minified JSON

**Strict Reading**:
Reading JSON under the new-write rules: duplicate member names, numbers that do not survive a round trip through an IEEE double, integers beyond ±(2^53−1), and invalid strings are rejected.
_Avoid_: validation, strict parsing

**Legacy Reading**:
Reading stored JSON that may break the new-write rules without rejecting or altering it; values that pass Strict Reading come out identical to it.
_Avoid_: lenient parsing, backfill mode

**JSON Value Equality**:
Two JSON values are equal when their Canonical Forms are byte-identical.
_Avoid_: deep equality, semantic equality

**Schema Charge**:
The UTF-8 byte length of a schema's Canonical Form, together with the encoding it was measured under.
_Avoid_: schema size, schema bytes

**Charge Encoding**:
Which Canonical Form a Schema Charge was measured under: `1` for the JCS subset, `0` (legacy-lossless) for values only Legacy Reading accepts.
_Avoid_: charge version, format

**Draft Size Limit**:
The maximum Schema Charge a Draft may have; the value is owned by the host application and applies only to the current encoding.
_Avoid_: 1 MB limit, max schema size
