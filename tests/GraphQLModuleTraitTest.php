<?php

namespace yiiunit\extensions\graphql;

class GraphQLModuleTraitTest extends TestCase
{
    public function testCustomErrorFormatterIsApplied()
    {
        $callable = function () {
        };

        $this->mockWebApplication([
            'modules' => [
                'graphql' => [
                    'class' => Module::class,
                    'errorFormatter' => $callable,
                ],
            ],
        ]);

        $module = \Yii::$app->getModule('graphql');
        $graphQL = $module->getGraphQL();

        $this->assertSame($callable, $graphQL->errorFormatter);
    }

    public function testDefaultErrorFormatterIsUsedWhenNotProvided()
    {
        $this->mockWebApplication();

        $module = \Yii::$app->getModule('graphql');
        $graphQL = $module->getGraphQL();

        $this->assertEquals(['yii\graphql\ErrorFormatter', 'formatError'], $graphQL->errorFormatter);
    }
}
