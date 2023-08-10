<?php
declare(strict_types=1);

namespace Mougrim\Deployer\Helper;

use Psr\Log\LoggerInterface;
use RuntimeException;
use function dirname;
use function file_exists;
use function file_get_contents;
use function is_readable;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class TemplateShellHelper
{
    public function __construct(
        private readonly ShellHelper $shellHelper,
        private readonly TemplateHelper $templateHelper,
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
        $template = $this->templateHelper->processTemplateString($template, $params);
        $this->shellHelper->sudo($user)->writeFile($destinationPath, $template);
        $this->logger->info("Process complete");

        return $destinationDir;
    }
}
