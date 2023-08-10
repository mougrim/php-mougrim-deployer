#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * @author Mougrim <rinat@mougrim.ru>
 */

namespace Mougrim\Deployer;

// require composer autoloader
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    /** @noinspection PhpIncludeInspection */
    require_once $autoloadPath;
} else {
    /** @noinspection PhpIncludeInspection */
    require_once dirname(__DIR__, 3) . '/autoload.php';
}

use Mougrim\Deployer\Kernel\Application;
use Mougrim\Deployer\Kernel\Request;
use Psr\Log\LoggerInterface;
use Throwable;

/** @var LoggerInterface $logger */
$logger = require __DIR__ . '/../config/logger.php';

try {
    $request     = new Request($argv);
    $application = new Application(
        request: $request,
        controllersNamespace: '\Mougrim\Deployer\Command',
        logger: $logger,
    );
    $application->run();
} catch(Throwable $exception) {
    $logger->error('Uncaught exception', ['exception' => $exception]);
    exit(1);
}
