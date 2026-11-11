# Staged PHP migration

## First step: PHP 8.0

This step raises only the root PHP constraint, from `>=7.4.0` to `>=8.0.0`. It preserves webonyx `^14.8`,
graphql-upload `^6`, Diactoros `^2.17`, Yii `~2.0.10`, and all development-tool constraints. Runtime PHP code and
public API behavior stay unchanged. PHP 8.0 already passes the project's full test suite with the latest
dependencies; the additional compatibility check is a lowest-dependency run on the new minimum runtime.

PHP 8.0 is a migration milestone, not the final supported platform target. Consumers can first run the published
`14.11.2` version on PHP 8.0, fix their application compatibility, and then update the extension. Applications that
must stay on PHP 7.4 can retain the published `14.11.2` tag. Future releases must identify each minimum-PHP increase
explicitly as a platform compatibility change in the changelog and upgrade guide. Package major/minor numbers
continue to follow the webonyx compatibility target; this PR does not create a release or move existing tags.

The consumer upgrade sequence is:

1. Save the application's current dependency lock and establish passing application tests on `14.11.2`.
2. Move CLI, web and background-worker runtimes to PHP 8.0 and rerun those tests before updating this extension.
3. Update the extension, review the lock diff, and run `composer check-platform-reqs` on the deployment runtime.
4. Exercise GraphQL queries, mutations, authentication/authorization and multipart uploads in the application.
5. Review `composer audit --locked`. Keep the prior deployment and lock available until application checks pass.

## Dependencies and security boundaries

PHP 8.0 can resolve the existing major versions of every runtime dependency. Keeping those constraints separates
the platform migration from later library/API migrations. Modern Composer still needs the existing three scoped
webonyx 14 advisory exceptions for compatibility CI; these apply only to blocking resolution, and audit reporting
remains enabled. This migration does not fix the upstream webonyx 14 vulnerabilities.

Removing PHP 7.4 lets CI drop its Diactoros header-advisory exception. The upstream fixes include
[Diactoros 2.18.1](https://github.com/laminas/laminas-diactoros/security/advisories/GHSA-xv3h-4844-9h36), which supports
PHP 8.0. Fresh CI resolution must exclude the affected versions. The package's `^2.17` constraint and existing
consumer locks are unchanged, so consumers must still audit and update vulnerable locks themselves. Tightening
that dependency floor can be reviewed as a separate change.

The initial lowest-dependency resolution with `config.platform.php` set to `8.0.0` selected:

| Package | Resolved version |
| --- | --- |
| webonyx/graphql-php | 14.8.0 |
| ecodev/graphql-upload | 6.1.0 |
| laminas/laminas-diactoros | 2.18.1 |
| yiisoft/yii2 | 2.0.55 |

This records a resolution under the existing security policy, not new package constraints. A separate dry run
targeting PHP 7.4.33 was rejected by the root `>=8.0.0` requirement. The local resolver simulation is complemented
by CI executing the full suite on an actual PHP 8.0 runtime with both lowest and latest dependencies.

## CI and repository rules

- Run the existing full quality checks on PHP 8.0, 8.1, 8.2 and 8.3.
- Run PHPUnit and PHPStan with the lowest dependencies on PHP 8.0. Keep MSI 70% and covered MSI 74% unchanged.
- Remove PHP 7.4 execution and its runner-local Diactoros advisory exception.
- Keep the PHP 8.4/8.5 skip notices until compatible dependency versions are introduced.
- Replace the required `tests (7.4, xdebug, true)` check in the master ruleset with
  `tests (8.0, none, true, lowest)` after the new job succeeds. Preserve the other required checks and protection rules.

## Subsequent steps

Raise the PHP minimum to 8.1, then 8.2, 8.3 and 8.4 in separate reviewed migration steps. Adaptation to webonyx 15
and graphql-upload 7 is a separate dependency change once the PHP 8.1 baseline is established; graphql-upload 8
is a later PHP 8.4 step. Before each release, verify both latest and lowest dependency sets on its minimum runtime
and the full supported matrix. Tool upgrades and syntax refactoring can be reviewed independently from consumer
runtime requirements.

The removal of the deprecated mutation transport opt-out and the legacy module fallback belongs to the first
webonyx 15 release, as previously announced; it is outside the PHP 8.0-only step.
