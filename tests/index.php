<?php

/*
 * PHP-Foundation-Core (https://github.com/delight-im/PHP-Foundation-Core)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

// enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 'stdout');

header('Content-type: text/plain; charset=utf-8');

require __DIR__.'/../vendor/autoload.php';

$app = new \Delight\Foundation\App(
	__DIR__.'/../storage/app',
	__DIR__.'/../views',
	__DIR__.'/../storage/framework'
);
var_dump($app);
