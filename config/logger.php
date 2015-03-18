<?php
/**
 * @author Mougrim <rinat@mougrim.ru>
 */
$logPath = __DIR__ . '/../logs/deployer.log';
return [
    'policy'    => [
        'ioError'            => 'trigger_warn',
        'configurationError' => 'trigger_warn'
    ],
    'renderer'  => [
        'nullMessage' => '-',
    ],
    'layouts'   => [
        'console' => [
            'class'   => 'LoggerLayoutPattern',
            'pattern' => '{pid} [{date:Y-m-d H:i:s}] {global:_SERVER.USER} {logger}.{level} [{mdc}][{ndc}] {message} {ex}',
        ],
    ],
    'appenders' => [
        'std_log'          => [
            'class'  => 'LoggerAppenderStd',
            'layout' => 'console',
        ],
        'console_log'      => [
            'class'  => 'LoggerAppenderStream',
            'layout' => 'console',
            'stream' => $logPath,
        ],
        'root_console_log' => [
            'class'    => 'LoggerAppenderStream',
            'layout'   => 'console',
            'stream'   => $logPath,
            'minLevel' => Logger::getLevelByName('info'),
        ],
    ],
    'loggers'   => [
        'help' => [
            'appenders' => ['std_log'],
            'minLevel'  => Logger::getLevelByName('info'),
            'addictive' => false,
        ],
    ],
    'root'      => ['appenders' => ['std_log', 'root_console_log']],
];
