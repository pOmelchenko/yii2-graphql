<?php

namespace yii\graphql;

use Yii;

/**
 * graphQl trait，help the yii Module initial
 * @author QingShan Li
 */
trait GraphQLModuleTrait
{
    /**
     * The schemas for query and/or mutation. It expects an array to provide
     * both the 'query' fields and the 'mutation' fields. You can also
     * provide directly an object GraphQL\Schema
     *
     * Example:
     *
     * 'schema' => [
     *      'query' => [
     *          'user' => 'App\GraphQL\Query\UsersQuery'
     *      ],
     *      'mutation' => [
     *
     *      ],
     *      'types'=>[
     *          'user'=>'app\modules\graph\type\UserType'
     *      ],
     * ]
     *
     * @var array
     */
    public $schema = [];

    /**
     * @var GraphQL|null the Graph handle
     */
    private ?GraphQL $graphQL = null;

    /**
     * @var callable|null if don't set error formatter,it will use php-graphql default
     * @see \GraphQL\Executor\ExecutionResult
     */
    public $errorFormatter;

    /**
     * get graphql handler
     */
    public function getGraphQL(): GraphQL
    {
        if ($this->graphQL == null) {
            $this->graphQL = new GraphQL();
            $this->graphQL->schema($this->schema);
            if ($this->errorFormatter) {
                $this->graphQL->setErrorFormatter($this->errorFormatter);
            } else {
                $this->graphQL->setErrorFormatter(['yii\graphql\ErrorFormatter', 'formatError']);
            }
        }
        return $this->graphQL;
    }
}
