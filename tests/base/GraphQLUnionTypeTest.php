<?php

namespace yiiunit\extensions\graphql\base;

use yii\base\InvalidConfigException;
use yii\graphql\base\GraphQLUnionType;
use yii\graphql\GraphQL;
use yiiunit\extensions\graphql\TestCase;
use yiiunit\extensions\graphql\data\User;
use yiiunit\extensions\graphql\objects\types\StoryType;
use yiiunit\extensions\graphql\objects\types\UserType;

class GraphQLUnionTypeTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    public function testGetAttributesRequiresResolveType()
    {
        $type = new class extends GraphQLUnionType {
            public function types()
            {
                return [UserType::class];
            }
        };

        $this->expectException(InvalidConfigException::class);
        $type->getAttributes();
    }

    public function testGetAttributesResolvesTypes()
    {
        $type = new class extends GraphQLUnionType {
            public function types()
            {
                return [
                    StoryType::class,
                    UserType::class,
                ];
            }

            protected function resolveType($value)
            {
                if ($value instanceof User) {
                    return GraphQL::type(UserType::class);
                }

                return GraphQL::type(StoryType::class);
            }
        };

        $attributes = $type->getAttributes();

        $this->assertCount(2, $attributes['types']);
        $this->assertSame(GraphQL::type(StoryType::class)->name, $attributes['types'][0]->name);

        $resolver = $attributes['resolveType'];
        $resolved = $resolver(new User(['id' => 1]));
        $this->assertSame(GraphQL::type(UserType::class)->name, $resolved->name);
    }

    public function testGetAttributesAcceptsTypeInstances()
    {
        $story = GraphQL::type(StoryType::class);
        $type = new class(['instances' => [$story]]) extends GraphQLUnionType {
            public array $instances;
            protected $attributes = [
                'name' => 'InstanceUnion',
            ];

            public function types()
            {
                return $this->instances;
            }

            protected function resolveType($value)
            {
                return $this->instances[0];
            }
        };

        $attributes = $type->getAttributes();
        $this->assertSame('InstanceUnion', $attributes['name']);
        $this->assertSame($story, $attributes['types'][0]);
    }

    public function testToTypeBuildsUnionType()
    {
        $type = new class extends GraphQLUnionType {
            protected $attributes = [
                'name' => 'ResultUnion',
            ];

            public function types()
            {
                return [
                    StoryType::class,
                ];
            }

            protected function resolveType($value)
            {
                return GraphQL::type(StoryType::class);
            }
        };

        $unionType = $type->toType();
        $this->assertInstanceOf(\GraphQL\Type\Definition\UnionType::class, $unionType);
        $this->assertSame('ResultUnion', $unionType->name);
    }
}
