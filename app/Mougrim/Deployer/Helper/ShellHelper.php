<?php
namespace Mougrim\Deployer\Helper;

/**
 * @package Mougrim\Deployer\Helper
 * @author  Mougrim <rinat@mougrim.ru>
 */
class ShellHelper
{
    private $logger;
    private $sudo = false;
    private $sudoUser;

    /**
     * @return \Logger
     */
    public function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = \Logger::getLogger('shell-helper');
        }

        return $this->logger;
    }

    public function runCommand($command)
    {
        if ($this->sudo) {
            $commandPrefix = 'sudo ';
            if ($this->sudoUser !== 'root') {
                $commandPrefix .= '-u ' . escapeshellarg($this->sudoUser) . ' ';
            }

            $command = $commandPrefix . $command;

            $this->sudo     = false;
            $this->sudoUser = null;
        }
        $this->getLogger()->info("$ {$command}");
        system($command, $result);
        $this->getLogger()->info("$");
        if ($result !== 0) {
            throw new \RuntimeException("Command '{$command}' executed with error code: {$result}");
        }
    }

    public function sudo($user = 'root')
    {
        $this->sudo     = true;
        $this->sudoUser = $user;
        return $this;
    }

    public function mkdir($directory, $recursive = false)
    {
        $this->runCommand('mkdir ' . ($recursive ? '-p ' : '') . escapeshellarg($directory));
    }

    public function chown($user, $group, $directory, $recursive = false)
    {
        $this->runCommand(
            'chown ' . ($recursive ? '-R ' : '') .
            escapeshellarg("{$user}:{$group}") . ' ' .
            escapeshellarg($directory)
        );
    }

    public function rm($path, $recursive = false)
    {
        $this->runCommand('rm -f' . ($recursive ? 'r' : '') . ' ' . escapeshellarg($path));
    }

    public function ln($link, $destination)
    {
        $this->runCommand('ln -sfT ' . escapeshellarg($destination) . ' ' . escapeshellarg($link));
    }
}
