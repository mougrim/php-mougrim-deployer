<?php
declare(strict_types=1);

namespace Mougrim\Deployer\Helper;

use Psr\Log\LoggerInterface;
use RuntimeException;
use function escapeshellarg;
use function fclose;
use function is_resource;
use function proc_close;
use function proc_open;
use function trim;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class ShellHelper
{
    private bool $sudo = false;
    private ?string $sudoUser = null;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function runCommand(string $command): void
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
        $this->logger->info("Run '{$command}'");

        $descriptorsSpec = [
            0 => ["pipe", "r"], // stdin is a pipe that the child will read from
            1 => ["pipe", "w"], // stdout is a pipe that the child will write to
            2 => ["pipe", "w"], // stderr is a pipe that the child will write to
        ];
        $resource        = proc_open($command, $descriptorsSpec, $pipes);
        if (!is_resource($resource)) {
            throw new RuntimeException("Can't create resource for command '{$command}'");
        }

        fclose($pipes[0]);
        $output = trim(stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        $error = trim(stream_get_contents($pipes[2]));
        fclose($pipes[2]);
        $result = proc_close($resource);

        if (!empty($output)) {
            $this->logger->info("Output:\n" . $output);
        } else {
            $this->logger->info("Empty output");
        }
        if (!empty($error)) {
            $this->logger->info("Error output:\n" . $error);
        }
        if ($result !== 0) {
            throw new RuntimeException("Command '{$command}' executed with error code: {$result}");
        }
    }

    public function sudo(string $user = 'root'): static
    {
        $this->sudo     = true;
        $this->sudoUser = $user;
        return $this;
    }

    public function mkdir(string $directory, bool $recursive = false): void
    {
        $this->runCommand('mkdir ' . ($recursive ? '-p ' : '') . escapeshellarg($directory));
    }

    public function chown(string $user, string $group, string $directory, bool $recursive = false): void
    {
        $this->runCommand(
            'chown ' . ($recursive ? '-R ' : '') .
            escapeshellarg("{$user}:{$group}") . ' ' .
            escapeshellarg($directory)
        );
    }

    public function rm(string $path, bool $recursive = false): void
    {
        $this->runCommand('rm -f' . ($recursive ? 'r' : '') . ' ' . escapeshellarg($path));
    }

    public function ln(string $link, string $destination): void
    {
        $this->runCommand('ln -sfT ' . escapeshellarg($destination) . ' ' . escapeshellarg($link));
    }

    public function checkIsWritable(string $path): void
    {
        $path         = escapeshellarg($path);
        $checkCommand = "if [ ! -w {$path} ]; then echo 'Path not writable'; exit 1; else echo 'Path writable'; fi";
        $this->runCommand('bash -c ' . escapeshellarg($checkCommand));
    }

    public function writeFile(string $path, string $content): void
    {
        $path         = escapeshellarg($path);
        $content      = escapeshellarg($content);
        $writeCommand = "echo {$content} > {$path}";
        $this->runCommand('bash -c ' . escapeshellarg($writeCommand));
    }
}
