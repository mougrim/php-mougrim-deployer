<?php
namespace Mougrim\Deployer\Command;

use Mougrim\Deployer\Kernel\AbstractCommand;

/**
 * @package Mougrim\Deployer\Command
 * @author  Mougrim <rinat@mougrim.ru>
 */
class Help extends AbstractCommand
{
    static public function getInfo()
    {
        return 'this command';
    }

    static public function getRequestParamsInfo()
    {
        return array(
            'defaultParams' => array(
                0 => array(
                    'info' => 'action name',
                ),
            ),
        );
    }

    protected $loggerName = 'help';

    public function actionIndex()
    {
        $this->getLogger()->info("Usage: mougrim-deployer.php <command> [<action>] [<args>]");
        $this->getLogger()->info("Commands list:");
        foreach (static::getAvailableCommands() as $commandName) {
            /** @var AbstractCommand $commandClass */
            $commandClass = '\Mougrim\Deployer\Command\\' . ucfirst($commandName);
            $this->getLogger()->info("\t" . $commandName . "\t" . $commandClass::getInfo());
        }
    }

    public function __call($methodName, array $params = array())
    {
        if (substr($methodName, 0, 6) !== 'action') {
            throw new \RuntimeException('Call to undefined method ' . get_class() . '::' . $methodName . '()');
        }

        $commandName = substr($methodName, 6);
        /** @var AbstractCommand $commandClass */
        $commandClass = '\Mougrim\Deployer\Command\\' . $commandName;
        $commandName  = lcfirst($commandName);
        if (!class_exists($commandClass)) {
            throw new \RuntimeException("Command '{$commandName}' not exists");
        }
        $this->getLogger()->info("NAME");
        $this->getLogger()->info("\t{$commandName} - " . $commandClass::getInfo());
        $this->getLogger()->info("");

        if ($commandClass::getDescription() !== null) {
            $this->getLogger()->info("DESCRIPTION");
            foreach (explode("\n", $commandClass::getDescription()) as $descriptionLine) {
                $this->getLogger()->info("\t{$descriptionLine}");
            }
            $this->getLogger()->info("");
        }

        $this->getLogger()->info("ACTIONS");
        foreach ($commandClass::getActionsIdList() as $actionId) {
            // todo action description
            $this->getLogger()->info("\t{$actionId}" . ($actionId === 'index' ? ' [default]' : ''));
        }

        $commandRequestParamsInfo = $commandClass::getRequestParamsInfo();
        $actionId                 = $this->getRequestParam(0);
        if ($actionId === null && in_array('index', $commandClass::getActionsIdList(), true)) {
            $actionId = 'index';
        }
        if ($actionId !== null && isset($commandRequestParamsInfo[$actionId])) {
            if ($actionId === 'index') {
                $this->getLogger()->info("OPTIONS for default action {$actionId}");
            } else {
                $this->getLogger()->info("OPTIONS for action {$actionId}");
            }

            foreach ($commandRequestParamsInfo[$actionId] as $paramName => $paramInfo) {
                $this->getLogger()->info("\t{$paramName}");
                $this->getLogger()->info("\t\t{$paramInfo['info']}");
                $this->getLogger()->info("");
            }
        }
    }

    static public function getActionsIdList()
    {
        return array_merge(parent::getActionsIdList(), static::getAvailableCommands());
    }

    static private function getAvailableCommands()
    {
        $availableCommands = array();
        foreach (glob(__DIR__ . '/../Command/*') as $file) {
            $commandName         = basename($file, ".php");
            $availableCommands[] = lcfirst($commandName);
        }

        return $availableCommands;
    }
}
