<?php

namespace yiiunit\extensions\graphql;

use yii\filters\auth\QueryParamAuth;
use yii\graphql\filters\auth\CompositeAuth;
use yii\graphql\GraphQLAction;
use yii\web\Controller;

class GraphQLActionTest extends TestCase
{
    /**
     * @var DefaultController
     */
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
        $this->controller = new DefaultController('default', \Yii::$app->getModule('graphql'));
    }


    public function testAction()
    {
        $_GET = [
            'query' => $this->queries['hello'],
        ];
        $controller = $this->controller;
        $ret = $controller->runAction('index');
        $this->assertNotEmpty($ret);
    }

    public function testRunError()
    {
        $_GET = [
            'query' => 'query error{error}',
        ];
        $controller = $this->controller;
        $action = $controller->createAction('index');
        $action->enableSchemaAssertValid = false;
        $ret = $action->runWithParams([]);
        $this->assertNotEmpty($ret);
        $this->assertArrayHasKey('errors', $ret);
        $this->assertArrayHasKey('code', $ret['errors'][0]);
        $this->assertSame(404, $ret['errors'][0]['code']);
        $this->assertSame('Schema not found for requested operation.', $ret['errors'][0]['message']);
    }

    public function testAuthBehavior()
    {
        $_GET = [
            'query' => $this->queries['hello'],
            'access-token' => 'testtoken',
        ];
        $controller = $this->controller;
        $controller->attachBehavior('authenticator', [
            'class' => QueryParamAuth::class
        ]);
        $ret = $controller->runAction('index');
        $this->assertNotEmpty($ret);
    }

    public function testAuthBehaviorDoesNotStartSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $_GET = [
            'query' => $this->queries['hello'],
            'access-token' => 'testtoken',
        ];
        $controller = $this->controller;
        $controller->attachBehavior('authenticator', [
            'class' => QueryParamAuth::class
        ]);

        $controller->runAction('index');

        $this->assertSame(PHP_SESSION_NONE, session_status(), 'Auth should not start PHP session');
    }

    public function testAuthBehaviorExcept()
    {
        $_GET = [
            'query' => $this->queries['hello'],
        ];
        $controller = $this->controller;
        $controller->attachBehavior('authenticator', [
            'class' => CompositeAuth::class,
            'authMethods' => [
                \yii\filters\auth\QueryParamAuth::class,
            ],
            'except' => ['hello'],
        ]);
        $ret = $controller->runAction('index');
        $this->assertNotEmpty($ret);
    }

    public function testAuthBehaviorExceptStillInvokesCheckAccess()
    {
        $_GET = [
            'query' => $this->queries['hello'],
        ];
        $invoked = [];
        $controller = new class ('default', \Yii::$app->getModule('graphql')) extends DefaultController {
            public $actionCheckAccess;

            public function actions()
            {
                return [
                    'index' => [
                        'class' => GraphQLAction::class,
                        'checkAccess' => $this->actionCheckAccess,
                    ],
                ];
            }
        };
        $controller->actionCheckAccess = function ($actionName) use (&$invoked) {
            $invoked[] = $actionName;
        };
        $controller->attachBehavior('authenticator', [
            'class' => CompositeAuth::class,
            'authMethods' => [
                \yii\filters\auth\QueryParamAuth::class,
            ],
            'except' => ['hello'],
        ]);

        $result = $controller->runAction('index');

        $this->assertArrayHasKey('data', $result);
        $this->assertSame(['hello'], $invoked);
    }

    public function testIntrospectionQuery()
    {
        $_GET = [
            'query' => $this->queries['introspectionQuery'],
        ];
        $controller = $this->controller;
        $controller->attachBehavior('authenticator', [
            'class' => CompositeAuth::class,
            'authMethods' => [
                \yii\filters\auth\QueryParamAuth::class,
            ],
            'except' => ['__schema'],
        ]);
        $ret = $controller->runAction('index');
        $this->assertNotEmpty($ret);
    }

    public function testRootFragmentIntrospectionQueryUsesIntrospectionExemption()
    {
        $_GET = [
            'query' => <<<'GRAPHQL'
                query IntrospectionQuery {
                    __typename
                    ...RootIntrospection
                }
                fragment RootIntrospection on Query {
                    __schema { queryType { name } }
                }
                GRAPHQL,
        ];
        $this->controller->attachBehavior('authenticator', [
            'class' => CompositeAuth::class,
            'authMethods' => [
                \yii\filters\auth\QueryParamAuth::class,
            ],
            'except' => ['__schema'],
        ]);

        $result = $this->controller->runAction('index');

        $this->assertSame('Query', $result['data']['__typename']);
        $this->assertSame('Query', $result['data']['__schema']['queryType']['name']);
    }

    public function testSelectedApplicationOperationCannotUseIntrospectionExemption()
    {
        $_GET = [
            'query' => <<<'GRAPHQL'
                query Schema { __schema { queryType { name } } }
                query ReadUser { user(id: "1") { id } }
                GRAPHQL,
            'operationName' => 'ReadUser',
        ];
        $this->controller->attachBehavior('authenticator', [
            'class' => CompositeAuth::class,
            'authMethods' => [
                \yii\filters\auth\QueryParamAuth::class,
            ],
            'except' => ['__schema'],
        ]);

        $this->expectException(\yii\web\UnauthorizedHttpException::class);
        $this->controller->runAction('index');
    }

    public function testMixedApplicationQueryCannotUseIntrospectionExemption()
    {
        $_GET = [
            'query' => <<<'GRAPHQL'
                query MixedQuery {
                    __schema { queryType { name } }
                    user(id: "1") { id }
                }
                GRAPHQL,
        ];
        $this->controller->attachBehavior('authenticator', [
            'class' => CompositeAuth::class,
            'authMethods' => [
                \yii\filters\auth\QueryParamAuth::class,
            ],
            'except' => ['__schema'],
        ]);

        $this->expectException(\yii\web\UnauthorizedHttpException::class);
        $this->controller->runAction('index');
    }

    public function testIntrospectionCapabilitiesQueryDoesNotThrowSchemaNotFound()
    {
        $_GET = [
            'query' => $this->queries['introspectionCapabilitiesQuery'],
        ];

        $result = $this->controller->runAction('index');

        $this->assertArrayHasKey('data', $result);
        if (isset($result['errors'])) {
            $messages = array_map(static function ($error) {
                return $error['message'] ?? '';
            }, $result['errors']);
            $this->assertNotContains('Schema not found for requested operation.', $messages);
        }
    }

    public function testJsonStringVariablesAreDecoded()
    {
        $query = <<<'GRAPHQL'
        query user($id: ID!) {
            user(id: $id) {
                id
                email
            }
        }
        GRAPHQL;

        $_GET = [
            'query' => $query,
            'variables' => json_encode(['id' => '2']),
        ];

        $result = $this->controller->runAction('index');

        $this->assertSame('2', $result['data']['user']['id']);
        $this->assertSame('jane@example.com', $result['data']['user']['email']);
    }

    public function testRawBodyIsUsedWhenBodyParamsAreEmpty()
    {
        $request = \Yii::$app->request;
        $previousRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request->setBodyParams([]);
        $request->setRawBody($this->queries['singleObject']);

        $result = $this->controller->createAction('index')->runWithParams([]);

        $_SERVER['REQUEST_METHOD'] = $previousRequestMethod;
        $request->setRawBody('');

        $this->assertSame('2', $result['data']['user']['id']);
        $this->assertSame('jane@example.com', $result['data']['user']['email']);
        $this->assertArrayHasKey('url', $result['data']['user']['photo']);
        $this->assertStringContainsString('/images/user/2-icon.jpg', $result['data']['user']['photo']['url']);
    }

    public function testCheckAccessCallbacksAreInvokedForEachRequestedAction()
    {
        $_GET = [
            'query' => $this->queries['multiObject'],
        ];

        $action = $this->controller->createAction('index');

        $invoked = [];
        $action->checkAccess = function ($actionName) use (&$invoked) {
            $invoked[] = $actionName;
        };

        $action->runWithParams([]);

        sort($invoked);
        $this->assertSame(['stories', 'user'], $invoked);
    }

    public function testRemovedGraphQLActionsAreNotReinitializedBeforeAccessCheck()
    {
        $_GET = [
            'query' => $this->queries['hello'],
        ];

        $action = $this->controller->createAction('index');
        $action->removeGraphQlAction('hello');

        $called = false;
        $action->checkAccess = function () use (&$called) {
            $called = true;
        };

        $result = $action->runWithParams([]);

        $this->assertFalse($called);
        $this->assertArrayHasKey('data', $result);
    }

    public function testRunReturns404WhenSchemaMissing()
    {
        $_GET = [
            'query' => 'query { unknown }',
        ];

        $result = $this->controller->runAction('index');

        $this->assertArrayHasKey('errors', $result);
        $this->assertSame(404, $result['errors'][0]['code']);
    }

    public function testMultipartUploadRequestIsParsed()
    {
        $request = \Yii::$app->request;
        $previousBody = $request->getBodyParams();
        $previousPost = $_POST;
        $previousContentType = $_SERVER['CONTENT_TYPE'] ?? null;
        $previousFiles = $_FILES;
        $previousMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $json = json_encode([
            'query' => <<<'GRAPHQL'
            query user($id: ID!) {
                user(id: $id) {
                    id
                }
            }
            GRAPHQL,
            'variables' => ['id' => 1],
        ]);
        $map = json_encode(['1' => ['variables.file']]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=test';
        $payload = [
            'operations' => $json,
            'map' => $map,
        ];
        $_POST = $payload;
        $request->setBodyParams($payload);
        $request->setRawBody('');
        $_FILES = [
            '1' => [
                'name' => 'dummy.txt',
                'type' => 'text/plain',
                'tmp_name' => __FILE__,
                'error' => UPLOAD_ERR_OK,
                'size' => 10,
            ],
        ];

        $action = $this->controller->createAction('index');
        $result = $action->runWithParams([]);

        $request->setBodyParams($previousBody);
        $_POST = $previousPost;
        $_FILES = $previousFiles;
        $_SERVER['REQUEST_METHOD'] = $previousMethod;
        if ($previousContentType === null) {
            unset($_SERVER['CONTENT_TYPE']);
        } else {
            $_SERVER['CONTENT_TYPE'] = $previousContentType;
        }

        $this->assertArrayHasKey('data', $result);
        $this->assertSame('1', $result['data']['user']['id']);
    }

    public function testUnsetPostRequirementPreservesLegacyMutationAndEmitsDeprecation()
    {
        $_GET = [
            'query' => 'mutation { updateUserPwd(id: "qsli@google.com", password: "newpwd") { id } }',
        ];
        $deprecation = null;
        set_error_handler(function ($severity, $message) use (&$deprecation) {
            if ($severity === E_USER_DEPRECATED) {
                $deprecation = $message;
                return true;
            }
            return false;
        });

        try {
            $result = $this->controller->createAction('index')->runWithParams([]);
        } finally {
            restore_error_handler();
        }

        $this->assertSame('1', $result['data']['updateUserPwd']['id']);
        $this->assertStringContainsString(
            'GraphQLAction::$requirePostForMutations is deprecated',
            $deprecation
        );
    }

    public function testExplicitLegacyMutationOptOutDoesNotEmitDeprecation()
    {
        $_GET = [
            'query' => 'mutation { updateUserPwd(id: "qsli@google.com", password: "newpwd") { id } }',
        ];
        $deprecation = null;
        set_error_handler(function ($severity, $message) use (&$deprecation) {
            if ($severity === E_USER_DEPRECATED) {
                $deprecation = $message;
                return true;
            }
            return false;
        });

        try {
            $action = new GraphQLAction('index', $this->controller, [
                'requirePostForMutations' => false,
            ]);
            $result = $action->runWithParams([]);
        } finally {
            restore_error_handler();
        }

        $this->assertNull($deprecation);
        $this->assertSame('1', $result['data']['updateUserPwd']['id']);
    }

    public function testPostMutationWithUnsetRequirementDoesNotEmitDeprecation()
    {
        $request = \Yii::$app->request;
        $previousBody = $request->getBodyParams();
        $previousRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request->setBodyParams([
            'query' => 'mutation { updateUserPwd(id: "qsli@google.com", password: "newpwd") { id } }',
        ]);
        $deprecation = null;
        set_error_handler(function ($severity, $message) use (&$deprecation) {
            if ($severity === E_USER_DEPRECATED) {
                $deprecation = $message;
                return true;
            }
            return false;
        });

        try {
            $result = $this->controller->createAction('index')->runWithParams([]);
        } finally {
            restore_error_handler();
            $_SERVER['REQUEST_METHOD'] = $previousRequestMethod;
            $request->setBodyParams($previousBody);
        }

        $this->assertNull($deprecation);
        $this->assertSame('1', $result['data']['updateUserPwd']['id']);
    }

    /**
     * @dataProvider nonPostMutationMethodsProvider
     */
    public function testMutationOverNonPostMethodIsRejectedWhenRequired($method)
    {
        $request = \Yii::$app->request;
        $previousGet = $_GET;
        $previousBody = $request->getBodyParams();
        $previousRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $_SERVER['REQUEST_METHOD'] = $method;
        $_GET = [
            'query' => 'mutation { updateUserPwd(id: "qsli@google.com", password: "newpwd") { id } }',
        ];
        $request->setBodyParams([
            'query' => 'mutation { updateUserPwd(id: "qsli@google.com", password: "newpwd") { id } }',
        ]);

        try {
            $this->expectException(\yii\web\MethodNotAllowedHttpException::class);
            new GraphQLAction('index', $this->controller, [
                'requirePostForMutations' => true,
            ]);
        } finally {
            $_SERVER['REQUEST_METHOD'] = $previousRequestMethod;
            $_GET = $previousGet;
            $request->setBodyParams($previousBody);
        }
    }

    public function nonPostMutationMethodsProvider()
    {
        return [
            'GET' => ['GET'],
            'PUT' => ['PUT'],
            'PATCH' => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    public function testPostMutationIsAllowedWhenPostIsRequired()
    {
        $request = \Yii::$app->request;
        $previousBody = $request->getBodyParams();
        $previousRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request->setBodyParams([
            'query' => 'mutation { updateUserPwd(id: "qsli@google.com", password: "newpwd") { id } }',
        ]);

        try {
            $action = new GraphQLAction('index', $this->controller, [
                'requirePostForMutations' => true,
            ]);
            $result = $action->runWithParams([]);
        } finally {
            $_SERVER['REQUEST_METHOD'] = $previousRequestMethod;
            $request->setBodyParams($previousBody);
        }

        $this->assertSame('1', $result['data']['updateUserPwd']['id']);
    }

    public function testMutationWithoutAccessCheckIsRejectedWhenRequired()
    {
        $request = \Yii::$app->request;
        $previousBody = $request->getBodyParams();
        $previousRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request->setBodyParams([
            'query' => 'mutation { updateUserPwd(id: "qsli@google.com", password: "newpwd") { id } }',
        ]);

        try {
            $this->expectException(\yii\web\ForbiddenHttpException::class);
            new GraphQLAction('index', $this->controller, [
                'requireAccessCheckForMutations' => true,
            ]);
        } finally {
            $_SERVER['REQUEST_METHOD'] = $previousRequestMethod;
            $request->setBodyParams($previousBody);
        }
    }

    public function testConfiguredMutationAccessCheckIsInvokedWithoutCompositeAuth()
    {
        $request = \Yii::$app->request;
        $previousBody = $request->getBodyParams();
        $previousRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request->setBodyParams([
            'query' => 'mutation { updateUserPwd(id: "qsli@google.com", password: "newpwd") { id } }',
        ]);
        $invoked = [];

        try {
            $action = new GraphQLAction('index', $this->controller, [
                'requireAccessCheckForMutations' => true,
                'checkAccess' => function ($actionName) use (&$invoked) {
                    $invoked[] = $actionName;
                },
            ]);
            $result = $action->runWithParams([]);
        } finally {
            $_SERVER['REQUEST_METHOD'] = $previousRequestMethod;
            $request->setBodyParams($previousBody);
        }

        $this->assertSame(['updateUserPwd'], $invoked);
        $this->assertSame('1', $result['data']['updateUserPwd']['id']);
    }

    public function testSelectedQueryDoesNotTriggerMutationRequirements()
    {
        $_GET = [
            'query' => <<<'GRAPHQL'
                query ReadUser { user(id: "1") { id } }
                mutation ChangePassword {
                    updateUserPwd(id: "qsli@google.com", password: "newpwd") { id }
                }
                GRAPHQL,
            'operationName' => 'ReadUser',
        ];

        $action = new GraphQLAction('index', $this->controller, [
            'requirePostForMutations' => true,
            'requireAccessCheckForMutations' => true,
        ]);
        $result = $action->runWithParams([]);

        $this->assertSame('1', $result['data']['user']['id']);
    }

    public function testSelectedMutationTriggersPostRequirement()
    {
        $_GET = [
            'query' => <<<'GRAPHQL'
                query ReadUser { user(id: "1") { id } }
                mutation ChangePassword {
                    updateUserPwd(id: "qsli@google.com", password: "newpwd") { id }
                }
                GRAPHQL,
            'operationName' => 'ChangePassword',
        ];

        $this->expectException(\yii\web\MethodNotAllowedHttpException::class);
        new GraphQLAction('index', $this->controller, [
            'requirePostForMutations' => true,
        ]);
    }

    public function testIntrospectionQueryReturnsSpecialActions()
    {
        $_GET = [
            'query' => $this->queries['introspectionQuery'],
        ];
        $action = $this->controller->createAction('index');

        $actions = $action->getGraphQLActions();
        $this->assertArrayHasKey(GraphQLAction::INTROSPECTIONQUERY, $actions);

        $called = false;
        $action->checkAccess = function () use (&$called) {
            $called = true;
        };
        $action->runWithParams([]);

        $this->assertFalse($called, 'Introspection should not trigger checkAccess callbacks');
    }
}
