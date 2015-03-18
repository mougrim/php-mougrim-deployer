<?php
namespace Mougrim\Deployer\Kernel;

/**
 * @package Mougrim\Deployer\Kernel
 * @author  Mougrim <rinat@mougrim.ru>
 */
class Application
{
    private $request;
    private $defaultCommand = 'help';
    private $defaultAction = 'index';
    private $controllersNamespace;

    /**
     * @return string
     */
    public function getControllersNamespace()
    {
        if ($this->controllersNamespace === null) {
            throw new \RuntimeException("controllersNamespace is not set");
        }
        return $this->controllersNamespace;
    }

    /**
     * @param string $controllersNamespace
     */
    public function setControllersNamespace($controllersNamespace)
    {
        if ($this->controllersNamespace !== null) {
            throw new \RuntimeException("controllersNamespace is already set");
        }
        $this->controllersNamespace = $controllersNamespace;
    }

    public function setRequest(Request $request)
    {
        if ($this->request !== null) {
            throw new \RuntimeException("Request is already set");
        }

        $this->request = $request;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        if ($this->request === null) {
            throw new \RuntimeException("Request is not set");
        }

        return $this->request;
    }

    public function run()
    {
        $requestParams = $this->getRequest()->getRequestParams();
        $config = $this->fetchConfig($requestParams);
        unset ($requestParams['config']);
        reset($requestParams);
        if (key($requestParams) !== 0) {
            $commandId = $this->defaultCommand;
            $actionId  = $this->defaultAction;
        } else {
            $commandId = array_shift($requestParams);
            if (key($requestParams) !== 0) {
                $actionId = $this->defaultAction;
            } else {
                $actionId = array_shift($requestParams);
            }
        }

        if (isset($config[$commandId][$actionId])) {
            $requestParams = array_merge($config[$commandId][$actionId], $requestParams);
        }

        $commandClass = $this->controllersNamespace . '\\' . ucfirst($commandId);
        /** @var AbstractCommand $command */
        $command = new $commandClass($this, $commandId);
        $subActions = $command::getSubActions($actionId);
        foreach ($subActions as $subActionId) {
            if (isset($config[$commandId][$subActionId])) {
                $requestParams = array_merge($config[$commandId][$subActionId], $requestParams);
            }
        }
        $command->setRequestParams($requestParams);
        $command->run($actionId);
    }

    private function fetchConfig($requestParams)
    {
        if (!isset($requestParams['config'])) {
            return [];
        }

        $configPath = $requestParams['config'];
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Config file '{$configPath}' not found");
        }
        if (!is_readable($configPath)) {
            throw new \RuntimeException("Config file '{$configPath}' not readable");
        }
        $configFileInfo = new \SplFileInfo($configPath);
        $extension      = $configFileInfo->getExtension();
        if ($extension === 'yaml') {
            if (!function_exists('yaml_parse_file')) {
                throw new \RuntimeException("yaml extension not loaded");
            }
            $config = yaml_parse_file($configPath);
        } elseif ($extension === 'php') {
            /** @noinspection PhpIncludeInspection */
            $config = require $configPath;
        } elseif ($extension === 'ini') {
            $config = parse_ini_file($configPath, true);
        } else {
            throw new \RuntimeException("Unknown config type {$extension}");
        }

        if (!is_array($config)) {
            throw new \RuntimeException("Can't parse config '{$configPath}'");
        }

        return $config;
    }

    public function end($status = 0)
    {
        exit($status);
    }
}
