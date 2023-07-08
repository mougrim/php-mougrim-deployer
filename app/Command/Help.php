<?php
declare(strict_types=1);

namespace Mougrim\Deployer\Command;

use Mougrim\Deployer\Kernel\AbstractCommand;
use RuntimeException;
use function class_exists;
use function lcfirst;
use function str_starts_with;
use function substr;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class Help extends AbstractCommand
{
    static public function getInfo(): string
    {
        return 'this command';
    }

    static public function getRawRequestParamsInfo(): array
    {
        return [
            'defaultParams' => [
                0 => [
                    'info' => 'action name',
                ],
            ],
        ];
    }

    public function actionIndex(): void
    {
        $this->logger->info("Usage: mougrim-deployer.php <command> [<action>] [<args>]");
        $this->logger->info("Commands list:");
        foreach (static::getAvailableCommands() as $commandName) {
            /** @var AbstractCommand $commandClass */
            $commandClass = '\Mougrim\Deployer\Command\\' . ucfirst($commandName);
            $this->logger->info("\t" . $commandName . "\t" . $commandClass::getInfo());
        }
    }

    public function __call(string $methodName, array $params = [])
    {
        if (!str_starts_with($methodName, 'action')) {
            throw new RuntimeException('Call to undefined method ' . get_class() . '::' . $methodName . '()');
        }

        $commandName = substr($methodName, 6);
        /** @var AbstractCommand|string $commandClass */
        $commandClass = '\Mougrim\Deployer\Command\\' . $commandName;
        $commandName  = lcfirst($commandName);
        if (!class_exists($commandClass)) {
            throw new RuntimeException("Command '{$commandName}' not exists");
        }
        $this->logger->info("NAME");
        $this->logger->info("\t{$commandName} - " . $commandClass::getInfo());
        $this->logger->info("");

        $description = $commandClass::getDescription();
        if ($description !== null) {
            $this->logger->info("DESCRIPTION");
            foreach (explode("\n", $description) as $descriptionLine) {
                $this->logger->info("\t{$descriptionLine}");
            }
            $this->logger->info("");
        }

        $this->logger->info("ACTIONS");
        foreach ($commandClass::getActionsIdList() as $actionId) {
            // todo action description
            $this->logger->info("\t{$actionId}" . ($actionId === 'index' ? ' [default]' : ''));
        }

        $commandRequestParamsInfo = $commandClass::getRequestParamsInfo();
        $actionId                 = $this->getRequestParam(0);
        if ($actionId === null && in_array('index', $commandClass::getActionsIdList(), true)) {
            $actionId = 'index';
        }
        if ($actionId !== null && isset($commandRequestParamsInfo[$actionId])) {
            if ($actionId === 'index') {
                $this->logger->info("OPTIONS for default action {$actionId}");
            } else {
                $this->logger->info("OPTIONS for action {$actionId}");
            }

            foreach ($commandRequestParamsInfo[$actionId] as $paramName => $paramInfo) {
                if (!is_numeric($paramName)) {
                    $paramName = "--{$paramName}";
                }
                $this->logger->info("\t{$paramName}");
                foreach (explode("\n", $paramInfo['info']) as $paramInfoLine) {
                    $this->logger->info("\t\t{$paramInfoLine}");
                }
                $this->logger->info("");
            }
        }
    }

    /**
     * @return array<string>
     */
    static public function getActionsIdList(): array
    {
        return array_merge(parent::getActionsIdList(), static::getAvailableCommands());
    }

    /**
     * @return array<string>
     */
    static private function getAvailableCommands(): array
    {
        $availableCommands = [];
        foreach (glob(__DIR__ . '/../Command/*') as $file) {
            $commandName         = basename($file, ".php");
            $availableCommands[] = lcfirst($commandName);
        }

        return $availableCommands;
    }
}
