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

    public function testInternalExceptionDoesNotExposeItsMessageAsAClientError()
    {
        $exception = new \RuntimeException('Database password: secret');
        $error = new GraphQLError('Resolver failed', null, null, [], null, $exception);

        $formatted = ErrorFormatter::formatError($error);

        $this->assertSame('Internal server error', $formatted['message']);
    }

    public function testClientAwareExceptionPreservesItsSafeMessage()
    {
        $exception = new class ('Public error') extends \RuntimeException implements \GraphQL\Error\ClientAware {
            public function isClientSafe()
            {
                return true;
            }

            public function getCategory()
            {
                return 'application';
            }
        };
        $error = new GraphQLError($exception->getMessage(), null, null, [], null, $exception);

        $formatted = ErrorFormatter::formatError($error);

        $this->assertSame('Public error', $formatted['message']);
        $this->assertSame('application', $formatted['extensions']['category']);
    }
}
