# The Safety Check models the validator's reference behaviour, not only the draft-04 specification

justinrainbow/json-schema 5.3.0 rewrites every `$ref` against draft-04 `id` base URIs, reads `#name` as the pointer `/name`, and merges keywords next to `$ref` into the target. Because the Safety Check must guarantee the library never retrieves anything and always terminates, it follows the library rather than the spec: a string `id`/`$id` at a Schema Position is rejected (`unsupported_identifier`), a non-pointer fragment reference is rejected (`unsupported_reference`), and cycle detection follows a `$ref` node's sibling keywords as well as the reference itself.

## Considered Options

- Resolving `id` ourselves: rejected; it is what makes a seemingly local reference non-local inside the library.
- Rejecting `$ref` with siblings: rejected; annotation siblings (`title`, `description`) are too common in existing schemas.
