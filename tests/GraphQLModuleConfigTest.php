<?php

namespace yiiunit\extensions\graphql;

use GraphQL\Type\Definition\Type;
use yii\graphql\GraphQL;
use yiiunit\extensions\graphql\objects\types\ExampleType;

class GraphQLModuleConfigTest extends TestCase
{
    public function testModuleInlineFieldExecutesResolver()
    {
        $this->mockWebApplication();
        $module = \Yii::$app->getModule('graphql');
        $schema = $module->schema;
        $schema['query']['moduleInline'] = [
            'type' => Type::nonNull(Type::int()),
            'resolve' => function () {
                return 77;
            },
        ];

        $newModule = $this->recreateGraphQLModule($module, $schema);

        $result = $newModule->getGraphQL()->query('query { moduleInline }', null, \Yii::$app);

        $this->assertSame(77, $result['data']['moduleInline']);
    }

    public function testModuleInlineFieldMayReferenceGraphQLType()
    {
        $this->mockWebApplication();
        $module = \Yii::$app->getModule('graphql');
        $schema = $module->schema;
        $schema['query']['moduleInlineType'] = [
            'type' => GraphQL::type(ExampleType::class),
            'resolve' => function () {
                return \yiiunit\extensions\graphql\data\DataSource::findUser(1);
            },
        ];

        $newModule = $this->recreateGraphQLModule($module, $schema);

        $result = $newModule->getGraphQL()->query('query { moduleInlineType { id firstName } }', null, \Yii::$app);

        $this->assertSame('1test', $result['data']['moduleInlineType']['id']);
        $this->assertSame('John', $result['data']['moduleInlineType']['firstName']);
    }

    public function testModuleInlineTypeResolvedBeforeApplicationIsBootstrapped()
    {
        // Simulate config being evaluated before Yii::$app exists.
        \Yii::$app = null;
        \Yii::$container = new \yii\di\Container();
        GraphQL::resetStandaloneTypeResolution();
        $preResolvedType = GraphQL::type(ExampleType::class);

        $this->mockWebApplication();
        $module = \Yii::$app->getModule('graphql');
        $schema = $module->schema;
        $schema['query']['moduleInlineTypeEarly'] = [
            'type' => $preResolvedType,
            'resolve' => function () {
                return \yiiunit\extensions\graphql\data\DataSource::findUser(1);
            },
        ];

        $newModule = $this->recreateGraphQLModule($module, $schema);

        $result = $newModule->getGraphQL()->query('query { moduleInlineTypeEarly { id firstName } }', null, \Yii::$app);

        $this->assertSame('1test', $result['data']['moduleInlineTypeEarly']['id']);
        $this->assertSame('John', $result['data']['moduleInlineTypeEarly']['firstName']);
    }

    private function recreateGraphQLModule($originalModule, array $schema)
    {
        $class = get_class($originalModule);
        $newModule = new $class('graphql', \Yii::$app, [
            'schema' => $schema,
        ]);
        \Yii::$app->setModule('graphql', $newModule);

        return $newModule;
    }
}
