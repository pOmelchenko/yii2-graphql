<?php

namespace yii\graphql\filters\auth;

use Yii;
use yii\base\InvalidConfigException;
use yii\filters\auth\AuthInterface;
use yii\graphql\GraphQLAction;

/**
 * CompositeAuth is used to handle authorization checks
 * for GraphQL’s single-entry request model.
 * In a GraphQL query, multiple sub-requests may be executed at once;
 * compared to MVC, this can be considered as executing multiple actions
 * within a single request.
 * @package yii\graphql\filters\auth
 */
class CompositeAuth extends \yii\filters\auth\AuthMethod
{
    /**
     * @var array the supported authentication methods. This property should take a list of supported
     * authentication methods, each represented by an authentication class or configuration.
     *
     * If this property is empty, no authentication will be performed.
     *
     * Note that an auth method class must implement the [[\yii\filters\auth\AuthInterface]] interface.
     */
    public array $authMethods = [];


    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        return empty($this->authMethods) ? true : parent::beforeAction($action);
    }

    /**
     * @inheritdoc
     */
    public function authenticate($user, $request, $response)
    {
        foreach ($this->authMethods as $i => $auth) {
            if (!$auth instanceof AuthInterface) {
                $this->authMethods[$i] = $auth = Yii::createObject($auth);
                if (!$auth instanceof AuthInterface) {
                    throw new InvalidConfigException(get_class($auth) . ' must implement yii\filters\auth\AuthInterface');
                }
            }

            $identity = $auth->authenticate($user, $request, $response);
            if ($identity !== null) {
                return $identity;
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function challenge($response)
    {
        foreach ($this->authMethods as $method) {
            /** @var $method AuthInterface */
            $method->challenge($response);
        }
    }

    protected function isActive($action)
    {
        if ($action instanceof GraphQLAction) {
            $maps = $action->getGraphQLActions();

            if (empty($this->only)) {
                $onlyMatch = true;
            } else {
                $onlyMatch = true;
                foreach ($maps as $key => $value) {
                    foreach ($this->only as $pattern) {
                        if (fnmatch($pattern, $key)) {
                            continue 2;
                        }
                    }
                    $onlyMatch = false;
                    break;
                }
            }

            $exceptMatch = true;
            foreach ($maps as $key => $value) {
                foreach ($this->except as $pattern) {
                    if (fnmatch($pattern, $key)) {
                        $action->removeGraphQlAction($key);
                        continue 2;
                    }
                }
                $exceptMatch = false;
                break;
            }
            return !$exceptMatch && $onlyMatch;
        } else {
            return parent::isActive($action);
        }
    }
}
