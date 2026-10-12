<?php

$appPath = getenv('SANCHAYA_TEST_APP') ?: dirname(__DIR__, 4);

require rtrim($appPath, '/').'/vendor/autoload.php';
require __DIR__.'/Fixtures/User.php';
require __DIR__.'/TestCase.php';
