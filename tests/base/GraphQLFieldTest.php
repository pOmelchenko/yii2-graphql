<?php

namespace yiiunit\extensions\graphql\base;

use GraphQL\Type\Definition\Type;
use yii\graphql\base\GraphQLField;
use yiiunit\extensions\graphql\TestCase;
use yiiunit\extensions\graphql\objects\types\ExampleType;

class GraphQLFieldTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    public function testGetAttributesResolvesTypeAndResolver()
    {
        $field = new DummyField();
        $field->typeName = ExampleType::class;
        $field->description = 'test field';

        $attributes = $field->getAttributes();

        $this->assertSame('test field', $attributes['description']);
        $this->assertArrayHasKey('args', $attributes);
        $this->assertArrayHasKey('type', $attributes);
        $this->assertArrayHasKey('resolve', $attributes);

        $this->assertSame(\yii\graphql\GraphQL::type(ExampleType::class)->name, $attributes['type']->name);

        $resolver = $attributes['resolve'];
        $this->assertSame('42', $resolver(null, ['id' => '42']));
    }

    public function testMagicAttributeAccessors()
    {
        $field = new DummyField();
        $field->foo = 'bar';

        $this->assertTrue(isset($field->foo));
        $this->assertSame('bar', $field->foo);

        unset($field->foo);
        $this->assertFalse(isset($field->foo));
        $this->assertNull($field->foo);
    }
}

class DummyField extends GraphQLField
{
    public $typeName;

    public function type()
    {
        return $this->typeName;
    }

    public function args()
    {
        return [
            'id' => [
                'type' => Type::id(),
            ],
        ];
    }

    public function resolve($root, $args)
    {
        return $args['id'];
    }
}
