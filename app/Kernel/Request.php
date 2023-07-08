<?php
declare(strict_types=1);

namespace Mougrim\Deployer\Kernel;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class Request
{
    private array $requestParams;

    /**
     * @param array $rawRequest $argv
     */
    public function __construct(
        private readonly array $rawRequest,
    ) {
    }

    /**
     * @return array<int, string> $argv
     */
    public function getRawRequest(): array
    {
        return $this->rawRequest;
    }

    /**
     * @return array<int|string, string>
     */
    public function getRequestParams(): array
    {
        if (!isset($this->requestParams)) {
            $this->requestParams = $this->populateRequestParams();
        }

        return $this->requestParams;
    }

    public function getRequestParam(string|int $name, ?string $defaultValue = null): ?string
    {
        $requestParams = $this->getRequestParams();
        if (!isset($requestParams[$name]) && !array_key_exists($name, $requestParams)) {
            return $defaultValue;
        }

        return $requestParams[$name];
    }

    private function populateRequestParams(): array
    {
        $rawRequest = $this->getRawRequest();
        array_shift($rawRequest);
        $requestParams = [];

        foreach ($rawRequest as $param) {
            if (preg_match('/^--([\w-]+?)=(.*)$/s', $param, $matches)) {
                $requestParams[$matches[1]] = $matches[2];
            } elseif (preg_match('/^--([\w-]+?)$/', $param, $matches)) {
                $requestParams[$matches[1]] = true;
            } else {
                $requestParams[] = $param;
            }
        }

        return $requestParams;
    }
}
