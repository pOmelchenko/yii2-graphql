Yii2 Graphql Changelog
=======================
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
