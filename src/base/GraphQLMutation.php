<?php

namespace yii\graphql\base;

use yii\graphql\traits\ShouldValidate;

class GraphQLMutation extends GraphQLField
{
    use ShouldValidate;
}
