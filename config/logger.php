<?php
/**
 * @author Mougrim <rinat@mougrim.ru>
 */
$logPath = __DIR__ . '/../logs/deployer.log';
return array(
    'policy'    => array(
        'ioError'            => 'trigger_warn',
        'configurationError' => 'trigger_warn'
    ),
    'renderer'  => array(
        'nullMessage' => '-',
    ),
    'layouts'   => array(
        'console' => array(
            'class'   => 'LoggerLayoutPattern',
            'pattern' => '{pid} [{date:Y-m-d H:i:s}] {global:_SERVER.USER} {logger}.{level} [{mdc}][{ndc}] {message} {ex}',
        ),
    ),
    'appenders' => array(
        'std_log'          => array(
            'class'  => 'LoggerAppenderStd',
            'layout' => 'console',
        ),
        'console_log'      => array(
            'class'  => 'LoggerAppenderStream',
            'layout' => 'console',
            'stream' => $logPath,
        ),
        'root_console_log' => array(
            'class'    => 'LoggerAppenderStream',
            'layout'   => 'console',
            'stream'   => $logPath,
            'minLevel' => Logger::getLevelByName('info'),
        ),
    ),
    'loggers'   => array(
        'help' => array(
            'appenders' => array('std_log'),
            'minLevel'  => Logger::getLevelByName('info'),
            'addictive' => false,
        ),
    ),
    'root'      => array('appenders' => array('std_log', 'root_console_log')),
);
