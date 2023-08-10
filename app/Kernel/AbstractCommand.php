<?php
declare(strict_types=1);

namespace Mougrim\Deployer\Kernel;

use Mougrim\Deployer\Helper\ShellHelper;
use Psr\Log\LoggerInterface;
use RuntimeException;
use function array_diff;
use function array_keys;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
abstract class AbstractCommand
{
    /**
     * @param array<int|string, string|null|array> $requestParams
     */
    public function __construct(
        protected readonly Application $application,
        protected readonly ShellHelper $shellHelper,
        protected readonly LoggerInterface $logger,
        protected array $requestParams,
        protected readonly string $id,
        protected readonly string $actionId,
    ) {
    }

    static public function getRawRequestParamsInfo(): array
    {
        return [];
    }

    static public function getRequestParamsInfo(): array
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

    abstract static public function getInfo(): string;

    static public function getDescription(): ?string
    {
        return null;
    }

    /**
     * @return array<string>
     */
    static public function getActionsIdList(): array
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

    static public function getActionsSubActions(): array
    {
        return [];
    }

    static public function getSubActions(string $actionId): array
    {
        $actionsSubActions = static::getActionsSubActions();
        return $actionsSubActions[$actionId] ?? [];
    }

    public function getRequestParam(string|int $name): string|null|array
    {
        if (!$this->requestParamExists($name)) {
            throw new RuntimeException("Unknown param '{$name}'");
        }

        return $this->requestParams[$name];
    }

    public function requestParamExists($name): bool
    {
        return isset($this->requestParams[$name]) || array_key_exists($name, $this->requestParams);
    }

    protected function getAdditionalParams(): array
    {
        return [];
    }

    private array $params = [];

    /**
     * @return array<int|string, string|null|array>
     */
    public function getParams(): array
    {
        if (!isset($this->params[$this->actionId])) {
            $params           = $this->requestParams;
            $additionalParams = $this->getAdditionalParams();
            $subActions       = array_merge(static::getSubActions($this->actionId), [$this->actionId]);
            foreach ($subActions as $actionId) {
                if (isset($additionalParams[$actionId])) {
                    $params = array_merge($params, $additionalParams[$actionId]);
                }
            }
            $this->params[$this->actionId] = $params;
        }

        return $this->params[$this->actionId];
    }

    public function getParam(string|int $name): string|null|array|bool
    {
        $params = $this->getParams();
        if (!$this->paramExists($name)) {
            throw new RuntimeException("Unknown param '{$name}'");
        }

        return $params[$name];
    }

    public function paramExists(string|int $name): bool
    {
        $params = $this->getParams();
        return isset($params[$name]) || array_key_exists($name, $params);
    }

    public function run(): void
    {
        $actionMethodName = 'action' . ucfirst($this->actionId);

        if (!in_array($this->actionId, static::getActionsIdList(), true)) {
            throw new RuntimeException("Unknown action '{$this->actionId}'");
        }

        $this->requestParams = $this->getActionRequestParams($this->actionId);

        $this->$actionMethodName();
    }

    /**
     * @return array<int|string, string|null|array>
     */
    private function getActionRequestParams(string $actionId): array
    {
        $actionsRequestParamsInfo = static::getRequestParamsInfo();
        $requestParamsInfo        = $actionsRequestParamsInfo[$actionId] ?? [];

        if (isset($actionsRequestParamsInfo['defaultParams'])) {
            foreach ($actionsRequestParamsInfo['defaultParams'] as $paramName => $paramInfo) {
                if (!isset($requestParamsInfo[$paramName])) {
                    $requestParamsInfo[$paramName] = $paramInfo;
                }
            }
        }
        $requestParams = $this->requestParams;
        $unknownParams = array_diff(array_keys($requestParams), array_keys($requestParamsInfo));
        if ($unknownParams) {
            throw new RuntimeException("Unknown params '" . implode("', '", $unknownParams) . "'");
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
            throw new RuntimeException("Params '" . implode("', '", $emptyRequireParams) . "' is require");
        }
        return $requestParams;
    }
}
