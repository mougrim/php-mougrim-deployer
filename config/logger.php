<?php
declare(strict_types=1);
/**
 * @author Mougrim <rinat@mougrim.ru>
 */

namespace Mougrim\Deployer;

use Mougrim\Deployer\Logger\Logger;

return new Logger(
    logFilePath: __DIR__ . '/../logs/deployer.log',
);
