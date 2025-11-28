<?php

namespace yiiunit\extensions\graphql\objects\types;

use yii\graphql\types\SimpleExpressionType;
use yiiunit\extensions\graphql\TestCase;

class SimpleExpressionTypeTest extends TestCase
{
    public function testFieldsDefinitionContainsExpectedTypes()
    {
        $type = new SimpleExpressionType();
        $fields = $type->fields();

        $this->assertArrayHasKey('gt', $fields);
        $this->assertSame('Int', $fields['gt']['type']->name);
        $this->assertInstanceOf(\GraphQL\Type\Definition\ListOfType::class, $fields['in']['type']);
        $this->assertSame('Int', $fields['in']['type']->getWrappedType()->name);
    }

    public function testToQueryCondition()
    {
        $express = [
            'id' => 1,
            'name' => [
                'eq' => 'abc'
            ],
            'count' => [
                'lt' => 1
            ],
            'age' => [
                'gt' => 20
            ],
        ];
        $expect = [
            'id' => 1,
            ['=', 'name', 'abc'],
            ['<', 'count', 1],
            ['>', 'age', 20],
        ];

        $val = SimpleExpressionType::toQueryCondition($express);
        $this->assertEquals($expect, $val);
    }

    public function testToQueryConditionHandlesExtendedOperators()
    {
        $express = [
            'age' => ['gte' => 18],
            'score' => ['lte' => 10],
            'status' => ['ne' => 'archived'],
        ];
        $val = SimpleExpressionType::toQueryCondition($express);

        $this->assertContains(['>=', 'age', 18], $val);
        $this->assertContains(['<=', 'score', 10], $val);
        $this->assertContains(['ne', 'status', 'archived'], $val);
    }

    public function testToQueryConditionHandlesInOperator()
    {
        $express = [
            'id' => [
                'in' => [1, 2, 3],
            ],
        ];

        $val = SimpleExpressionType::toQueryCondition($express);

        $this->assertContains(['in', 'id', [1, 2, 3]], $val);
    }

    public function testToQueryConditionKeepsScalarsAndUnknownOperators()
    {
        $express = [
            'status' => 'active',
            'custom' => ['neq' => 'archived'],
            'skip' => (object)['value' => 1],
        ];

        $val = SimpleExpressionType::toQueryCondition($express);

        $this->assertSame('active', $val['status']);
        $this->assertContains(['neq', 'custom', 'archived'], $val);
        $this->assertArrayNotHasKey('skip', $val);
    }
}
