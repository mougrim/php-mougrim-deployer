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

    private $application;

    protected function getApplication()
    {
        return $this->application;
    }

    public function __construct(Application $application, $id)
    {
        $this->application = $application;
        $this->id = $id;
    }

    private $actionId;

    /**
     * @return string
     */
    public function getActionId()
    {
        if ($this->actionId === null) {
            throw new \RuntimeException("action not run");
        }
        return $this->actionId;
    }

    static public function getRawRequestParamsInfo()
    {
        return [];
    }

    static public function getRequestParamsInfo()
    {
        $requestParamsInfo = static::getRawRequestParamsInfo();
        foreach (static::getActionsSubActions() as $actionId => $subActions) {
            if (!isset($requestParamsInfo[$actionId])) {
                $requestParamsInfo[$actionId] = [];
            }
            foreach ($subActions as $subActionId) {
                if (isset($requestParamsInfo[$subActionId])) {
                    $requestParamsInfo[$actionId] = array_merge(
                        $requestParamsInfo[$subActionId],
                        $requestParamsInfo[$actionId]
                    );
                }
            }
        }
        return $requestParamsInfo;
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
        $actions = [];
        $methods = get_class_methods(get_called_class());
        foreach ($methods as $method) {
            if (preg_match('/^action([A-Z][a-zA-Z0-9]*)$/', $method, $matches)) {
                $actions[] = lcfirst($matches[1]);
            }
        }

        return $actions;
    }

    static public function getActionsSubActions()
    {
        return [];
    }

    static public function getSubActions($actionId)
    {
        $actionsSubActions = static::getActionsSubActions();
        if (isset($actionsSubActions[$actionId])) {
            return $actionsSubActions[$actionId];
        } else {
            return [];
        }
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

    public function addRequestParam($name, $value)
    {
        if ($this->requestParams === null) {
            throw new \RuntimeException("requestParams is not set");
        }

        $this->requestParams[$name] = $value;
    }

    public function getRequestParam($name)
    {
        $requestParams = $this->getRequestParams();
        if (!$this->requestParamExists($name)) {
            throw new \RuntimeException("Unknown param '{$name}'");
        }

        return $requestParams[$name];
    }

    public function requestParamExists($name) {
        $requestParams = $this->getRequestParams();
        return isset($requestParams[$name]) || array_key_exists($name, $requestParams);
    }

    protected function getAdditionalParams()
    {
        return [];
    }

    private $params = [];

    public function getParams()
    {
        if (!isset($this->params[$this->getActionId()])) {
            $params = $this->getRequestParams();
            $additionalParams = $this->getAdditionalParams();
            $subActions = array_merge(static::getSubActions($this->getActionId()), [$this->getActionId()]);
            foreach ($subActions as $actionId) {
                if (isset($additionalParams[$actionId])) {
                    $params = array_merge($params, $additionalParams[$actionId]);
                }
            }
            $this->params[$this->getActionId()] = $params;
        }

        return $this->params[$this->getActionId()];
    }

    public function getParam($name)
    {
        $params = $this->getParams();
        if (!$this->paramExists($name)) {
            throw new \RuntimeException("Unknown param '{$name}'");
        }

        return $params[$name];
    }

    public function paramExists($name) {
        $params = $this->getParams();
        return isset($params[$name]) || array_key_exists($name, $params);
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
            $requestParamsInfo = [];
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

        $emptyRequireParams = [];
        foreach ($requestParamsInfo as $paramName => $paramInfo) {
            if (!isset($requestParams[$paramName])) {
                if (isset($paramInfo['require']) && $paramInfo['require']) {
                    $emptyRequireParams[] = $paramName;
                    continue;
                } elseif (isset($paramInfo['default']) && $paramInfo['default']) {
                    $requestParams[$paramName] = $paramInfo['default'];
                }
            }

            if (isset($requestParams[$paramName])) {
                if (isset($paramInfo['multiple']) && $paramInfo['multiple']) {
                    if (!is_array($requestParams[$paramName])) {
                        $paramValue                  = $requestParams[$paramName];
                        $requestParams[$paramName]   = [];
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
