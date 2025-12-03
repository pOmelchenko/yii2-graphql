Yii2 Graphql Changelog
=======================
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
