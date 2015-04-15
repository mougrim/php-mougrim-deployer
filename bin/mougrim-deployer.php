#!/usr/bin/env php
<?php
/**
 * @author Mougrim <rinat@mougrim.ru>
 */
// require composer autoloader
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    /** @noinspection PhpIncludeInspection */
    require_once $autoloadPath;
} else {
    /** @noinspection PhpIncludeInspection */
    require_once dirname(dirname(dirname(__DIR__))) . '/autoload.php';
}

use Mougrim\Deployer\Kernel\Request;
use Mougrim\Deployer\Kernel\Application;

Logger::configure(require_once __DIR__ . '/../config/logger.php');

try {
    $request = new Request();
    $request->setRawRequest($argv);
    $application = new Application();
    $application->setControllersNamespace('\Mougrim\Deployer\Command');
    $application->setRequest($request);
    $application->run();
} catch (Exception $exception) {
    Logger::getLogger('dispatcher')->error("Uncaught exception:", $exception);
    exit(1);
}
