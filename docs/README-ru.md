yii-graphql
==========
Реализация серверной части Facebook [GraphQL](http://facebook.github.io/graphql/) на PHP. Расширяет [graphql-php](https://github.com/webonyx/graphql-php) для применения в YII2.

[![Latest Stable Version](https://poser.pugx.org/pomelchenko/yii2-graphql/v/stable.svg)](https://packagist.org/packages/pomelchenko/yii2-graphql)
[![CI](https://github.com/pOmelchenko/yii2-graphql/actions/workflows/ci.yml/badge.svg)](https://github.com/pOmelchenko/yii2-graphql/actions)
[![Coverage Status](https://codecov.io/gh/pOmelchenko/yii2-graphql/branch/master/graph/badge.svg)](https://codecov.io/gh/pOmelchenko/yii2-graphql)
[![Total Downloads](https://poser.pugx.org/pomelchenko/yii2-graphql/downloads.svg)](https://packagist.org/packages/pomelchenko/yii2-graphql)

Языки: [English](/README.md) | [Русский](/docs/README-ru.md) | [中文](/docs/README-zh.md)

-------

> Проект изначально был создан [tsingsun](https://github.com/tsingsun) и продолжает развиваться в этом форке.

### Особенности

- Упрощённая конфигурация, включая декларации стандартных протоколов GraphQL.
- Поддержка отложенной и по‑требованию загрузки типов по их полным именам классов (FQCN) — не нужно загружать все типы на старте.
- Поддержка валидации входных данных для `mutation`.
- Интеграция с контроллерами и поддержка авторизации.

### Установка

Через [composer](https://getcomposer.org/):
```
composer require pomelchenko/yii2-graphql
```
Требуется PHP ≥ 7.4; протестировано с [webonyx/graphql-php](https://github.com/webonyx/graphql-php) 14.x и [ecodev/graphql-upload](https://github.com/Ecodev/graphql-upload) 6.1.x.

### Типы (`GraphQLType`)
Типовая система — ядро GraphQL и представлена классом `GraphQLType`. Путём декомпозиции протокола GraphQL и использования библиотеки [graphql-php](https://github.com/webonyx/graphql-php) обеспечивается тонкий контроль над элементами и удобное расширение классов.

#### Основные элементы `GraphQLType`
Элементы могут быть объявлены в свойстве `$attributes` класса либо методами (если не оговорено иное).

Элемент | Тип | Описание
----- | ----- | -----
`name` | string | **Обязательно** — имя типа. Желательно уникальное; задаётся в `$attributes`.
`description` | string | Описание типа и его назначения; задаётся в `$attributes`.
`fields` | array | **Обязательно** — набор полей, возвращаемый методом `fields()`.
`resolveField` | callback | **function($value, $args, $context, GraphQL\Type\Definition\ResolveInfo $info)** — резолвер поля. Для поля `user` метод будет `resolveUserField()`. `$value` — экземпляр типа, определённого в `type`.

### Query
`GraphQLQuery` и `GraphQLMutation` наследуются от `GraphQLField` и имеют одинаковую структуру элементов. Каждый запрос GraphQL соответствует объекту `GraphQLQuery`.

#### Основные элементы `GraphQLField`
Элемент | Тип  | Описание
----- | ----- | -----
`type` | ObjectType | Возвращаемый тип. Один элемент — через `GraphQL::type`, список — `Type::listOf(GraphQL::type)`.
`args` | array | Аргументы запроса; каждый параметр описывается как `Field`.
`resolve` | callback | **function($value, $args, $context, GraphQL\Type\Definition\ResolveInfo $info)** — `$value` корневые данные, `$args` аргументы, `$context` — `yii\web\Application`, `$info` — информация о резолве.

### Mutation
Определяется аналогично `GraphQLQuery`.

### Упрощённое объявление полей
Можно указывать тип поля напрямую, без массива с ключом `type`.

Стандартное объявление
```php
//...
'id' => [
    'type' => Type::id(),
],
//...
```

Упрощённое объявление
```php
//...
'id' => Type::id(),
//...
```

### Интеграция с Yii

### Общая конфигурация
Включите парсер JSON для `request`:
```php
'components' => [
    'request' => [
        'parsers' => [
            'application/json' => 'yii\\web\\JsonParser',
        ],
    ],
];
```

#### Модуль
Подключите `yii\graphql\GraphQLModuleTrait` в модуле — трейт отвечает за инициализацию.
```php
class MyModule extends \\yii\\base\\Module
{
    use \\yii\\graphql\\GraphQLModuleTrait;
}
```

Пример конфигурации:
```php
'modules'=>[
    'moduleName' => [
        'class' => 'path\\to\\module',
        // graphql config
        'schema' => [
            'query' => [
                'user' => 'app\\graphql\\query\\UsersQuery'
            ],
            'mutation' => [
                'login'
            ],
            // если запросы содержат интерфейсы или фрагменты, можно не задавать types
            // ключ должен совпадать с именем класса
            'types' => [
                'Story' => 'yiiunit\\extensions\\graphql\\objects\\types\\StoryType'
            ],
        ],
    ],
];
```

Используйте контроллер с `yii\graphql\GraphQLAction` для приёма запросов:
```php
class MyController extends Controller
{
   function actions() {
       return [
            'index'=>[
                'class'=>'yii\\graphql\\GraphQLAction'
            ],
       ];
   }
}
```

#### Компонент
Альтернативно, можно подключить трейт в собственный компонент и инициализировать его самостоятельно.
```php
'components'=>[
    'componentsName' => [
        'class' => 'path\\to\\components',
        // graphql config
        'schema' => [
            'query' => [
                'user' => 'app\\graphql\\query\\UsersQuery'
            ],
            'mutation' => [
                'login'
            ],
            // если запросы содержат интерфейсы или фрагменты, можно не задавать types
            // ключ должен совпадать с именем класса
            'types'=>[
                'Story'=>'yiiunit\\extensions\\graphql\\objects\\types\\StoryType'
            ],
        ],
    ],
];
```

### Валидация входных данных
Поддерживаются правила валидации. Помимо встроенной в GraphQL, можно использовать валидацию моделей Yii для входных параметров. Добавьте метод `rules()` прямо в `mutation`.
```php
public function rules() {
    return [
        ['password','boolean']
    ];
}
```

### Аутентификация и авторизация
Поскольку запросы GraphQL могут комбинироваться, разные части запроса могут иметь разные ограничения. Отдельные части называем «GraphQL actions»; проверка проходит, когда условия выполнены для всех действий.

#### Аутентификация
Задайте аутентификацию в `behaviors()` контроллера:
```php
function behaviors() {
    return [
        'authenticator'=>[
            'class' => 'yii\\graphql\\filter\\auth\\CompositeAuth',
            'authMethods' => [
                \\yii\\filters\\auth\\QueryParamAuth::className(),
            ],
            'except' => ['hello']
        ],
    ];
}
```
Для поддержки авторизации в IntrospectionQuery используйте действие `__schema`.

#### Авторизация
Если пользователь аутентифицирован, можно дополнительно проверить доступ к ресурсу через `checkAccess` у `GraphQLAction`.
```php
class GraphqlController extends Controller
{
    public function actions() {
        return [
            'index' => [
                'class' => 'yii\\graphql\\GraphQLAction',
                'checkAccess'=> [$this,'checkAccess'],
            ]
        ];
    }

    /**
     * authorization
     * @param $actionName
     * @throws yii\\web\\ForbiddenHttpException
     */
    public function checkAccess($actionName) {
        $permissionName = $this->module->id . '/' . $actionName;
        $pass = Yii::$app->getAuthManager()->checkAccess(Yii::$app->user->id,$permissionName);
        if (!$pass){
            throw new yii\\web\\ForbiddenHttpException('Access Denied');
        }
    }
}
```

### Поддержка мультизагрузки (multipart)

`GraphQLAction` поддерживает спецификацию [`operations`/`map`](https://github.com/jaydenseric/graphql-multipart-request-spec) и автоматически подставляет файлы в переменные GraphQL через middleware `ecodev/graphql-upload`. Отправляйте `multipart/form-data` запросы с указанными полями.

### Тестирование

Запустить тесты (можно внутри Docker):

```
docker compose up -d --build
docker compose exec app composer install
docker compose exec app composer test
docker compose exec app composer test-coverage
```

Эти команды прогоняют фасад GraphQL, контроллер, поддержку загрузок и кастомные типы, фиксируя регрессии при обновлении зависимостей.

### Пайплайн GitLab

В `.gitlab-ci.yml` уже описан пайплайн, который подтягивает изменения из апстрима и отправляет Composer-пакет в приватный GitLab Registry. Чтобы включить синхронизацию, задайте в настройках CI/CD две переменные:

- `UPSTREAM_URL` — HTTPS/SSH-адрес репозитория, который выступает «источником правды». Ничего не указывайте, если текущий проект — основной.
- `UPSTREAM_BRANCH` — имя ветки, из которой надо подтягивать изменения (например, `master` или `main`). Job `sync_upstream` выполняется только если ветка пайплайна совпадает с этим значением.

Переменные нужны только форкам, которые должны идти в ногу с другим репозиторием. В этом случае `sync_upstream` делает fast-forward и пушит результат перед ручным, теговым `publish_package`, уведомляющим GitLab Packages о публикации Composer-артефакта. Если синхронизация не требуется, достаточно одного релизного job.

### Демонстрация

#### Создание запроса по протоколу GraphQL
Каждому запросу соответствует файл `GraphQLQuery`.
```php
class UserQuery extends GraphQLQuery
{
    public function type() {
        return GraphQL::type(UserType::class);
    }

    public function args() {
        return [
            'id'=>[
                'type' => Type::nonNull(Type::id())
            ],
        ];
    }

    public function resolve($value, $args, $context, ResolveInfo $info) {
        return DataSource::findUser($args['id']);
    }
}
```

Определение типа по протоколу запроса
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
                    throw new \\Exception("This is error field");
                }
            ]
        ];
        return $result;
    }

    public function resolvePhotoField(User $user,$args){
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

#### Примеры запросов
```php
'hello' =>  "
        query hello{hello}
    ",

    'singleObject' =>  "
        query user {
            user(id:\"2\") {
                id
                email
                email2
                photo(size:ICON){
                    id
                    url
                }
                firstName
                lastName

            }
        }
    ",
    'multiObject' =>  "
        query multiObject {
            user(id: \"2\") {
                id
                email
                photo(size:ICON){
                    id
                    url
                }
            }
            stories(after: \"1\") {
                id
                author{
                    id
                }
                body
            }
        }
    ",
    'updateObject' =>  "
        mutation updateUserPwd{
            updateUserPwd(id: \"1001\", password: \"123456\") {
                id,
                username
            }
        }
    "
```

### Обработка исключений
Можно настроить форматирование ошибок. По умолчанию используется `yii\graphql\ErrorFormatter`, который оптимизирует обработку результатов валидации моделей.
```php
'modules'=>[
    'moduleName' => [
       'class' => 'path\\to\\module',
       'errorFormatter' => ['yii\\graphql\\ErrorFormatter', 'formatError'],
    ],
];
```

### План
- Инструмент для генерации классов запросов и мутаций на основе `ActiveRecord`.
- Тестирование специального синтаксиса GraphQL (например, `@Directives`).
