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

        $commandClass = $this->controllersNamespace . '\\' . ucfirst($commandId);
        /** @var AbstractCommand $command */
        $command = new $commandClass($commandId);
        $command->setRequestParams($requestParams);
        $command->run($actionId);
    }
}
