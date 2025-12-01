<?php

namespace yiiunit\extensions\graphql\traits;

use GraphQL\Type\Definition\Type;
use yii\base\InvalidParamException;
use yii\graphql\base\GraphQLMutation;
use yiiunit\extensions\graphql\TestCase;

class ShouldValidateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    public function testResolverThrowsWhenValidationFails()
    {
        $field = new ValidatingMutation();

        $this->expectException(InvalidParamException::class);
        $field->executeResolver(['email' => 'not-email']);
    }

    public function testResolverReturnsValueWhenValidationPasses()
    {
        $field = new ValidatingMutation();
        $result = $field->executeResolver(['email' => 'user@example.com']);

        $this->assertSame('user@example.com', $result);
    }

    public function testResolverFallsBackWhenNoRules()
    {
        $field = new class extends \yii\graphql\base\GraphQLMutation {
            public function type()
            {
                return Type::string();
            }

            public function args()
            {
                return [];
            }

            public function rules()
            {
                return [];
            }
        };

        $resolver = $this->invoke($field, 'getResolver');
        $this->assertNull($resolver);
    }
}

class ValidatingMutation extends GraphQLMutation
{
    public function type()
    {
        return Type::string();
    }

    public function args()
    {
        return [
            'email' => [
                'type' => Type::nonNull(Type::string()),
            ],
        ];
    }

    public function resolve($root, $args)
    {
        return $args['email'];
    }

    public function rules()
    {
        return [
            ['email', 'email'],
        ];
    }

    public function executeResolver(array $args)
    {
        $resolver = $this->getResolver();
        return $resolver(null, $args);
    }
}
