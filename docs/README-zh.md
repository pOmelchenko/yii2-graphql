yii-graphql
==========
使用 Facebook [GraphQL](http://facebook.github.io/graphql/) 的 PHP 服务端实现。扩展 [graphql-php](https://github.com/webonyx/graphql-php) 以适用于 YII2。

[![Latest Stable Version](https://poser.pugx.org/pomelchenko/yii2-graphql/v/stable.svg)](https://packagist.org/packages/pomelchenko/yii2-graphql)
[![CI](https://github.com/pOmelchenko/yii2-graphql/actions/workflows/ci.yml/badge.svg)](https://github.com/pOmelchenko/yii2-graphql/actions)
[![Coverage Status](https://codecov.io/gh/pOmelchenko/yii2-graphql/branch/master/graph/badge.svg)](https://codecov.io/gh/pOmelchenko/yii2-graphql)
[![Total Downloads](https://poser.pugx.org/pomelchenko/yii2-graphql/downloads.svg)](https://packagist.org/packages/pomelchenko/yii2-graphql)

Languages: [English](/README.md) | [Русский](/docs/README-ru.md) | [中文](/docs/README-zh.md)

> 最初由 [tsingsun](https://github.com/tsingsun) 创建；在此分支中持续开发。

-------

yii-graphql 特点

* 配置简化，包括简化标准 GraphQL 协议的定义。
* 基于类型的全限定类名（FQCN）实现按需/懒加载，无需在启动时预加载所有类型定义。
* 支持 mutation 输入验证。
* 提供控制器集成与授权支持。

### 安装

使用 [composer](https://getcomposer.org/):
```
composer require pomelchenko/yii2-graphql
```
需要 PHP ≥ 7.4；项目已在 [webonyx/graphql-php](https://github.com/webonyx/graphql-php) 14.x 与 [ecodev/graphql-upload](https://github.com/Ecodev/graphql-upload) 6.1.x 上测试。

### 开发

在提交之前本地运行代码风格检查：

```
composer lint

# 查看全部警告（例如超长行）
composer lint:strict
```

### Type
类型系统是 GraphQL 的核心，体现在 `GraphQLType` 中。通过解构 GraphQL 协议并利用 [graphql-php](https://github.com/webonyx/graphql-php) 库，可对各元素进行细粒度控制，便于按需扩展类。

#### `GraphQLType` 的主要元素

以下元素可在类的 `$attributes` 属性中声明，或实现为方法（若无特别说明）。

元素  | 类型 | 说明
----- | ----- | -----
`name` | string | **必须** — 类型名称，建议唯一；在 `$attributes` 中定义。
`description` | string | 类型用途描述；在 `$attributes` 中定义。
`fields` | array | **必须** — 字段集合，由 `fields()` 方法返回。
`resolveField` | callback | **function($value, $args, $context, GraphQL\Type\Definition\ResolveInfo $info)** 字段解析器。例如 `user` 字段对应方法 `resolveUserField()`；`$value` 为 `type` 定义的类型实例。

### Query

`GraphQLQuery` 与 `GraphQLMutation` 继承自 `GraphQLField`，元素结构一致；若需要可复用的 `Field`，可以继承它。每个 GraphQL 查询都对应一个 `GraphQLQuery` 对象。

#### GraphQLField 的主要元素

 元素 | 类型  | 说明
----- | ----- | -----
`type` | ObjectType | 返回的查询类型。单个类型用 `GraphQL::type` 指定，列表用 `Type::listOf(GraphQL::type)`。
`args` | array | 可用的查询参数；每个参数按 `Field` 定义。
`resolve` | callback | **function($value, $args, $context, GraphQL\Type\Definition\ResolveInfo $info)** — `$value` 为根数据，`$args` 为参数，`$context` 为 `yii\web\Application`，`$info` 为解析信息。

### Mutation

定义与 `GraphQLQuery` 类似，参考上述说明。

### 简化的字段定义

简化 `Field` 声明：可直接给出字段的类型，而无需包一层数组。

```php
// 标准方式
'id' => [
    'type' => Type::id(),
],

// 简化写法
'id' => Type::id(),
```

### 在 YII 中的实现

#### 通用配置
启用 `request` 的 JSON 解析：
```php
'components' => [
    'request' => [
        'parsers' => [
            'application/json' => \yii\web\JsonParser::class,
        ],
    ],
];
```

#### 模块支持
在模块中引入 `yii\graphql\GraphQLModuleTrait`，该 trait 负责初始化。
```php
class MyModule extends \yii\base\Module
{
    use \yii\graphql\GraphQLModuleTrait;
}
```

应用配置示例：
```php
'modules'=>[
    'moduleName' => [
        'class' => 'path\to\module',
        // graphql config
        'schema' => [
            'query' => [
                'user' => \app\graphql\query\UsersQuery::class
            ],
            'mutation' => [
                'login'
            ],
            // 如果查询包含 interface 或 fragment，可不显式设置 types；
            // 键需与定义的类名一致
            'types' => [
                'Story' => \yiiunit\extensions\graphql\objects\types\StoryType::class
            ],
        ],
    ],
];
```

通过控制器动作使用 `yii\graphql\GraphQLAction` 处理请求：
```php
class MyController extends Controller
{
    function actions()
    {
        return [
            'index' => [
                'class' => \yii\graphql\GraphQLAction::class,
            ],
        ];
    }
}
```

#### 组件支持
也可以在自定义组件中引入该 trait 并自行初始化：
```php
'components' => [
    'componentsName' => [
        'class' => 'path\to\components'
        // graphql config
        'schema' => [
            'query' => [
                'user' => \app\graphql\query\UsersQuery::class
            ],
            'mutation' => [
                'login'
            ],
            // 如果查询包含 interface 或 fragment，可不显式设置 types；
            // 键需与定义的类名一致
            'types'=>[
                'Story' => \yiiunit\extensions\graphql\objects\types\StoryType::class,
            ],
        ],
    ],
];
```

### 输入验证

支持验证规则。除基于 GraphQL 的验证外，也可使用 Yii Model 的验证来验证输入参数。直接在 mutation 中添加 `rules()` 方法：
```php
public function rules()
{
    return [
        ['password', 'boolean'],
    ];
}
```

### 测试

在本地或 Docker 中运行：

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app composer test
docker compose exec app composer test-coverage
```

覆盖 GraphQL facade、控制器、上传中间件和自定义类型，便于升级依赖时发现回归。

### GitLab 发布流水线

`.gitlab-ci.yml` 只包含一个 `publish_package` job，它利用 `CI_JOB_TOKEN` 调用 GitLab Packages API，根据 `CI_COMMIT_TAG` 将版本推送到私有 Composer Registry。若在 GitLab 中配置了仓库镜像（Settings → Repository → Mirroring repositories）并启用了 **Trigger pipelines when updates are mirrored**，每次从 GitHub 拉取到新标签时都会自动运行该 job。

推荐流程：

1. 配置镜像或从 GitHub Actions 直接 push 到 GitLab，保证 GitLab 能收到所有标签。
2. 在镜像条目上勾选 “Trigger pipelines when updates are mirrored”，让拉取操作触发 CI。
3. 在源仓库创建标签（例如 `v0.15.2`），GitLab 同步后会自动发布对应的 Composer 包，无需额外变量或手动触发。

### 认证与授权验证

由于 GraphQL 查询可以组合（例如一次请求包含多个 query），且不同 query 可能有不同的授权约束，因此需要对每个单独的 GraphQL 查询（下称 “GraphQL action”）进行校验；当所有 action 满足配置条件时才通过授权检查。

#### 认证（Authenticate）
在控制器的 `behaviors()` 方法中设置认证器：
```php
function behaviors()
{
    return [
        'authenticator' => [
            'class' => \yii\graphql\filter\auth\CompositeAuth::class,
            'authMethods' => [
                \yii\filters\auth\QueryParamAuth::class,
            ],
            'except' => ['hello'],
        ],
    ];
}
```
如需支持 IntrospectionQuery 的授权，相应的 GraphQL action 为 `__schema`。

#### 授权（Authorization）
若用户已通过认证，还可以对资源进行访问检查。可在控制器中使用 `GraphQLAction` 的 `checkAccess` 方法；它会检查所有 GraphQL actions。
```php
class GraphqlController extends Controller
{
    public function actions()
    {
        return [
            'index' => [
                'class' => \yii\graphql\GraphQLAction::class,
                'checkAccess'=> [$this, 'checkAccess'],
            ]
        ];
    }

    /**
     * authorization
     * @param $actionName
     * @throws \yii\web\ForbiddenHttpException
     */
    public function checkAccess($actionName)
    {
        $permissionName = $this->module->id . '/' . $actionName;
        $pass = Yii::$app->getAuthManager()->checkAccess(Yii::$app->user->id, $permissionName);
        if (!$pass) {
            throw new \yii\web\ForbiddenHttpException('Access Denied');
        }
    }
}
```

### Multipart 上传支持

`GraphQLAction` 支持 [`operations`/`map`](https://github.com/jaydenseric/graphql-multipart-request-spec) 规范，并借助 `ecodev/graphql-upload` middleware 将上传文件注入 GraphQL 变量。请确保请求使用 `multipart/form-data` 并携带上述字段。

### Demo

#### 创建基于 GraphQL 协议的查询

每次查询对应一个GraphQLQuery文件,
```php
class UserQuery extends GraphQLQuery
{
    public function type()
    {
        return GraphQL::type(UserType::class);
    }

    public function args()
    {
        return [
            'id'=>[
                'type'=>Type::nonNull(Type::id())
            ],
        ];
    }

    public function resolve($value, $args, $context, ResolveInfo $info)
    {
        return DataSource::findUser($args['id']);
    }
}
```

根据查询协议定义类型文件
```php
class UserType extends GraphQLType
{
    protected $attributes = [
        'name'=>'user',
        'description'=>'user is user'
    ];

    public function fields()
    {
        $result = [
            'id' => ['type'=>Type::id()],
            'email' => Types::email(),
            'email2' => Types::email(),
            'photo' => [
                'type' => GraphQL::type(ImageType::class),
                'description' => 'User photo URL',
                'args' => [
                    'size' => Type::nonNull(GraphQL::type(ImageSizeEnumType::class)),
                ]
            ],
            'firstName' => [
                'type' => Type::string(),
            ],
            'lastName' => [
                'type' => Type::string(),
            ],
            'lastStoryPosted' => GraphQL::type(StoryType::class),
            'fieldWithError' => [
                'type' => Type::string(),
                'resolve' => function() {
                    throw new \Exception("This is error field");
                }
            ]
        ];
        return $result;
    }

    public function resolvePhotoField(User $user,$args)
    {
        return DataSource::getUserPhoto($user->id, $args['size']);
    }

    public function resolveIdField(User $user, $args)
    {
        return $user->id.'test';
    }

    public function resolveEmail2Field(User $user, $args)
    {
        return $user->email2.'test';
    }


}
```

#### 查询实例

```php
'hello' => '
    query hello {
        hello
    }
',
'singleObject' => '
    query user {
        user(id:"2") {
            id
            email
            email2
            photo(size:ICON) {
                id
                url
            }
            firstName
            lastName
        }
    }
',
'multiObject' => '
    query multiObject {
        user(id: "2") {
            id
            email
            photo(size:ICON) {
                id
                url
            }
        }
        stories(after: "1") {
            id
            author{
                id
            }
            body
        }
    }
',
'updateObject' => '
    mutation updateUserPwd{
        updateUserPwd(id: "1001", password: "123456") {
            id,
            username
        }
    }
'
```

### 异常处理

可以为 GraphQL 配置错误格式化器。默认处理器使用 `yii\graphql\ErrorFormatter`，它优化了对 Model 验证结果的处理。
```php
'modules'=>[
    'moduleName' => [
       'class' => 'path\to\module'
       'errorFormatter' => ['yii\\graphql\\ErrorFormatter', 'formatError'],
    ],
];
```

### Future
* ActiveRecord generate tool for generating query and mutation class.
* 对于 GraphQL 的一些特殊语法（如 `@Directives` 等）尚未进行测试
