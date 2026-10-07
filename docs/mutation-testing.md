# Mutation testing

## Run from a fresh install

Use PHP 8.4 with `ext-mbstring` and a coverage driver: PCOV enabled
(`pcov.enabled=1`) or Xdebug in coverage mode. The PhpSpec coverage extension
requires a driver for ordinary specs too. With Xdebug:

```sh
composer install
XDEBUG_MODE=coverage composer mutation
```

With PCOV, use `composer mutation` without `XDEBUG_MODE`. The script runs all
specs first and then Infection with `--coverage=coverage`. This produces fresh
XML in `coverage/phpspec-coverage-xml`, the directory expected by the PhpSpec
adapter when reusing coverage. The existing Clover report remains at
`coverage/coverage_phpspec.xml`, including in the independent Codecov test gate.
Do not use `--skip-initial-tests`: Infection still verifies that the unmutated
suite passes.

Infection 0.32.7 and PhpSpec adapter 0.3.1 were tested with PhpSpec 8.3.1 and
phpspec-code-coverage 7.0.0 (php-code-coverage 11.0.12). The adapter is pinned
to 0.3.1 because 0.3.2 requires include-interceptor 1.x, while Infection 0.32
requires 0.2.x. Composer's extension installer is explicitly allowed so the
adapter is registered during a non-interactive install. This library does not
commit its lockfile; Composer resolves compatible transitive dependencies,
including the comparator/diff pair, on a fresh install.

## Focus a run

Regenerate full coverage after changing either production code or specs, then
forward Infection options through the single-command script:

```sh
XDEBUG_MODE=coverage composer test
XDEBUG_MODE=coverage composer mutation:run -- --filter=src/JsonHub/Core/CanonicalJson/DraftSizeLimit.php
```

For the exact limit comparison only:

```sh
XDEBUG_MODE=coverage composer mutation:run -- --filter=src/JsonHub/Core/CanonicalJson/DraftSizeLimit.php --mutators=GreaterThan
```

`composer mutation` is the complete run; `composer mutation:run` reuses existing
coverage and must only be used after a fresh `composer test`. Pass narrowing
options to `mutation:run`, not the composite `mutation` script, to avoid
forwarding Infection options to PhpSpec. Override concurrency with
`--threads=2` (the committed default is four workers); the per-mutant timeout
is ten seconds. The mutation scripts disable Composer's default 300-second
process timeout locally, since a full analysis takes longer. A timed-out mutant
needs inspection before treating it as evidence of a behavior assertion.
The Infection coordinator is given 512 MB of PHP memory for rendering the full
HTML/JSON reports; the default 128 MB was insufficient for this suite.

## Reports and scope

All generated output is under the already ignored `coverage/` directory:

| File | Purpose |
| --- | --- |
| `infection.log` | Surviving and timed-out mutant diffs |
| `infection.html` | Interactive mutation report |
| `infection-summary.log` | Mutant counts by outcome |
| `infection.json` | MSI, covered MSI, counts and machine-readable mutant details |
| `coverage_phpspec.xml` | Clover for Codecov |
| `phpspec-coverage-xml/index.xml` | Infection's PhpSpec XML coverage entry point |

The source directory is only `src/JsonHub/Core`, using `@default` mutators.
Specs, vendor dependencies and `src/JsonHub/Contracts` interfaces are outside
that directory. There are no score-driven mutator or method exclusions.

The waiting-room job in `quality.yml` runs on main pushes and PRs, uses PHP 8.4
with PCOV, and uploads `mutation-reports` even if analysis fails. It is advisory
and has no dependency relationship with `test.yml`. Scheduled quality runs
remain reserved for dependency watchers. No MSI threshold is chosen during
adoption. Agree thresholds after the baseline is understood, then move the job
to `test.yml` and remove the advisory copy. Full runs remain necessary for
changes affecting only specs.

References: [supported frameworks](https://infection.github.io/guide/supported-test-frameworks.html),
[configuration](https://infection.github.io/guide/usage.html),
[CLI and coverage reuse](https://infection.github.io/guide/command-line-options.html).

## Baseline and survivor review (2026-10-07)

Measured locally on PHP 8.4.24, Xdebug 3.5.3, Infection 0.32.7, adapter 0.3.1,
PhpSpec 8.3.1 and php-code-coverage 11.0.12, with four workers and the default
mutator profile. No thresholds or behavior exclusions were applied.

The first completed analysis, before strengthening examples (151 examples),
generated 2,125 mutants: 1,605 killed by tests, 506 escaped, 13 timed out,
1 errored and 0 uncovered. MSI and covered MSI were both 76.19%, with 100%
mutation coverage. The text report was written, but HTML rendering exhausted
the default 128 MB coordinator memory before the summary/JSON and final timer
were written. This analysis took approximately 12 minutes (based on coverage
and text-report timestamps; Infection's final timer was not reached).
The subsequent full run below uses the corrected 512 MB script
and is the reproducible adoption baseline with all report formats.

| Metric | Complete adoption baseline (165 examples) |
| --- | ---: |
| Total mutants | 2,172 |
| Killed by tests | 1,714 |
| Escaped / surviving | 444 |
| Uncovered | 0 |
| Timed out | 13 |
| Errored | 1 |
| Syntax errors / skipped / ignored / static-analysis kills | 0 |
| MSI | 79.56% |
| Covered MSI | 79.56% |
| Mutation code coverage | 100% |
| Infection execution time | 12 min 22 sec |
| Complete `composer mutation` wall time (including fresh coverage) | 12 min 34.41 sec |
| Infection reported memory | 0.20 GB |

The complete command exited successfully and produced text, HTML, summary and
JSON reports. Comparing exact mutant diffs confirmed that 79 mutants that
escaped the initial run are now killed by tests: 29 in Decimal/Reader, 40 in
schema policy/safety/document traversal, and all 10 initial registry survivors.
The two runs generated different mutant totals; scores should be read alongside
the diff comparison, not interpreted as a fixed population of mutants.

One remaining EntityRegistry survivor dropped previously mapped fields when
`slug` was processed last. After the full baseline, the all-values example was
strengthened to run both key orders. A final focused run on the entire
EntityRegistry file with fresh full-suite coverage and its own spec file killed
all 69 mutants, with 0 survivors/errors/timeouts (100% MSI, four seconds).
Reports are `coverage/entity-registry.{log,html,json}` and
`coverage/entity-registry-summary.json`. The full reports above are preserved
as the measured baseline; the aggregate score has not been extrapolated from
this focused result. All 165 examples and PSR-12 pass after this final change.

The reviewed gaps were addressed through existing public behavior boundaries:

| Area | Surviving mutation / gap | Examples strengthened or added |
| --- | --- | --- |
| DraftSizeLimit | Exact-limit `>` to `>=` | Already killed by `it_accepts_a_charge_at_exactly_the_limit`; a focused fresh-install run confirmed one mutant killed, none escaped |
| CanonicalJson / Decimal | Exponent digit `9` omitted; negative legacy sign skipped; UTF-8 boundaries and continuation guards | Strict/legacy `1e-9`, negative unsafe legacy numbers, exact fractional legacy spelling, negative safe limit, Unicode boundaries, invalid continuations |
| SchemaSafetyCheck / SchemaDocument | Array-index guards, escaped cycle targets, only first cycle returned, skipped anyOf/oneOf traversal, array depth changes | Canonical in-bounds indices, all independent cycles, anyOf/oneOf cycles, every consuming keyword, data-array depth 64/65 |
| SchemaPublicationPolicy | Removed sorting, lost document ranks, OR changed to AND when retaining specific failures | Combined policy/library violations in document order with guard precedence and escaped pointers; specific root and nested library failures survive generic-root suppression |
| DefinitionRegistry | `count > 0` to `>= 0`, removed setter chain, dropped previously mapped fields, null-safe owner guard removed | Changed unused schema updates all fields and persists exactly once; unauthenticated owner filters reject before repository access |
| EntityRegistry | System-user flag replaced with false, removed setters, dropped data/previous fields, null-safe owner guard removed | System-user repository query/result, changed values passed to all setters in either key order, unauthenticated owner filters reject before repository access |

The initial DraftSizeLimit file had no survivors. Existing duplicate-name,
UTF-16 ordering, numeric safe-limit, raw-object depth and unsafe-schema library
short-circuit examples already killed their corresponding decision mutants.
New examples supplement those checks rather than duplicating them.

Remaining mutants are deliberately visible. Examples include internal Decimal
sign/compare variants unused by strict classification (which compares absolute
values), grammar-redundant guards on already validated literals, message text,
and changes to sort ranks/guard numbers that preserve the observed ordering.
These are review candidates, not blanket exemptions or a claim that every
survivor is equivalent. Decimal rounding ties and additional adversarial
reference graphs remain useful follow-up areas.

The initial timeout diffs were inspected: Decimal loop bounds/carry loops and
Reader parsing/position/whitespace changes can stop progress or make traversal
unbounded. They remain reported as timeouts, not new assertion kills. Infection
includes timeout/error outcomes in MSI; always read the separate counts too.
The full run's single errored mutant replaced the cycle visitor's recorded
target color with `white`, removing cycle detection and exhausting memory.
