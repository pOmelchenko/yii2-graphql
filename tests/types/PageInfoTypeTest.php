<?php

namespace yiiunit\extensions\graphql\types;

use GraphQL\Type\Definition\Type;
use yii\graphql\types\PageInfoType;
use yiiunit\extensions\graphql\TestCase;

class PageInfoTypeTest extends TestCase
{
    public function testFieldsAreExposedWithExpectedTypes()
    {
        $type = new PageInfoType();
        $fields = $type->getFields();

        $this->assertSame('PageInfo', $type->name);
        $this->assertCount(4, $fields);

        $this->assertSame(Type::string(), $fields['startCursor']->getType());
        $this->assertSame(Type::string(), $fields['endCursor']->getType());
        $this->assertSame(Type::boolean(), $fields['hasNextPage']->getType());
        $this->assertSame(Type::boolean(), $fields['hasPreviousPage']->getType());
    }
}
