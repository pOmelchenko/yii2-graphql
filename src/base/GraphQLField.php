<?php

namespace yii\graphql\base;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use yii\base\Model;
use yii\graphql\GraphQL;
use yii\web\Application;

/**
 * The GraphQLField class corresponds to each node in the type system
 * described in a GraphQL schema document. For example:
 *
 * ```json
 * type Person {
 *   name: String
 *   age: Int
 *   picture: Url
 *   relationship: Person
 * }
 * ```
 * Each node includes properties such as name, type, args, and description.
 *
 * @package yii\graphql\base
 */
class GraphQLField extends GraphQLModel
{
    public function type()
    {
        return null;
    }

    public function args()
    {
        return [];
    }

    protected function getResolver()
    {
        if (!method_exists($this, 'resolve')) {
            return null;
        }

        $resolver = array($this, 'resolve');
        return function () use ($resolver) {
            $args = func_get_args();
            return $resolver(...$args);
        };
    }

    /**
     * Get the graphql office's description format,that will be used for create GraphQL Object Type.
     *
     * @param $name
     * @param $except
     * @return array
     */
    public function getAttributes($name = null, $except = null)
    {
        $attributes = $this->attributes;
        $args = $this->args();

        $attributes = array_merge([
            'args' => $args
        ], $attributes);

        $type = $this->type();
        if (isset($type)) {
            if(!is_object($type)){
                $type = GraphQL::type($type);
            }
            $attributes['type'] = $type;
        }

        $resolver = $this->getResolver();
        if (isset($resolver)) {
            $attributes['resolve'] = $resolver;
        }

        return $attributes;
    }
}
