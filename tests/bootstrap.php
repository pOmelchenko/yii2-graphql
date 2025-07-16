<?php
// ensure we get report on all possible php errors
error_reporting(-1);
define('YII_ENABLE_ERROR_HANDLER', false);
define('YII_DEBUG', true);

$_SERVER['SCRIPT_NAME'] = '/' . __DIR__;
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

// Detect vendor dir
if (is_dir(__DIR__ . '/../vendor/')) {
    $vendorRoot = __DIR__ . '/../vendor'; // this extension has its own vendor folder
} else {
    $vendorRoot = __DIR__ . '/../../..'; // this extension is part of a project vendor folder
}

require_once $vendorRoot . '/autoload.php';
require_once $vendorRoot . '/yiisoft/yii2/Yii.php';

// Set aliases
Yii::setAlias('@yiiunit/extensions/graphql', __DIR__);
Yii::setAlias('@yiiunit', __DIR__ . '/../tests');
Yii::setAlias('@yii/graphql', dirname(__DIR__) . '/src');

// Minimal mock $_SERVER vars for web Application
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['DOCUMENT_ROOT'] = __DIR__;

// Minimal application setup for tests
new \yii\web\Application([
    'id' => 'test-app',
    'basePath' => dirname(__DIR__),
    'components' => [
        // Request configuration with disabled CSRF validation and test cookie key
        'request' => [
            'cookieValidationKey' => 'test-key',
            'enableCsrfValidation' => false,
        ],

        // Response component with JSON formatter enabled
        'response' => [
            'formatters' => [
                \yii\web\Response::FORMAT_JSON => \yii\web\JsonResponseFormatter::class,
            ],
        ],

        // Disable session handling entirely using DummySession (prevents headers/cookie errors in CLI)
        'session' => [
            'class' => \yii\web\DummySession::class,
        ],

        // Disable session support in user component to avoid session_start() calls during tests
        'user' => [
            'class' => \yii\web\User::class,
            'enableSession' => false,
            'identityClass' => null, // set to null if no authentication is used in tests
        ],
    ],
]);
