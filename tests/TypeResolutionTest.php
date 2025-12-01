<?php

namespace yiiunit\extensions\graphql;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use yii\graphql\TypeResolution;
use yii\graphql\GraphQL;
use yii\graphql\exceptions\TypeNotFound;
use yiiunit\extensions\graphql\objects\types\ExampleType;

class TypeResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    public function testParseTypeByAlias()
    {
        $resolution = new TypeResolution();
        $resolution->setAlias(['example' => ExampleType::class]);

        $type = $resolution->parseType('example', true);

        $this->assertSame(GraphQL::type(ExampleType::class)->name, $type->name);
        $this->assertSame($type, $resolution->parseType(ExampleType::class));
    }

    public function testParseTypeThrowsWhenMissing()
    {
        $resolution = new TypeResolution();
        $resolution->setAlias(['missing' => '\NotExisting\GraphQL\Type']);

        $this->expectException(TypeNotFound::class);
        $resolution->parseType('missing', true);
    }

    public function testObjectTypeBuildsFromClass()
    {
        $resolution = new TypeResolution();
        $type = $resolution->objectType(DummyGraphQLType::class, ['name' => 'Dummy']);

        $this->assertInstanceOf(ObjectType::class, $type);
        $this->assertSame('Dummy', $type->name);
    }

    public function testObjectTypeBuildsFromFieldArray()
    {
        $resolution = new TypeResolution();
        $type = $resolution->objectType([
            'number' => DummyGraphQLField::class,
        ], ['name' => 'Query']);

        $this->assertInstanceOf(ObjectType::class, $type);
        $fields = $type->getFields();
        $this->assertArrayHasKey('number', $fields);
    }

    public function testResolvePossibleTypesForInterface()
    {
        $resolution = new TypeResolution();
        $interface = new InterfaceType([
            'name' => 'Character',
            'fields' => [
                'id' => Type::id(),
            ],
            'resolveType' => function () {
            },
        ]);

        $objectType = new ObjectType([
            'name' => 'Hero',
            'fields' => [
                'id' => Type::id(),
            ],
            'interfaces' => [$interface],
        ]);

        $resolution->initTypes([$objectType], false);

        $possible = $resolution->resolvePossibleTypes($interface);
        $this->assertCount(1, $possible);
        $this->assertSame('Hero', $possible[0]->name);
    }

    public function testResolvePossibleTypesForUnion()
    {
        $resolution = new TypeResolution();
        $union = new UnionType([
            'name' => 'SearchResult',
            'types' => [
                new ObjectType([
                    'name' => 'Post',
                    'fields' => [
                        'id' => Type::id(),
                    ],
                ]),
            ],
        ]);

        $resolution->initTypes([$union], false);
        $possible = $resolution->resolvePossibleTypes($union);

        $this->assertCount(1, $possible);
        $this->assertSame('Post', $possible[0]->name);
    }

    public function testGetDescriptorReturnsTypeMaps()
    {
        $resolution = new TypeResolution();
        $heroType = null;
        $interface = new InterfaceType([
            'name' => 'Character',
            'fields' => [
                'id' => Type::id(),
            ],
            'resolveType' => function () use (&$heroType) {
                return $heroType ?? null;
            },
        ]);

        $heroType = new ObjectType([
            'name' => 'Hero',
            'fields' => [
                'id' => Type::id(),
            ],
            'interfaces' => [$interface],
        ]);

        $resolution->initTypes([$heroType], true);
        $descriptor = $resolution->getDescriptor();

        $this->assertArrayHasKey('Hero', $descriptor['typeMap']);
        $this->assertArrayHasKey('Character', $descriptor['possibleTypeMap']);
    }

    public function testBuildTypeThrowsForUnsupportedClass()
    {
        $resolution = new TypeResolution();

        $this->expectException(\yii\base\NotSupportedException::class);
        $this->invoke($resolution, 'buildType', [new UnsupportedType()]);
    }

    public function testResolveTypeUsesCache()
    {
        $resolution = new TypeResolution();
        $resolution->setAlias(['example' => ExampleType::class]);

        $first = $resolution->parseType('example', true);
        $second = $resolution->resolveType($first->name);

        $this->assertSame($first, $second);
    }

    public function testInitTypesIncludesIntrospectionSchema()
    {
        $resolution = new TypeResolution();
        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'dummy' => ['type' => Type::string()],
            ],
        ]);

        $resolution->initTypes([$query], true);
        $typeMap = $resolution->getTypeMap();

        $this->assertArrayHasKey('__Schema', $typeMap);
    }
}

class DummyGraphQLType extends \yii\graphql\base\GraphQLType
{
    public function fields()
    {
        return [
            'id' => Type::id(),
        ];
    }
}

class DummyGraphQLField extends \yii\graphql\base\GraphQLField
{
    public function type()
    {
        return Type::int();
    }

    public function resolve($root, $args)
    {
        return 1;
    }
}

class UnsupportedType
{
    public function __toString()
    {
        return 'UnsupportedType';
    }
}
