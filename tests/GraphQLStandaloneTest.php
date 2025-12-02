<?php

namespace yiiunit\extensions\graphql;

use PHPUnit\Framework\TestCase;
use yii\di\Container;
use yii\graphql\GraphQL;
use yiiunit\extensions\graphql\objects\types\ExampleType;

class GraphQLStandaloneTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        GraphQL::resetStandaloneTypeResolution();
        \Yii::$app = null;
        \Yii::$container = new Container();
    }

    public function testTypeResolvesWithoutApplication()
    {
        \Yii::$app = null;
        \Yii::$container = new Container();
        GraphQL::resetStandaloneTypeResolution();

        $type = GraphQL::type(ExampleType::class);

        $this->assertSame('example', $type->name);
    }
}
