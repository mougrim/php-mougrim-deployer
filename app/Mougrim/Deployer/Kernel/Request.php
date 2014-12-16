<?php
namespace Mougrim\Deployer\Kernel;

/**
 * @package Mougrim\Deployer\Kernel
 * @author  Mougrim <rinat@mougrim.ru>
 */
class Request
{
    private $rawRequest;
    private $requestParams;

    /**
     * @param array $rawRequest $argv
     */
    public function setRawRequest(array $rawRequest)
    {
        if ($this->rawRequest !== null) {
            throw new \RuntimeException("rawRequest is already set");
        }
        $this->rawRequest = $rawRequest;
    }

    /**
     * @return array $argv
     */
    public function getRawRequest()
    {
        if ($this->rawRequest === null) {
            throw new \RuntimeException("rawRequest is not set");
        }

        return $this->rawRequest;
    }

    /**
     * @return array
     */
    public function getRequestParams()
    {
        if ($this->requestParams === null) {
            $this->requestParams = $this->populateRequestParams();
        }

        return $this->requestParams;
    }

    public function getRequestParam($name, $defaultValue = null)
    {
        $requestParams = $this->getRequestParams();
        if (!isset($requestParams[$name]) && !array_key_exists($name, $requestParams)) {
            return $defaultValue;
        }

        return $requestParams[$name];
    }

    private function populateRequestParams()
    {
        $rawRequest = $this->getRawRequest();
        array_shift($rawRequest);
        $requestParams = array();

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
