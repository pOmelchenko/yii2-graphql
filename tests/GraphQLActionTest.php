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

    protected function setUp()
    {
        parent::setUp();
        $this->mockWebApplication();
        $this->controller = new DefaultController('default', \Yii::$app->getModule('graphql'));
    }


    function testAction()
    {
        $_GET = [
            'query' => $this->queries['hello'],
        ];
        $controller = $this->controller;
        $ret = $controller->runAction('index');
        $this->assertNotEmpty($ret);
    }

    function testRunError()
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

    function testAuthBehavior()
    {
        $_GET = [
            'query' => $this->queries['hello'],
            'access-token' => 'testtoken',
        ];
        $controller = $this->controller;
        $controller->attachBehavior('authenticator', [
            'class' => QueryParamAuth::className()
        ]);
        $ret = $controller->runAction('index');
        $this->assertNotEmpty($ret);
    }

    function testAuthBehaviorDoesNotStartSession()
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
            'class' => QueryParamAuth::className()
        ]);

        $controller->runAction('index');

        $this->assertSame(PHP_SESSION_NONE, session_status(), 'Auth should not start PHP session');
    }

    function testAuthBehaviorExcept()
    {
        $_GET = [
            'query' => $this->queries['hello'],
        ];
        $controller = $this->controller;
        $controller->attachBehavior('authenticator', [
            'class' => CompositeAuth::className(),
            'authMethods' => [
                \yii\filters\auth\QueryParamAuth::className(),
            ],
            'except' => ['hello'],
        ]);
        $ret = $controller->runAction('index');
        $this->assertNotEmpty($ret);
    }

    function testIntrospectionQuery()
    {
        $_GET = [
            'query' => $this->queries['introspectionQuery'],
        ];
        $controller = $this->controller;
        $controller->attachBehavior('authenticator', [
            'class' => CompositeAuth::className(),
            'authMethods' => [
                \yii\filters\auth\QueryParamAuth::className(),
            ],
            'except' => ['__schema'],
        ]);
        $ret = $controller->runAction('index');
        $this->assertNotEmpty($ret);
    }

    function testJsonStringVariablesAreDecoded()
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

    function testRawBodyIsUsedWhenBodyParamsAreEmpty()
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
}
