<?php

namespace yii\graphql;

use Yii;
use yii\base\Action;
use yii\web\Response;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use GraphQL\Error\Error as GQLError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Upload\UploadMiddleware;
use Laminas\Diactoros\ServerRequestFactory;
use yii\graphql\exceptions\SchemaNotFound;

/**
 * GraphQLAction implements the access method of the graph server and returns the query results in the JSON format
 * configure in Controller actions method
 * ```php
 * function actions()
 * {
 *     return [
 *          'index'=>['class'=>'yii\graphql\GraphQLAction']
 *     ]
 * }
 * ```
 * @package yii\graphql
 */
class GraphQLAction extends Action
{
    public const INTROSPECTIONQUERY = '__schema';
    /**
     * @var GraphQL
     */
    private $graphQL;
    private $schemaArray;
    private $selectedOperationSchema;
    private $selectedOperationHasIntrospection = false;
    private $query;
    private $variables;
    private $operationName;
    /**
     * @var array child graphql actions
     */
    private $authActions = [];
    /**
     * @var bool whether the actions participating in access checks were initialized
     */
    private $authActionsInitialized = false;
    /**
     * @var callable|null a PHP callable that will be called when running an action to determine
     * if the current user has the permission to execute the action. If not set, the access
     * check will not be performed. The signature of the callable should be as follows,
     *
     * ```php
     * function ($actionName) {
     *
     *     // If null, it means no specific model (e.g. IndexAction)
     * }
     * ```
     */
    public $checkAccess;
    /**
     * @var bool|null whether mutation requests must use POST as both the original HTTP transport method and Yii's
     * effective method. Method overrides do not satisfy this requirement in either direction. Null preserves legacy
     * behavior and emits a deprecation warning when either method is non-POST. The default will become true in the
     * next major.
     */
    public $requirePostForMutations = null;
    /**
     * @var bool whether mutations require a configured access check. Disabled by default for backward compatibility.
     */
    public $requireAccessCheckForMutations = false;
    /**
     * @var bool whether use Schema validation , and it is recommended only in the development environment
     */
    public $enableSchemaAssertValid = YII_ENV_DEV;

    public function init()
    {
        parent::init();

        $request = Yii::$app->getRequest();
        if ($request->isGet) {
            $this->query = $request->get('query');
            $this->variables = $request->get('variables');
            $this->operationName = $request->get('operationName');
        } else {
            $body = $request->getBodyParams();
            if (empty($body)) {
                // Use raw body as query (supports simple queries when no structured body is provided)
                $this->query = $request->getRawBody();
            } else {
                if (!empty($body['operations'])) {
                    $serverRequest = ServerRequestFactory::fromGlobals();
                    $uploadMiddleware = new UploadMiddleware();
                    $serverRequest = $uploadMiddleware->processRequest($serverRequest);
                    $parsedBody = $serverRequest->getParsedBody();

                    $this->query = $parsedBody['query'] ?? $parsedBody;
                    $this->variables = $parsedBody['variables']  ?? [];
                    $this->operationName = $parsedBody['operationName']  ?? null;
                } else {
                    $this->query = $body['query'] ?? $body;
                    $this->variables = $body['variables'] ?? [];
                    $this->operationName = $body['operationName'] ?? null;
                }
            }
        }
        if (empty($this->query)) {
            throw new InvalidParamException('invalid query,query document not found');
        }
        if (is_string($this->variables)) {
            $this->variables = json_decode($this->variables, true);
        }

        $module = $this->controller->module;
        if ($module instanceof GraphQLModuleInterface) {
            $this->graphQL = $module->getGraphQL();
        } elseif (method_exists($module, 'getGraphQL')) {
            // TODO: drop legacy trait fallback and throw InvalidConfigException in the next major release.
            trigger_error('Using GraphQLModuleTrait without implementing GraphQLModuleInterface is deprecated and will throw an exception in a future release.', E_USER_DEPRECATED);
            $this->graphQL = $module->getGraphQL();
        } else {
            throw new InvalidConfigException('GraphQL module must implement GraphQLModuleInterface.');
        }

        $this->schemaArray = $this->graphQL->parseRequestQuery($this->query, $this->operationName);
        $this->selectedOperationSchema = $this->graphQL->getSelectedOperationSchema();
        $this->selectedOperationHasIntrospection = $this->graphQL->hasSelectedOperationIntrospection();

        $hasMutation = $this->graphQL->getOperationType($this->operationName) === 'mutation';
        $isTransportPost = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $isEffectivePost = $request->isPost;

        if ($hasMutation && (!$isTransportPost || !$isEffectivePost)) {
            if ($this->requirePostForMutations === null) {
                // TODO: Remove this legacy branch and default the option to true in the next major release.
                trigger_error(
                    'Allowing GraphQL mutations over non-POST requests without explicitly configuring '
                    . 'GraphQLAction::$requirePostForMutations is deprecated. Set it to true to require POST '
                    . 'or false to keep the legacy behavior. The default will change to true in the next major release.',
                    E_USER_DEPRECATED
                );
            } elseif ($this->requirePostForMutations) {
                throw new MethodNotAllowedHttpException('GraphQL mutations must use POST requests.');
            }
        }

        if ($hasMutation && $this->requireAccessCheckForMutations && !$this->checkAccess) {
            throw new ForbiddenHttpException('Mutation execution requires access check configuration.');
        }
    }

    /**
     * Return all GraphQL actions participating in the selected operation.
     * Introspection is represented by the special __schema action.
     * @return array
     */
    public function getGraphQLActions()
    {
        if ($this->selectedOperationSchema === true) {
            $this->authActionsInitialized = true;
            return [self::INTROSPECTIONQUERY => 'true'];
        }
        $ret = array_merge($this->selectedOperationSchema[0], $this->selectedOperationSchema[1]);
        if ($this->selectedOperationHasIntrospection) {
            $ret[self::INTROSPECTIONQUERY] = 'true';
        }
        if (!$this->authActionsInitialized) {
            //init
            $this->authActions = $ret;
            $this->authActionsInitialized = true;
        }
        return $ret;
    }

    /**
     * remove action that no need check access
     * @param $key
     */
    public function removeGraphQlAction($key)
    {
        if (!$this->authActionsInitialized) {
            $this->getGraphQLActions();
        }
        unset($this->authActions[$key]);
    }

    /**
     * @return array
     */
    public function run()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if ($this->checkAccess) {
            if (!$this->authActionsInitialized) {
                $this->getGraphQLActions();
            }
            foreach ($this->authActions as $childAction => $class) {
                $fn = $this->checkAccess;
                $fn($childAction);
            }
        }
        try {
            $schema = $this->graphQL->buildSchema($this->schemaArray === true ? null : $this->schemaArray);
            //TODO the graphql-php's valid too strict,the lazy load has can't pass when execute mutation(must has query node)
//        if ($this->enableSchemaAssertValid) {
//            $this->graphQL->assertValid($schema);
//        }
            $val = $this->graphQL->execute($schema, null, Yii::$app, $this->variables, $this->operationName);
        } catch (SchemaNotFound $exception) {
            $error = new GQLError($exception->getMessage(), null, null, [], null, $exception);
            $val = new ExecutionResult(null, [$error]);
        }
        $result = $this->graphQL->getResult($val);
        return $result;
    }
}
