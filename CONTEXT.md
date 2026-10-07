# JSON Hub Core

Pure domain rules of JSON Hub: Definitions, Entities and the policies that decide whether a JSON Schema may be published. No I/O; it returns decisions and never persists them.

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
