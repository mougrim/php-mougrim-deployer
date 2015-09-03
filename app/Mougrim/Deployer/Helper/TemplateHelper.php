<?php
namespace Mougrim\Deployer\Helper;

use Mougrim\Logger\Logger;

/**
 * @package Mougrim\Deployer\Helper
 * @author Mougrim <rinat@mougrim.ru>
 */
class TemplateHelper
{
    private $shellHelper;

    public function setShellHelper(ShellHelper $shellHelper)
    {
        $this->shellHelper = $shellHelper;
    }

    /**
     * @return ShellHelper
     */
    protected function getShellHelper()
    {
        return $this->shellHelper;
    }

    private $logger;

    /**
     * @return Logger
     */
    protected function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = Logger::getLogger('template-helper');
        }

        return $this->logger;
    }

    public function processTemplateToFile($user, $templatePath, array $params, $destinationPath = null)
    {
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template '{$templatePath}' not exists");
        }
        if (!is_readable($templatePath)) {
            throw new \RuntimeException("Template '{$templatePath}' not readable");
        }

        $this->getLogger()->info("Process template {$templatePath}");

        if ($destinationPath === null) {
            $destinationPath = $templatePath;
        } else {
            $this->getLogger()->info("Destination path {$destinationPath}");
        }

        $destinationDir = dirname($destinationPath);
        if (!file_exists($destinationDir)) {
            throw new \RuntimeException("Destination dir '{$destinationDir}' not exists");
        }
        $this->getShellHelper()->sudo($user)->checkIsWritable($destinationDir);
        if (file_exists($destinationPath)) {
            $this->getShellHelper()->sudo($user)->checkIsWritable($destinationPath);
        }

        $template = file_get_contents($templatePath);
        $template = $this->processTemplateString($template, $params);
        $this->getShellHelper()->sudo($user)->writeFile($destinationPath, $template);
        $this->getLogger()->info("Process complete");

        return $destinationDir;
    }

    public function processTemplateString($string, array $params)
    {
        $replace = $this->getReplace($params);

        $result = strtr($string, $replace);
        if (preg_match_all('/({{ .+? }})/', $result, $matches)) {
            $notProcessedVariables = implode(", ", $matches[1]);
            throw new \RuntimeException("Not all variables processed: {$notProcessedVariables}");
        }

        return $result;
    }

    private function getReplace($params, $keyPrefix = '', $replace= []) {
        foreach ($params as $key => $value) {
            $key = $keyPrefix . $key;
            if (is_array($value)) {
                $replace = $this->getReplace($value, $key . '.', $replace);
            } else {
                $replace["{{ {$key} }}"] = $value;
            }
        }

        return $replace;
    }
}
