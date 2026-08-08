Yii2 Graphql Changelog
=======================
# Unreleased

> Before upgrading, follow each applicable version step in [UPGRADE.md](UPGRADE.md).

# 0.18.0
- **Security:** cached root-fragment expansion across operations to prevent quadratic unauthenticated CPU work.
- **Security behavior change:** secured mixed application/introspection operations by authorizing introspection
  independently and using the complete configured schema after authorization.
- **Security behavior change:** applied `CompositeAuth` `only` and `except` patterns to each selected GraphQL action
  independently. Requests that previously relied on a mixed-field pattern mismatch may now require credentials.
- **Security behavior change:** made `requirePostForMutations` validate the original HTTP transport method so Yii
  method-override inputs cannot turn GET, PUT, PATCH, or DELETE mutations into accepted POST mutations.
- Added a versioned migration guide covering intentional behavior changes.

# 0.17.5
- Published release tags without a `v` prefix so GitHub, Packagist, and Composer use the same version names.

# 0.17.4
- Required manual approval before the GitLab mirror can publish a package.
- Restricted the GitLab release job to protected, approved tag pipelines.

# 0.17.3
- Restricted GitHub release creation to commits reachable from the protected default branch.
- Hardened the tag-based release workflow trust boundary.

# 0.17.2
- Ensured configured `checkAccess` callbacks run without requiring `CompositeAuth`.
- Prevented `CompositeAuth::except` from removing actions before authorization checks.

# 0.17.1
- Detected introspection from actual `__schema` and `__type` fields, including fields reached through root fragments.
- Scoped authentication and mutation policies to the operation selected through `operationName`.
- Prevented application operations from inheriting an exemption from an unselected introspection operation.

# 0.17.0
- Added opt-in mutation hardening through `requirePostForMutations` and `requireAccessCheckForMutations`.
- Deprecated the implicit allowance of mutations over non-POST requests. Leaving `requirePostForMutations` unset
  preserves the legacy behavior with an `E_USER_DEPRECATED` warning; set it to `false` for an explicit legacy opt-out
  or `true` to require POST. The default will change to `true` in the next major release.
- Added Infection mutation testing and expanded the supported PHP CI matrix.

# 0.16.1
- Fixed SchemaNotFound during IDE capability probes by treating `__schema` / `__type` selections as introspection in `GraphQL::parseRequestQuery()`.
- Added a regression test for PhpStorm's `IntrospectionCapabilitiesQuery` to ensure GraphQLAction returns data or standard errors instead of SchemaNotFound.
- Created lightweight tests for the `PageInfo` and `Pagination` types to keep coverage high without constraining implementation details.
- Migrated `phpunit.xml.dist` to the PHPUnit 9 schema, removing deprecated-config warnings.

# 0.15.3
- Added PHPStan with baseline, `composer lint:strict` / `composer stan` scripts, and CI steps for every supported PHP version.
- Introduced `GraphQLModuleInterface` and base `yii\graphql\GraphQLModule`; legacy modules using only the trait now trigger deprecation warnings (temporary fallback tested).
- README (+ RU/ZH translations) document the new commands and recommend inheriting from the base module.

# 0.15.2
- Simplified the GitLab CI pipeline to a single tag-triggered Composer release job suited for mirrored repositories.
- Updated the English, Russian, and Chinese READMEs with instructions for enabling GitLab mirror triggers instead of UPSTREAM variables.
# 0.15.1
- Added GitLab CI pipeline capable of syncing upstream forks before publishing Composer packages to GitLab Packages.
- Documented the `UPSTREAM_URL` and `UPSTREAM_BRANCH` CI variables in the English, Russian, and Chinese READMEs.
# 0.15
- Added Docker-based dev environment with MySQL, PHP 7.4, Xdebug, and composer scripts for headless testing
- Improved documentation (EN/RU/ZH) with dependency requirements, multipart upload notes, and testing instructions
- Translated remaining Chinese inline comments to English and expanded unit tests across GraphQL, uploads, union types, and scalars (coverage ~80%)
- Adjusted GraphQL facade to treat missing operation names as null for compatibility with graphql-php 14.x
# 0.14
- GraphQL action coverage tests for variables/raw body plus fixes to assertions
- Throw SchemaNotFound when referenced schema is missing
- Codecov uploads, refreshed branding, and corrected coverage badges
- Added Russian docs and corrected Chinese translations
- Disabled sessions for web tests, added auth behavior coverage, and removed stale GraphQL comments
# 0.13
- update for graphql-php(v0.13)
- add graphql-upload(v4.0.0)
# 0.11
- update for graphql-php(v0.11)
- Enh: default ErrorFormatter log the error from graphql-php
# 0.9.1
- Enh: add the default errorFormat to format the model validator error,add "code" field to response errors segment.
# 0.9
- New support facebook graphql server 
