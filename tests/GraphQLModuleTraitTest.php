<?php

namespace yiiunit\extensions\graphql;

use yii\graphql\GraphQL;
use yii\graphql\GraphQLModuleInterface;
use yiiunit\extensions\graphql\objects\types\ExampleType;

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

    public function testModuleImplementsInterface()
    {
        $this->mockWebApplication();

        $module = \Yii::$app->getModule('graphql');
        $this->assertInstanceOf(GraphQLModuleInterface::class, $module);
    }

    public function testLegacyModuleTriggersDeprecation()
    {
        // TODO: remove legacy support (and this test) once GraphQLModuleInterface is mandatory.
        $this->mockWebApplication();

        $original = \Yii::$app->getModule('graphql');
        $legacy = new LegacyModule('graphql', \Yii::$app);
        $legacy->schema = $original->schema;

        \Yii::$app->setModule('graphql', $legacy);

        $messages = [];
        set_error_handler(function ($errno, $errstr) use (&$messages) {
            if ($errno === E_USER_DEPRECATED) {
                $messages[] = $errstr;
                return true;
            }
            return false;
        });

        try {
            $type = GraphQL::type(ExampleType::class);
            $this->assertNotNull($type);
        } finally {
            restore_error_handler();
            \Yii::$app->setModule('graphql', $original);
        }

        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('Using GraphQLModuleTrait without implementing GraphQLModuleInterface is deprecated', $messages[0]);
    }
}
