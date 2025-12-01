<?php

namespace yiiunit\extensions\graphql;

use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\graphql\filters\auth\CompositeAuth;
use yii\graphql\GraphQLAction;

class CompositeAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    public function testAuthenticateReturnsFirstIdentity()
    {
        $auth = new CompositeAuth();
        $auth->authMethods = [
            FailingAuth::class,
            SuccessfulAuth::class,
        ];

        $identity = $auth->authenticate(
            \Yii::$app->user,
            \Yii::$app->request,
            \Yii::$app->response
        );

        $this->assertSame('user-1', $identity);
    }

    public function testAuthenticateThrowsOnInvalidMethod()
    {
        $auth = new CompositeAuth();
        $auth->authMethods = [InvalidAuth::class];

        $this->expectException(InvalidConfigException::class);
        $auth->authenticate(\Yii::$app->user, \Yii::$app->request, \Yii::$app->response);
    }

    public function testBeforeActionSkipsWhenNoMethods()
    {
        $auth = new CompositeAuth();
        $controller = new DefaultController('default', \Yii::$app->getModule('graphql'));
        $action = new Action('index', $controller);

        $this->assertTrue($auth->beforeAction($action));
    }

    public function testIsActiveEvaluatesPatterns()
    {
        $controller = new DefaultController('default', \Yii::$app->getModule('graphql'));
        $action = new GraphQLActionStub($controller, [
            'hello' => 'HelloQuery',
            'user' => 'UserQuery',
        ]);

        $auth = new CompositeAuthStub();
        $this->assertTrue($auth->callIsActive($action));

        $authOnly = new CompositeAuthStub();
        $authOnly->only = ['foo*'];
        $this->assertFalse($authOnly->callIsActive($action));

        $authExcept = new CompositeAuthStub();
        $authExcept->except = ['hello', 'user'];
        $this->assertFalse($authExcept->callIsActive($action));
        $this->assertSame(['hello', 'user'], $action->removedKeys);
    }

    public function testChallengeInvokesAllAuthMethods()
    {
        $auth = new CompositeAuth();
        $auth->authMethods = [
            new ChallengeAuth(),
            new ChallengeAuth(),
        ];

        $response = \Yii::$app->response;
        $auth->challenge($response);

        $this->assertSame(2, ChallengeAuth::$challenged);
        ChallengeAuth::$challenged = 0;
    }

    public function testBeforeActionRunsParentWhenMethodsSet()
    {
        $auth = new CompositeAuth();
        $auth->authMethods = [SuccessfulAuth::class];
        $controller = new DefaultController('default', \Yii::$app->getModule('graphql'));
        $action = new Action('index', $controller);

        $this->assertTrue($auth->beforeAction($action));
    }

    public function testAuthenticateReturnsNullWhenNoIdentity()
    {
        $auth = new CompositeAuth();
        $auth->authMethods = [
            FailingAuth::class,
            FailingAuth::class,
        ];

        $identity = $auth->authenticate(\Yii::$app->user, \Yii::$app->request, \Yii::$app->response);
        $this->assertNull($identity);
    }

    public function testIsActiveHandlesOnlyPatterns()
    {
        $controller = new DefaultController('default', \Yii::$app->getModule('graphql'));
        $action = new GraphQLActionStub($controller, [
            'hello' => 'HelloQuery',
            'userModel' => 'UserModelQuery',
        ]);

        $auth = new CompositeAuthStub();
        $auth->only = ['user*'];

        $this->assertFalse($auth->callIsActive($action));
    }
}

class FailingAuth extends \yii\filters\auth\AuthMethod
{
    public function authenticate($user, $request, $response)
    {
        return null;
    }
}

class SuccessfulAuth extends \yii\filters\auth\AuthMethod
{
    public function authenticate($user, $request, $response)
    {
        return 'user-1';
    }
}

class InvalidAuth extends \yii\base\BaseObject
{
}

class ChallengeAuth extends \yii\filters\auth\AuthMethod
{
    public static $challenged = 0;

    public function authenticate($user, $request, $response)
    {
        return null;
    }

    public function challenge($response)
    {
        self::$challenged++;
    }
}

class CompositeAuthStub extends CompositeAuth
{
    public function callIsActive($action)
    {
        return parent::isActive($action);
    }
}

class GraphQLActionStub extends GraphQLAction
{
    public array $removedKeys = [];

    private array $maps;

    public function __construct($controller, array $maps)
    {
        parent::__construct('index', $controller, []);
        $this->maps = $maps;
    }

    public function init()
    {
        // Skip parent init to avoid parsing actual HTTP requests.
    }

    public function getGraphQLActions()
    {
        return $this->maps;
    }

    public function removeGraphQlAction($key)
    {
        $this->removedKeys[] = $key;
        unset($this->maps[$key]);
    }

    public function run()
    {
        return [];
    }
}
