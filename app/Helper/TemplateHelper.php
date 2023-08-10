<?php
declare(strict_types=1);

namespace Mougrim\Deployer\Helper;

use Psr\Log\LoggerInterface;
use RuntimeException;
use function dirname;
use function file_exists;
use function is_readable;
use function is_string;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class TemplateHelper
{
    public function __construct(
        private readonly ShellHelper $shellHelper,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function processTemplateToFile(
        string $user,
        string $templatePath,
        array $params,
        ?string $destinationPath = null,
    ): string {
        if (!file_exists($templatePath)) {
            throw new RuntimeException("Template '{$templatePath}' not exists");
        }
        if (!is_readable($templatePath)) {
            throw new RuntimeException("Template '{$templatePath}' not readable");
        }

        $this->logger->info("Process template {$templatePath}");

        if ($destinationPath === null) {
            $destinationPath = $templatePath;
        } else {
            $this->logger->info("Destination path {$destinationPath}");
        }

        /** @var string $destinationPath */
        $destinationDir = dirname($destinationPath);
        if (!file_exists($destinationDir)) {
            throw new RuntimeException("Destination dir '{$destinationDir}' not exists");
        }
        $this->shellHelper->sudo($user)->checkIsWritable($destinationDir);
        if (file_exists($destinationPath)) {
            $this->shellHelper->sudo($user)->checkIsWritable($destinationPath);
        }

        $template = file_get_contents($templatePath);
        $template = $this->processTemplateString($template, $params);
        $this->shellHelper->sudo($user)->writeFile($destinationPath, $template);
        $this->logger->info("Process complete");

        return $destinationDir;
    }

    public function processTemplateString(string $string, array $params): string
    {
        $replace = $this->prepareParams($params);

        $result = strtr($string, $replace);
        if (preg_match_all('/({{ .+? }})/', $result, $matches)) {
            $notProcessedVariables = implode(", ", $matches[1]);
            throw new RuntimeException("Not all variables processed: {$notProcessedVariables}");
        }

        return $result;
    }

    private function prepareParams(array $params): array
    {
        $params = $this->makeParamsFlatten($params);
        foreach ($params as &$value) {
            if (is_string($value)) {
                $value = strtr($value, $params);
            }
        }
        unset($value);
        return $params;
    }

    private function makeParamsFlatten(array $params, string $keyPrefix = '', array $replace = []): array
    {
        foreach ($params as $key => $value) {
            $key = $keyPrefix . $key;
            if (is_array($value)) {
                $replace = $this->makeParamsFlatten($value, $key . '.', $replace);
            } else {
                $replace["{{ {$key} }}"] = $value;
            }
        }

        return $replace;
    }
}
