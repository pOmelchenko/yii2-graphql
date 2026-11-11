<?php

namespace yiiunit\extensions\graphql;

use GraphQL\Type\Definition\Type;
use yii\base\Controller;
use yii\base\Module as YiiModule;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\filters\auth\QueryParamAuth;
use yii\graphql\GraphQLAction;
use yii\graphql\filters\auth\CompositeAuth;
use yii\web\HttpException;
use yii\web\Response;

class GraphQLActionLifecycleTest extends TestCase
{
    private $controller;
    private $events = [];
    private $resolverCalls = 0;
    private $formatDuringAudit;

    protected function setUp(): void
    {
        parent::setUp();
        $response = new class (['charset' => 'UTF-8']) extends Response {
            public function send()
            {
                // Prepare real error responses without sending headers or output from PHPUnit.
                $this->prepare();
            }
        };
        $this->mockWebApplication(['components' => ['response' => $response]]);
        $module = \Yii::$app->getModule('graphql');
        $module->schema['mutation'] = [
            'touch' => [
                'type' => Type::boolean(),
                'resolve' => function () {
                    $this->resolverCalls++;
                    return true;
                },
            ],
        ];
        $module->on(YiiModule::EVENT_BEFORE_ACTION, function () {
            $this->events[] = 'module';
        });
        $this->controller = new class ('default', $module) extends DefaultController {
            public $actionOptions = [];

            public function actions()
            {
                return ['index' => array_merge(['class' => GraphQLAction::class], $this->actionOptions)];
            }
        };
        $this->controller->enableCsrfValidation = false;
        $this->controller->attachBehavior('cors', [
            'class' => Cors::class,
            'cors' => ['Origin' => ['https://client.example']],
        ]);
        $this->controller->attachBehavior('negotiator', [
            'class' => ContentNegotiator::class,
            'formats' => ['application/json' => Response::FORMAT_JSON],
        ]);
        $this->controller->attachBehavior('authenticator', [
            'class' => CompositeAuth::class,
            'authMethods' => [QueryParamAuth::class],
        ]);
        $this->controller->on(Controller::EVENT_BEFORE_ACTION, function () {
            $this->events[] = 'controller audit';
            $this->formatDuringAudit = \Yii::$app->response->format;
        });
    }

    private function prepareRequest($method, $authenticated = true): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['HTTP_ORIGIN'] = 'https://client.example';
        $request = \Yii::$app->request;
        $query = ['query' => 'mutation { touch }'];
        if ($authenticated) {
            $query['access-token'] = 'testtoken';
        }
        $request->setQueryParams($query);
        $request->setBodyParams(['query' => 'mutation { touch }']);
        $request->headers->set('Accept', 'application/json');
    }

    private function runAndRender(): Response
    {
        $response = \Yii::$app->response;
        try {
            $result = $this->controller->runAction('index');
            if ($result !== null) {
                $response->data = $result;
                $response->send();
            }
        } catch (HttpException $exception) {
            // Exercise Yii's actual exception-to-status/JSON conversion without its exit handler.
            $this->invoke(\Yii::$app->getErrorHandler(), 'renderException', [$exception]);
        }

        return $response;
    }

    /**
     * @dataProvider rejectedMutationProvider
     */
    public function testRejectedMutationRunsFiltersBeforePolicyChecks($method, array $options, $status)
    {
        $this->prepareRequest($method);
        $this->controller->actionOptions = $options;

        $response = $this->runAndRender();

        $this->assertSame(['module', 'controller audit'], $this->events);
        $this->assertFalse(\Yii::$app->user->isGuest);
        $this->assertSame(Response::FORMAT_JSON, $this->formatDuringAudit);
        $this->assertSame('https://client.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame($status, $response->statusCode);
        $this->assertSame(Response::FORMAT_JSON, $response->format);
        $this->assertSame($status, json_decode($response->content, true)['status']);
        $this->assertSame($status === 405 ? 'POST' : null, $response->headers->get('Allow'));
        $this->assertSame(0, $this->resolverCalls);
    }

    public function rejectedMutationProvider()
    {
        return [
            'transport policy' => ['GET', ['requirePostForMutations' => true], 405],
            'access-check policy' => ['POST', ['requireAccessCheckForMutations' => true], 403],
        ];
    }

    public function testPolicyFailureUsesJsonWithoutContentNegotiator()
    {
        $this->prepareRequest('GET');
        $this->controller->detachBehavior('negotiator');
        $this->controller->actionOptions = ['requirePostForMutations' => true];

        $response = $this->runAndRender();

        $this->assertSame(Response::FORMAT_HTML, $this->formatDuringAudit);
        $this->assertSame(Response::FORMAT_JSON, $response->format);
        $this->assertSame(405, $response->statusCode);
        $this->assertSame('POST', $response->headers->get('Allow'));
        $this->assertSame(405, json_decode($response->content, true)['status']);
        $this->assertSame(0, $this->resolverCalls);
    }

    public function testAuthenticationCanRejectBeforeMutationPolicy()
    {
        $this->prepareRequest('GET', false);
        $this->controller->actionOptions = ['requirePostForMutations' => true];

        $response = $this->runAndRender();

        $this->assertSame(['module'], $this->events);
        $this->assertSame(401, $response->statusCode);
        $this->assertSame(Response::FORMAT_JSON, $response->format);
        $this->assertSame('https://client.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($response->headers->get('Allow'));
        $this->assertSame(0, $this->resolverCalls);
    }

    public function testCorsPreflightCanFinishBeforeMutationPolicies()
    {
        $this->prepareRequest('OPTIONS', false);
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';
        \Yii::$app->request->headers->set('Access-Control-Request-Method', 'POST');
        $this->controller->actionOptions = [
            'requirePostForMutations' => true,
            'requireAccessCheckForMutations' => true,
        ];

        $response = $this->runAndRender();

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('https://client.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($response->headers->get('Allow'));
        $this->assertSame(['module'], $this->events);
        $this->assertSame(0, $this->resolverCalls);
    }

    public function testBeforeActionCanConfigureRequiredAccessCheckForValidMutation()
    {
        $this->prepareRequest('POST');
        $this->controller->actionOptions = [
            'requirePostForMutations' => true,
            'requireAccessCheckForMutations' => true,
        ];
        $authorized = [];
        $this->controller->on(Controller::EVENT_BEFORE_ACTION, function ($event) use (&$authorized) {
            $event->action->checkAccess = function ($actionName) use (&$authorized) {
                $authorized[] = $actionName;
            };
        });

        $response = $this->runAndRender();

        $this->assertSame(['module', 'controller audit'], $this->events);
        $this->assertSame(['touch'], $authorized);
        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['data' => ['touch' => true]], json_decode($response->content, true));
        $this->assertSame(1, $this->resolverCalls);
    }
}
