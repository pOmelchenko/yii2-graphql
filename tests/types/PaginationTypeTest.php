<?php

namespace yiiunit\extensions\graphql\types;

use GraphQL\Type\Definition\Type;
use yii\graphql\types\PaginationType;
use yiiunit\extensions\graphql\TestCase;

class PaginationTypeTest extends TestCase
{
    public function testFieldsExposeRelayStyleArguments()
    {
        $type = new PaginationType();
        $fields = $type->getFields();

        $this->assertSame('Pagination', $type->name);
        $this->assertCount(4, $fields);

        $this->assertSame(Type::int(), $fields['first']->getType());
        $this->assertSame(Type::string(), $fields['after']->getType());
        $this->assertSame(Type::int(), $fields['last']->getType());
        $this->assertSame(Type::string(), $fields['before']->getType());
    }
}
