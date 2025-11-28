<?php

namespace yiiunit\extensions\graphql\types;

use GraphQL\Error\Error;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use yii\graphql\types\UrlType;
use yiiunit\extensions\graphql\TestCase;

class UrlTypeTest extends TestCase
{
    public function testParseValueRejectsInvalidUrl()
    {
        $type = new UrlType();

        $this->expectException(\UnexpectedValueException::class);
        $type->parseValue('not-a-url');
    }

    public function testParseLiteralRejectsNonStringNode()
    {
        $type = new UrlType();

        $this->expectException(Error::class);
        $type->parseLiteral(new IntValueNode(['value' => 1]));
    }

    public function testParseLiteralRejectsInvalidUrl()
    {
        $type = new UrlType();
        $node = new StringValueNode(['value' => 'invalid']);

        $this->expectException(Error::class);
        $type->parseLiteral($node);
    }

    public function testSerializeAcceptsValidUrl()
    {
        $type = new UrlType();
        $this->assertSame('https://example.com', $type->serialize('https://example.com'));
    }

    public function testParseValueAcceptsValidUrl()
    {
        $type = new UrlType();
        $this->assertSame('https://example.com', $type->parseValue('https://example.com'));
    }

    public function testParseLiteralReturnsValidUrl()
    {
        $type = new UrlType();
        $node = new StringValueNode(['value' => 'https://example.com']);

        $this->assertSame('https://example.com', $type->parseLiteral($node));
    }
}
