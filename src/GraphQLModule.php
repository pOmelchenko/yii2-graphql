<?php

namespace yii\graphql;

use yii\base\Module;

abstract class GraphQLModule extends Module implements GraphQLModuleInterface
{
    use GraphQLModuleTrait;
}
