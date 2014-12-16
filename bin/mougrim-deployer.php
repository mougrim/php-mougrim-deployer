#!/usr/bin/env php
<?php
/**
 * @author Mougrim <rinat@mougrim.ru>
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Mougrim\Deployer\Kernel\Request;
use Mougrim\Deployer\Kernel\Application;

Logger::configure(require_once __DIR__ . '/../config/logger.php');

$request = new Request();
$request->setRawRequest($argv);
$application = new Application();
$application->setControllersNamespace('\Mougrim\Deployer\Command');
$application->setRequest($request);
$application->run();
