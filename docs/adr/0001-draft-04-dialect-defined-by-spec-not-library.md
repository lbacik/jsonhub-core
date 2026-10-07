# The draft-04 Dialect is defined by the specification, not by the validator library

The Publication Policy rejects every Later-dialect Keyword (draft-06 and newer: `if`/`then`/`else`, `const`, `contains`, `propertyNames`, `prefixItems`, `unevaluated*`, `dependent*`, `$dynamic*`, `$recursive*`, …), even ones that justinrainbow/json-schema 5.3.0 happens to enforce, such as `const`, `contains` and `propertyNames`. The rule exists because no external conformance suite fixes the list for this product, and tying the list to the library would let a library upgrade silently change which schemas may be published. The exact list lives in core and its specs are the conformance tests.

## Consequences

- Schemas that "work today" with `const` or `contains` fail publication; they keep working as `legacy` Revisions.
- Supporting such keywords means introducing a new Dialect, not relaxing this list.
