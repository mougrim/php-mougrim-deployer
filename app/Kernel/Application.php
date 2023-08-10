<?php /** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace Mougrim\Deployer\Kernel;

use Mougrim\Deployer\Helper\ShellHelper;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SplFileInfo;
use function array_merge;
use function array_shift;
use function file_exists;
use function file_get_contents;
use function function_exists;
use function is_readable;
use function json_decode;
use function key;
use function reset;
use function yaml_parse_file;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class Application
{
    private readonly string $defaultCommand;
    private readonly string $defaultAction;

    public function __construct(
        private readonly Request $request,
        private readonly string $controllersNamespace,
        private readonly LoggerInterface $logger,
    ) {
        $this->defaultCommand = 'help';
        $this->defaultAction  = 'index';
    }

    public function run(): void
    {
        $requestParams = $this->request->getRequestParams();
        $config        = $this->fetchConfig($requestParams);
        unset($requestParams['config']);
        reset($requestParams);
        if (key($requestParams) !== 0) {
            $commandId = $this->defaultCommand;
            $actionId  = $this->defaultAction;
        } else {
            $commandId = (string) array_shift($requestParams);
            if (key($requestParams) !== 0) {
                $actionId = $this->defaultAction;
            } else {
                $actionId = (string) array_shift($requestParams);
            }
        }

        if (isset($config[$commandId][$actionId])) {
            $requestParams = array_merge($config[$commandId][$actionId], $requestParams);
        }

        /** @var AbstractCommand $commandClass */
        $commandClass = $this->controllersNamespace . '\\' . ucfirst($commandId);
        $subActions   = $commandClass::getSubActions($actionId);
        foreach ($subActions as $subActionId) {
            if (isset($config[$commandId][$subActionId])) {
                $requestParams = array_merge($config[$commandId][$subActionId], $requestParams);
            }
        }
        /** @see AbstractCommand::__construct */
        $command = new $commandClass(
            application: $this,
            shellHelper: new ShellHelper(logger: $this->logger),
            logger: $this->logger,
            requestParams: $requestParams,
            id: $commandId,
            actionId: $actionId,
        );
        $command->run();
    }

    private function fetchConfig(array $requestParams): array
    {
        if (!isset($requestParams['config'])) {
            return [];
        }

        $configPath = $requestParams['config'];
        if (!file_exists($configPath)) {
            throw new RuntimeException("Config file '{$configPath}' not found");
        }
        if (!is_readable($configPath)) {
            throw new RuntimeException("Config file '{$configPath}' not readable");
        }
        $configFileInfo = new SplFileInfo($configPath);
        $extension      = $configFileInfo->getExtension();
        if ($extension === 'yaml') {
            if (!function_exists('yaml_parse_file')) {
                throw new RuntimeException("yaml extension not loaded");
            }
            $config = yaml_parse_file($configPath);
        } elseif ($extension === 'json') {
            if (!function_exists('json_decode')) {
                throw new RuntimeException("json extension not loaded");
            }
            $json_string = file_get_contents($configPath);
            $config      = json_decode($json_string, true);
        } elseif ($extension === 'php') {
            $config = require $configPath;
        } else {
            throw new RuntimeException("Unknown config type {$extension}");
        }

        if (!is_array($config)) {
            throw new RuntimeException("Can't parse config '{$configPath}'");
        }

        return $config;
    }

    public function end($status = 0): never
    {
        exit($status);
    }
}
