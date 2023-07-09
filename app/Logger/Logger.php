<?php
declare(strict_types=1);

namespace Mougrim\Deployer\Logger;

use JsonSerializable;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ReflectionClass;
use Stringable;
use function array_search;
use function array_values;
use function date;
use function file_put_contents;
use function posix_getpid;
use function var_export;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class Logger implements LoggerInterface
{
    private array $logLevels;
    private int $minLevelIndex;

    public function __construct(
        private readonly string $logFilePath,
        private readonly string $minLevel = LogLevel::INFO,
    ) {
        $reflectionClass     = new ReflectionClass(LogLevel::class);
        $this->logLevels     = array_reverse(array_values($reflectionClass->getConstants()));
        $this->minLevelIndex = array_search($this->minLevel, $this->logLevels) ?: 0;
    }

    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $index = array_search($level, $this->logLevels, true);
        if ($index !== false && $index < $this->minLevelIndex) {
            return;
        }

        $pid  = posix_getpid();
        $date = date('Y-m-d H:i:s');
        $user = $_SERVER['USER'] ?? '';
        foreach ($context as $key => $value) {
            if ($value instanceof JsonSerializable) {
                $context[$key] = $value->jsonSerialize();
                continue;
            }
            if ($value instanceof Stringable) {
                $context[$key] = $value->__toString();
                continue;
            }
        }
        $contextString = var_export($context, true);
        $fullMessage   = "{$pid} [{$date}] {$user} {$level} {$message} {$contextString}\n";

        file_put_contents($this->logFilePath, $fullMessage);
        echo $fullMessage;
    }
}
