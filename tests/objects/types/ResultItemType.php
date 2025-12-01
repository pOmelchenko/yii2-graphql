<?php

namespace yiiunit\extensions\graphql\objects\types;

use yii\graphql\base\GraphQLUnionType;
use yii\graphql\GraphQL;
use yiiunit\extensions\graphql\data\Story;
use yiiunit\extensions\graphql\data\User;

class ResultItemType extends GraphQLUnionType
{
    protected $attributes = [
        'name' => 'ResultItem',
        'description' => 'result type'
    ];

    public function types()
    {
        return [
            StoryType::class,
            UserType::class
        ];
    }

    protected function resolveType($value)
    {
        if ($value instanceof Story) {
            return GraphQL::type(StoryType::class);
        } elseif ($value instanceof User) {
            return GraphQL::type(UserType::class);
        }
    }
}
