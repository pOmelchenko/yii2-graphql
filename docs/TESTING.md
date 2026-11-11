# Testing and mutation score thresholds

## Runtime coverage

The current minimum is PHP 8.0. CI runs the full PHPUnit, lint, PHPStan and Infection checks with the latest
dependencies on PHP 8.0–8.3. A separate PHP 8.0 job resolves `--prefer-lowest --prefer-stable` dependencies and runs
PHPStan and the full PHPUnit suite, including GraphQL request execution. Both jobs use PHPStan 2.3.5 or newer with
the same configuration. The existing GlobalIdTrait test helper is included in analysed paths so the public trait
is checked in a concrete usage context. PHP 8.4 and 8.5 are explicitly skipped while the project depends on Diactoros 2.
See [the PHP migration plan](PHP-MIGRATION.md) for the next stages.

Run `composer test`, `composer lint`, and `composer stan` before submitting changes. Run the mutation suite with
Xdebug or PCOV available:

```sh
composer mutation
```

`composer mutation` loads `infection.json.dist`, including the thresholds used by all CI mutation jobs:

| Setting | Required score |
| --- | --- |
| `minMsi` (all generated mutants) | 70% |
| `minCoveredMsi` (mutants covered by tests) | 74% |

Infection returns a nonzero exit code when either score is below its threshold. The CI mutation step does not allow
failure, so this also fails the job. A run with no mutations does not bypass the thresholds.

## Why these thresholds

The common baseline comes from the successful [14.11.1 tag CI run](https://github.com/pOmelchenko/yii2-graphql/actions/runs/43073805016):

This is a historical reference; the PHP 8.0 migration removes PHP 7.4 testing and keeps the existing common thresholds.

| PHP | Infection | MSI | Covered MSI |
| --- | --- | --- | --- |
| 7.4 | 0.26.6 | 70% | 74% |
| 8.0 | 0.26.19 | 70% | 74% |
| 8.1 | 0.26.21 | 70% | 74% |
| 8.2–8.3 | 0.31.9 | 80% | 80% |

Scores are shown as whole percentages. Infection 0.31.9 reports covered MSI and 100% mutation code coverage in
these runs, so its overall MSI is equal to its covered MSI.

Infection versions generate different mutant sets, so one PHP job's score cannot be used as the floor for the entire
matrix. The configured thresholds fit the lowest results across all supported PHP versions. They establish an
initial regression gate; they do not require every mutant to be killed.

## Raising the thresholds

1. Inspect escaped and uncovered mutants in the CI artifacts and add assertions that verify the affected behavior.
2. Run `composer mutation` locally and the complete supported PHP matrix in the PR.
3. Raise `minMsi` and/or `minCoveredMsi` in `infection.json.dist` to a value passed by every supported PHP job. Include
   the per-version scores in the PR and update this baseline table when the common floor changes.
4. Keep the thresholds when fixing a regression. Changes to Infection versions or mutator profiles require a new
   matrix comparison before adjusting the baseline.

To check failure handling locally, use a temporary copy of `infection.json.dist` with both thresholds set to 100 and
run Infection with `--configuration` pointing to that copy. With the current test suite it must return a nonzero
exit code and report the required score. Keep this temporary configuration out of commits.

## Failure artifacts

CI uploads `build/infection` as `infection-logs-php-<version>` even when mutation testing fails, using `always()`.
The artifact contains `infection.log`, `infection.json`, and `summary.log` whenever Infection generates them.
Coverage reports are also uploaded on failure. Download these artifacts from the failing Actions run to inspect
escaped mutants and compare results before changing the thresholds.
