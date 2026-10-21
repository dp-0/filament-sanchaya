<?php

$appPath = getenv('SANCHAYA_TEST_APP') ?: dirname(__DIR__, 4);

$loader = require rtrim($appPath, '/').'/vendor/autoload.php';
$loader->addPsr4('DP0\\Sanchaya\\Tests\\', __DIR__.'/');
