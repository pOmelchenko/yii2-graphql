<?php

namespace yii\graphql;

use GraphQL\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Module as YiiModule;
use yii\graphql\exceptions\SchemaNotFound;
use yii\graphql\exceptions\TypeNotFound;
use yii\helpers\ArrayHelper;

/**
 * GraphQL facade class
 * In the use of each Module, graphql is independent and has no single example,
 * because it has a certain coupling with the Module instance.
 *
 * @package yii\graphql
 */
class GraphQL
{
    /**
     * @var array query map config
     */
    public $queries = [];
    /**
     * @var array mutation map config
     */
    public $mutations = [];
    /**
     * @var array type map config
     */
    public $types = [];

    public $errorFormatter;

    private $currentDocument;
    /**
     * @var TypeResolution|null
     */
    private $typeResolution;

    /**
     * @var TypeResolution|null standalone resolver used when module context is unavailable
     */
    private static ?TypeResolution $standaloneTypeResolution = null;

    public function __construct()
    {
    }

    /**
     * get TypeResolution
     * @return TypeResolution
     */
    public function getTypeResolution()
    {
        if (!$this->typeResolution) {
            $this->typeResolution = new TypeResolution();
        }
        return $this->typeResolution;
    }

    /**
     * Receive schema data and incorporate configuration information
     *
     * array format：
     * $schema = new [
     *   'query'=>[
     *      //the key is alias，mutation,types and so on
     *      'hello'=>HelloQuery::class
     *   ],
     *   'mutation'=>[],
     *   'types'=>[],
     * ];
     * @param null|array $schema Configuration array merged into the instance for persistence
     */
    public function schema($schema = null)
    {
        if (is_array($schema)) {
            $schemaQuery = ArrayHelper::getValue($schema, 'query', []);
            $schemaMutation = ArrayHelper::getValue($schema, 'mutation', []);
            $schemaTypes = ArrayHelper::getValue($schema, 'types', []);
            $this->queries += $schemaQuery;
            $this->mutations += $schemaMutation;
            $this->types += $schemaTypes;
            $this->getTypeResolution()->setAlias($schemaTypes);
        }
    }

    /**
     * GraphQl Schema is built according to input. Especially,
     * due to the need of Module and Controller in the process of building ObjectType,
     * the execution position of the method is restricted to a certain extent.
     * @param Schema|array|null $schema schema data
     * @return Schema
     */
    public function buildSchema($schema = null)
    {
        if ($schema instanceof Schema) {
            return $schema;
        }
        if ($schema === null && empty($this->queries) && empty($this->mutations)) {
            throw new SchemaNotFound('Schema not defined.', 404);
        }
        if ($schema === null) {
            list($schemaQuery, $schemaMutation, $schemaTypes) = [$this->queries, $this->mutations, $this->types];
        } else {
            list($schemaQuery, $schemaMutation, $schemaTypes) = $schema;
        }
        if (empty($schemaQuery) && empty($schemaMutation)) {
            throw new SchemaNotFound('Schema not found for requested operation.', 404);
        }
        $types = [];
        if (sizeof($schemaTypes)) {
            foreach ($schemaTypes as $name => $type) {
                $types[] = $this->getTypeResolution()->parseType($name, true);
            }
        }
        // GraphQL validator requires every schema to have a query entry
        $query = $this->getTypeResolution()->objectType($schemaQuery, [
            'name' => 'Query'
        ]);

        $mutation = null;
        if (!empty($schemaMutation)) {
            $mutation = $this->getTypeResolution()->objectType($schemaMutation, [
                'name' => 'Mutation'
            ]);
        }

        $this->getTypeResolution()->initTypes([$query, $mutation], $schema == null);

        $result = new Schema([
            'query' => $query,
            'mutation' => $mutation,
            'types' => $types,
            'typeLoader' => function ($name) {
                return $this->getTypeResolution()->parseType($name, true);
            }
        ]);
        return $result;
    }


    /**
     * query access
     * @param $requestString
     * @param null $rootValue
     * @param null $contextValue
     * @param null $variableValues
     * @param string $operationName
     * @return array|Error\InvariantViolation
     */
    public function query($requestString, $rootValue = null, $contextValue = null, $variableValues = null, $operationName = null)
    {
        $sl = $this->parseRequestQuery($requestString);
        if ($sl === true) {
            $sl = [$this->queries, $this->mutations, $this->types];
        }
        $schema = $this->buildSchema($sl);

        $val = $this->execute($schema, $rootValue, $contextValue, $variableValues, $operationName);
        return $this->getResult($val);
    }

    /**
     * @param $executeResult
     * @return array|Promise
     */
    public function getResult($executeResult)
    {
        if ($executeResult instanceof ExecutionResult) {
            if ($this->errorFormatter) {
                $executeResult->setErrorFormatter($this->errorFormatter);
            }
            return $this->parseExecutionResult($executeResult);
        } elseif ($executeResult instanceof Promise) {
            return $executeResult->then(function (ExecutionResult $executionResult) {
                if ($this->errorFormatter) {
                    $executionResult->setErrorFormatter($this->errorFormatter);
                }
                return $this->parseExecutionResult($executionResult);
            });
        } else {
            throw new Error\InvariantViolation("Unexpected execution result");
        }
    }

    private function parseExecutionResult(ExecutionResult $executeResult)
    {
        if (empty($executeResult->errors) || empty($this->errorFormatter)) {
            return $executeResult->toArray();
        }
        $result = [];

        if (null !== $executeResult->data) {
            $result['data'] = $executeResult->data;
        }

        if (!empty($executeResult->errors)) {
            $result['errors'] = [];
            foreach ($executeResult->errors as $er) {
                $fn = $this->errorFormatter;
                $fr = $fn($er);
                if (isset($fr['message'])) {
                    $result['errors'][] = $fr;
                } else {
                    $result['errors'] += $fr;
                }
            }
//            $result['errors'] = array_map($executeResult->errorFormatter, $executeResult->errors);
        }

        if (!empty($executeResult->extensions)) {
            $result['extensions'] = (array)$executeResult->extensions;
        }

        return $result;
    }

    /**
     * Executing the query according to schema, this method needs to be executed after the schema is generated
     * @param $schema
     * @param $rootValue
     * @param $contextValue
     * @param $variableValues
     * @param $operationName
     * @return ExecutionResult|Promise
     */
    public function execute($schema, $rootValue, $contextValue, $variableValues, $operationName)
    {
        try {
            /** @var QueryComplexity $queryComplexity */
            $queryComplexity = DocumentValidator::getRule('QueryComplexity');
            $queryComplexity->setRawVariableValues($variableValues);

            $validationErrors = DocumentValidator::validate($schema, $this->currentDocument);

            if (!empty($validationErrors)) {
                return new ExecutionResult(null, $validationErrors);
            }
            $operationName = $operationName === '' ? null : $operationName;
            return Executor::execute($schema, $this->currentDocument, $rootValue, $contextValue, $variableValues, $operationName);
        } catch (Error\Error $e) {
            return new ExecutionResult(null, [$e]);
        } finally {
            $this->currentDocument = null;
        }
    }

    /**
     * Convert a raw query into an array consumable by the schema builder.
     * @param $requestString
     * @return array|bool Array indexes: 0 query, 1 mutation, 2 types. True indicates IntrospectionQuery.
     */
    public function parseRequestQuery($requestString)
    {
        $source = new Source($requestString ?: '', 'GraphQL request');
        $this->currentDocument = Parser::parse($source);
        $queryTypes = [];
        $mutation = [];
        $types = [];
        $isAll = false;
        foreach ($this->currentDocument->definitions as $definition) {
            if (!($definition instanceof OperationDefinitionNode)) {
                continue;
            }

            foreach ($definition->selectionSet->selections as $selection) {
                if (!($selection instanceof FieldNode)) {
                    continue;
                }

                $node = $selection->name;

                if ($definition->operation === 'query') {
                    if ($definition->name && $definition->name->value === 'IntrospectionQuery') {
                        $isAll = true;
                        break 2;
                    }
                    if (isset($this->queries[$node->value])) {
                        $queryTypes[$node->value] = $this->queries[$node->value];
                    }
                    if (isset($this->types[$node->value])) {
                        $types[$node->value] = $this->types[$node->value];
                    }
                } elseif ($definition->operation === 'mutation') {
                    if (isset($this->mutations[$node->value])) {
                        $mutation[$node->value] = $this->mutations[$node->value];
                    }
                }
            }
        }
        return $isAll ?: [$queryTypes, $mutation, $types];
    }

    /**
     * Type manager access
     * @param string|Type $name
     * @param bool $byAlias if use alias
     * @return mixed
     */
    public static function type($name, $byAlias = false)
    {
        $module = null;
        if (Yii::$app !== null) {
            /** @var \yii\base\Controller|null $controller */
            $controller = Yii::$app->controller;
            /** @var YiiModule|null $module */
            $module = $controller !== null ? $controller->module : Yii::$app->getModule('graphql');
        }

        if ($module !== null) {
            if ($module instanceof GraphQLModuleInterface) {
                $gql = $module->getGraphQL();
            } elseif (method_exists($module, 'getGraphQL')) {
                // TODO: drop legacy trait fallback and throw InvalidConfigException in the next major release.
                trigger_error('Using GraphQLModuleTrait without implementing GraphQLModuleInterface is deprecated and will throw an exception in a future release.', E_USER_DEPRECATED);
                $gql = $module->getGraphQL();
            } else {
                throw new InvalidConfigException('GraphQL module must implement GraphQLModuleInterface.');
            }

            return $gql->getTypeResolution()->parseType($name, $byAlias);
        }

        return self::getStandaloneTypeResolution()->parseType($name, $byAlias);
    }

    /**
     * @param $class
     * @param null $name
     */
    public function addType($class, $name = null)
    {
        $name = $this->getTypeName($class, $name);
        $this->types[$name] = $class;
    }

    /**
     *
     * @param string|object $class
     * @param string|null $name
     * @return string
     * @throws InvalidConfigException
     */
    protected function getTypeName($class, $name = null): string
    {
        if ($name) {
            return $name;
        }

        $type = is_object($class) ? $class : Yii::createObject($class);
        return $type->name;
    }

    /**
     * Reset standalone type resolution cache (primarily for tests).
     */
    public static function resetStandaloneTypeResolution(): void
    {
        self::$standaloneTypeResolution = null;
    }

    private static function getStandaloneTypeResolution(): TypeResolution
    {
        if (self::$standaloneTypeResolution === null) {
            self::$standaloneTypeResolution = new TypeResolution();
        }
        return self::$standaloneTypeResolution;
    }

    /**
     * set error formatter
     * @param Callable $errorFormatter
     */
    public function setErrorFormatter(callable $errorFormatter)
    {
        $this->errorFormatter = $errorFormatter;
    }

    /**
     * validate the schema.
     *
     * when initial the schema,the types parameter must not passed.
     *
     * @param Schema $schema
     */
    public function assertValid($schema)
    {
        //the type come from the TypeResolution.
        foreach ($this->types as $name => $type) {
            $schema->getType($name);
        }
        $schema->assertValid();
    }
}
