<?php

namespace yiiunit\extensions\graphql\types;

use GraphQL\Error\Error;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use yii\graphql\types\EmailType;
use yiiunit\extensions\graphql\TestCase;

class EmailTypeTest extends TestCase
{
    public function testParseValueRejectsInvalidEmail()
    {
        $type = new EmailType();

        $this->expectException(\UnexpectedValueException::class);
        $type->parseValue('not-an-email');
    }

    public function testParseLiteralRejectsNonString()
    {
        $type = new EmailType();

        $this->expectException(Error::class);
        $type->parseLiteral(new IntValueNode(['value' => 5]));
    }

    public function testParseLiteralRejectsInvalidEmail()
    {
        $type = new EmailType();
        $node = new StringValueNode(['value' => 'invalid']);

        $this->expectException(Error::class);
        $type->parseLiteral($node);
    }

    public function testSerializePassesThroughValidValue()
    {
        $type = new EmailType();
        $this->assertSame('user@example.com', $type->serialize('user@example.com'));
    }

    public function testParseValueAcceptsValidEmail()
    {
        $type = new EmailType();
        $this->assertSame('user@example.com', $type->parseValue('user@example.com'));
    }

    public function testParseLiteralReturnsValidEmail()
    {
        $type = new EmailType();
        $node = new StringValueNode(['value' => 'user@example.com']);

        $this->assertSame('user@example.com', $type->parseLiteral($node));
    }
}
