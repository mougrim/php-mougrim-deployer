<?php
namespace Mougrim\Deployer\Kernel;

use Mougrim\Deployer\Helper\ShellHelper;

/**
 * @package Mougrim\Deployer
 * @author  Mougrim <rinat@mougrim.ru>
 */
class AbstractCommand
{
    protected $loggerName = 'command';
    private $logger;

    /**
     * @return \Logger
     */
    public function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = \Logger::getLogger($this->loggerName);
        }

        return $this->logger;
    }

    private $shellHelper;

    /**
     * @return ShellHelper
     */
    public function getShellHelper()
    {
        if ($this->shellHelper === null) {
            $this->shellHelper = new ShellHelper();
        }

        return $this->shellHelper;
    }

    private $id;

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    public function __construct($id)
    {
        $this->id = $id;
    }

    private $actionId;

    /**
     * @return string
     */
    public function getActionId()
    {
        return $this->actionId;
    }

    static public function getRequestParamsInfo()
    {
        return array();
    }

    static public function getInfo()
    {
        throw new \RuntimeException('Command can have description');
    }

    static public function getDescription()
    {
        return null;
    }

    static public function getActionsIdList()
    {
        $actions = array();
        $methods = get_class_methods(get_called_class());
        foreach ($methods as $method) {
            if (preg_match('/^action([A-Z][a-zA-Z0-9]*)$/', $method, $matches)) {
                $actions[] = lcfirst($matches[1]);
            }
        }

        return $actions;
    }

    private $requestParams;

    /**
     * @return array
     */
    public function getRequestParams()
    {
        if ($this->requestParams === null) {
            throw new \RuntimeException("requestParams is not set");
        }

        return $this->requestParams;
    }

    /**
     * @param array $requestParams
     */
    public function setRequestParams($requestParams)
    {
        if ($this->requestParams !== null) {
            throw new \RuntimeException("requestParams is already set");
        }

        $this->requestParams = $requestParams;
    }

    public function getRequestParam($name)
    {
        $requestParams = $this->getRequestParams();
        if (!isset($requestParams[$name]) && !array_key_exists($name, $requestParams)) {
            throw new \RuntimeException("Unknown param '{$name}'");
        }

        return $requestParams[$name];
    }

    public function run($actionId)
    {
        $this->actionId = $actionId;

        $actionMethodName = 'action' . ucfirst($actionId);

        if (!in_array($actionId, static::getActionsIdList(), true)) {
            throw new \RuntimeException("Unknown action '{$actionId}'");
        }

        $this->requestParams = $this->getActionRequestParams($actionId);

        $this->$actionMethodName();
    }

    private function getActionRequestParams($actionId)
    {
        $actionsRequestParamsInfo = static::getRequestParamsInfo();
        if (isset($actionsRequestParamsInfo[$actionId])) {
            $requestParamsInfo = $actionsRequestParamsInfo[$actionId];
        } else {
            $requestParamsInfo = array();
        }

        if (isset($actionsRequestParamsInfo['defaultParams'])) {
            foreach ($actionsRequestParamsInfo['defaultParams'] as $paramName => $paramInfo) {
                if (!isset($requestParamsInfo[$paramName])) {
                    $requestParamsInfo[$paramName] = $paramInfo;
                }
            }
        }
        $requestParams = $this->getRequestParams();
        $unknownParams = array_diff(array_keys($requestParams), array_keys($requestParamsInfo));
        if ($unknownParams) {
            throw new \RuntimeException("Unknown params '" . implode("', '", $unknownParams) . "'");
        }

        $emptyRequireParams = array();
        foreach ($requestParamsInfo as $paramName => $paramInfo) {
            if (!isset($requestParams[$paramName])) {
                if (isset($paramInfo['require']) && $paramInfo['require']) {
                    $emptyRequireParams[] = $paramName;
                } elseif (isset($paramInfo['default']) && $paramInfo['default']) {
                    $requestParams[$paramName] = $paramInfo['default'];
                }
            }

            if (isset($requestParams[$paramName])) {
                if (isset($paramInfo['multiple']) && $paramInfo['multiple']) {
                    if (!is_array($requestParams[$paramName])) {
                        $paramValue                  = $requestParams[$paramName];
                        $requestParams[$paramName]   = array();
                        $requestParams[$paramName][] = $paramValue;
                    }
                } else {
                    if (is_array($requestParams[$paramName])) {
                        $requestParams[$paramName] = end($requestParams[$paramName]);
                    }
                }
            } else {
                $requestParams[$paramName] = null;
            }
        }

        if ($emptyRequireParams) {
            throw new \RuntimeException("Params '" . implode("', '", $emptyRequireParams) . "' is require");
        }
        return $requestParams;
    }
}
