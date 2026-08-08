# Upgrading yii2-graphql

This document records migration steps and intentional behavior changes that may affect existing applications.
Read every section between the version currently installed and the version you are upgrading to. For example, an
application upgrading directly from 0.16.1 to 0.18.0 should follow all sections below in order.

## Upgrade from 0.16.1 to 0.17.0

No public API was removed in 0.17.0. Mutation hardening was added with backward-compatible defaults, but the implicit
legacy transport behavior is now deprecated.

### Choose mutation hardening explicitly

Mutations over non-POST requests remain allowed by default, but leaving the policy unspecified emits
`E_USER_DEPRECATED`. Choose the intended policy in the action configuration:

```php
return [
    'class' => \yii\graphql\GraphQLAction::class,

    // Recommended: reject mutations over GET and other non-POST methods.
    'requirePostForMutations' => true,

    // Optional: require a configured checkAccess callback for every mutation.
    'requireAccessCheckForMutations' => true,
];
```

To preserve the 0.16.1 mutation transport behavior without a deprecation warning, set `requirePostForMutations` to
`false`. `requireAccessCheckForMutations` defaults to `false`.

## Upgrade from 0.17.0 to 0.17.1

Version 0.17.1 changed how operations and introspection are identified for security decisions.

### Use actual introspection fields

Introspection is detected from `__schema` and `__type` fields, including fields reached through root fragments. An
operation is no longer treated as introspection merely because it is named `IntrospectionQuery`.

Remove integrations that grant access based only on the operation name. Use `GraphQLAction::INTROSPECTIONQUERY`
(`__schema`) in the authentication policy instead.

### Use the selected operation for policy decisions

When `operationName` is provided, authentication, `checkAccess`, and mutation transport policies are derived only
from the selected operation. Unselected operations in the same document do not grant exemptions and do not trigger
mutation requirements.

Always pass `operationName` when sending a document containing multiple operations.

## Upgrade from 0.17.1 to 0.17.2

Version 0.17.2 made `checkAccess` independent from authentication-filter initialization.

### Audit custom `checkAccess` callbacks

A configured `GraphQLAction::$checkAccess` callback is invoked for selected application actions even when
`CompositeAuth` is not attached. `CompositeAuth::except` controls authentication; it no longer removes matching
actions before the separate authorization callback runs.

Applications that configured `checkAccess` but accidentally relied on it not being called must update the callback or
remove the unused configuration.

## Upgrade from 0.17.2 to 0.17.5

Versions 0.17.3 through 0.17.5 changed release automation and tag publishing only. They require no application-code
migration.

## Upgrade from 0.17.5 to 0.18.0

Version 0.18.0 changes mixed application/introspection authentication and corrects `CompositeAuth` matching for
multi-field operations. It also makes strict mutation transport checks independent from Yii method overrides. These
are intentional security behavior changes; no public API is removed.

### Require the original POST transport for mutations

When `GraphQLAction::$requirePostForMutations` is `true`, the original `$_SERVER['REQUEST_METHOD']` must be `POST`.
Yii's `_method` parameter and `X-Http-Method-Override` header affect the effective application method, but they do not
satisfy this strict transport requirement. A raw GET, PUT, PATCH, or DELETE mutation overridden to POST now receives
HTTP 405.

Applications that intentionally accept method overrides from a trusted proxy must not use this strict flag for that
contract. Set `requirePostForMutations` to `false` and validate the proxy identity, override header, and allowed raw
methods before `GraphQLAction` runs.

### Audit mixed application and introspection operations

`__schema` and `__type` are both represented by the `GraphQLAction::INTROSPECTIONQUERY` action (`__schema`) for
authentication. A selected operation containing application fields and introspection now includes both kinds of
actions in its authentication map.

For example, this configuration makes only `hello` public. Introspection in the same operation still requires
authentication:

```php
use yii\graphql\filters\auth\CompositeAuth;

return [
    'authenticator' => [
        'class' => CompositeAuth::class,
        'authMethods' => [
            \yii\filters\auth\QueryParamAuth::class,
        ],
        'except' => ['hello'],
    ],
];
```

```graphql
query PublicWithProtectedSchema {
  hello
  __schema { queryType { name } }
}
```

To intentionally make both `hello` and introspection public, exempt both actions explicitly:

```php
use yii\graphql\GraphQLAction;

'except' => ['hello', GraphQLAction::INTROSPECTIONQUERY],
```

The same `__schema` policy action applies when the GraphQL field is `__type` or when introspection is reached through
a root fragment. Once authentication succeeds, mixed introspection runs against the complete configured schema. Its
result no longer depends on unrelated operations or fields in the request document.

### Review `CompositeAuth::only` and `except`

Patterns are evaluated for each action in the selected GraphQL operation:

- Authentication runs when at least one selected action matches `only` and is not excluded by `except`.
- Authentication is skipped by `except` only when every selected action that would otherwise be protected is exempt.
- Adding an unrelated or public field to an operation cannot disable authentication for a protected field.

Consequently, a request that previously relied on a mixed-field `only` mismatch to skip authentication will now
require valid credentials.

### Handle mixed introspection in `checkAccess`

For a mixed application/introspection operation, `GraphQLAction::$checkAccess` can receive
`GraphQLAction::INTROSPECTIONQUERY` (`__schema`) in addition to application action names. Audit callbacks that use a
closed list and explicitly decide whether introspection is allowed:

```php
use yii\graphql\GraphQLAction;
use yii\web\ForbiddenHttpException;

function checkAccess($actionName)
{
    if ($actionName === GraphQLAction::INTROSPECTIONQUERY && !userCanInspectSchema()) {
        throw new ForbiddenHttpException('Schema introspection is not allowed.');
    }

    // Existing application-field checks...
}
```

### Do not use `parseRequestQuery()` as an authorization decision

`GraphQL::parseRequestQuery()` returning `true` means that execution requires the complete configured schema. It does
not mean that the selected operation contains only introspection fields. Mixed application/introspection operations
also return `true`.

Integrations that need request metadata should use `GraphQL::getSelectedOperationSchema()` together with
`GraphQL::hasSelectedOperationIntrospection()`. Authorization should normally remain in `GraphQLAction`,
`CompositeAuth`, and `checkAccess`.

### Migration checklist

- Audit every `CompositeAuth` `only` and `except` list.
- Add `GraphQLAction::INTROSPECTIONQUERY` to `except` only when public introspection is intentional.
- Ensure custom `checkAccess` callbacks handle the `__schema` action for mixed introspection.
- Remove any authorization logic inferred only from the boolean return value of `parseRequestQuery()`.
- If method overrides are intentionally supported, replace `requirePostForMutations` with an explicit trusted-proxy
  policy before `GraphQLAction` runs.

## Upgrade from 0.18.0 to 0.18.1

Version 0.18.1 completes strict mutation transport enforcement for method overrides in the reverse direction. When
`GraphQLAction::$requirePostForMutations` is `true`, both the original `$_SERVER['REQUEST_METHOD']` and Yii's effective
request method must be `POST`.

A raw POST carrying `_method=GET` or another non-POST override now receives HTTP 405 instead of being parsed as a safe
method and executing a mutation from the query string. Applications that intentionally accept such overrides must
keep `requirePostForMutations` disabled and enforce their trusted-proxy policy before `GraphQLAction` runs.
