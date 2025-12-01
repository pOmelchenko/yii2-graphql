<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

defined('YII_ENABLE_ERROR_HANDLER') or define('YII_ENABLE_ERROR_HANDLER', false);
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

Yii::setAlias('@yii/graphql', __DIR__ . '/src');
