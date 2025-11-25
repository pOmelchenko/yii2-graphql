<?php

namespace yiiunit\extensions\graphql;

use GraphQL\Type\Definition\Config;
use yii\graphql\GraphQLModuleTrait;

class Module extends \yii\base\Module
{
    use GraphQLModuleTrait;

    public function init()
    {
    }
}
