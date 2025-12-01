<?php

namespace yiiunit\extensions\graphql;

use GraphQL\Error\Error as GraphQLError;
use yii\base\DynamicModel;
use yii\graphql\ErrorFormatter;
use yii\graphql\exceptions\SchemaNotFound;
use yii\graphql\exceptions\ValidatorException;
use yii\web\ForbiddenHttpException;

class ErrorFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    public function testFormatsValidatorException()
    {
        $model = new DynamicModel(['email']);
        $model->addError('email', 'invalid email');
        $exception = new ValidatorException($model);
        $error = new GraphQLError('Validation failed', null, null, [], null, $exception);

        $formatted = ErrorFormatter::formatError($error);

        $this->assertSame($exception->formatErrors, $formatted);
    }

    public function testFormatsSchemaNotFound()
    {
        $exception = new SchemaNotFound('Missing schema', 0);
        $error = new GraphQLError('Schema error', null, null, [], null, $exception);

        $formatted = ErrorFormatter::formatError($error);

        $this->assertSame(['code' => 404, 'message' => 'Missing schema'], $formatted);
    }

    public function testFormatsHttpException()
    {
        $exception = new ForbiddenHttpException('Access denied');
        $error = new GraphQLError('Forbidden', null, null, [], null, $exception);

        $formatted = ErrorFormatter::formatError($error);

        $this->assertSame(['code' => 403, 'message' => 'Access denied'], $formatted);
    }

    public function testFallsBackToFormattedErrorWhenNoPrevious()
    {
        $error = new GraphQLError('Plain error');
        $formatted = ErrorFormatter::formatError($error);

        $this->assertArrayHasKey('message', $formatted);
        $this->assertSame('Plain error', $formatted['message']);
    }
}
