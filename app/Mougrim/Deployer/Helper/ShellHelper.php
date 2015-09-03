<?php
namespace Mougrim\Deployer\Helper;

use Mougrim\Logger\Logger;

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
     * @return Logger
     */
    public function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = Logger::getLogger('shell-helper');
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
        $this->getLogger()->info("Run '{$command}'");

        $descriptorsSpec = [
            0 => ["pipe", "r"], // stdin is a pipe that the child will read from
            1 => ["pipe", "w"], // stdout is a pipe that the child will write to
            2 => ["pipe", "w"], // stderr is a pipe that the child will write to
        ];
        $resource = proc_open($command, $descriptorsSpec, $pipes);
        if (!is_resource($resource)) {
            throw new \RuntimeException("Can't create resource for command '{$command}'");
        }

        fclose($pipes[0]);
        $output = trim(stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        $error = trim(stream_get_contents($pipes[2]));
        fclose($pipes[2]);
        $result = proc_close($resource);

        if (!empty($output)) {
            $this->getLogger()->info("Output:\n" . $output);
        } else {
            $this->getLogger()->info("Empty output");
        }
        if (!empty($error)) {
            $this->getLogger()->info("Error output:\n" . $error);
        }
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

    public function checkIsWritable($path)
    {
        $path = escapeshellarg($path);
        $checkCommand = "if [ ! -w {$path} ]; then echo 'Path not writable'; exit 1; else echo 'Path writable'; fi";
        $this->runCommand('bash -c ' . escapeshellarg($checkCommand));
    }

    public function writeFile($path, $content)
    {
        $path = escapeshellarg($path);
        $content = escapeshellarg($content);
        $writeCommand = "echo {$content} > {$path}";
        $this->runCommand('bash -c ' . escapeshellarg($writeCommand));
    }
}
