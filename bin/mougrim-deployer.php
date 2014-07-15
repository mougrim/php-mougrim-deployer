#!/usr/bin/env php
<?php
/**
 * @author Mougrim <rinat@mougrim.ru>
 */
array_shift($argv);
$config = array();

foreach($argv as $param) {
	if(preg_match('/^--([\w-]+?)=(.*)$/', $param, $matches)) {
		$config[$matches[1]] = $matches[2];
	}
}

function runShell($command) {
	logInfo("$ {$command}");
	system($command, $result);
	logInfo("$");
	if($result !== 0) {
		throw new RuntimeException("Command '{$command}' executed with error code: {$result}");
	}
}

function logInfo($message) {
	$current = new DateTime();
	echo "[{$current->format("Y-m-d H:i:s")}] {$message}\n";
}

/**
 * 1. Инициализация (создание дирректорий, симлинок)
 * 2. git fetch origin -p
 * 3. git checkout tag
 * 4. rsync
 * 5. Change symlinc
 * 6. nginx reload
 */

$requiredArguments = ['tag', 'application-path', 'user', 'group'];

foreach($requiredArguments as $argument) {
	if(!array_key_exists($argument, $config)) {
		throw new RuntimeException("Argument '{$argument}' is required");
	}
}

$versionsPath = "{$config['application-path']}/versions";
$currentLink = "{$versionsPath}/current";

if(!file_exists($config['application-path'])) {
	logInfo("Init");
	runShell("sudo mkdir -p " . escapeshellarg($config['application-path']));
	runShell("sudo mkdir " . escapeshellarg($versionsPath));
	runShell(
		"sudo chown -R " .
			escapeshellarg("{$config['user']}:{$config['group']}") . " " .
			escapeshellarg($config['application-path'])
	);
}

logInfo("Deploy");
runShell("git status");
runShell("git fetch origin -p");
// try checkout that to make sure that tag exists
runShell("git checkout " . escapeshellarg($config['tag']));
$versionPath = "{$versionsPath}/{$config['tag']}";
if(file_exists($versionPath)) {
	if(realpath($currentLink) === $versionPath) {
		logInfo("[WARNING] Current link is point to {$versionPath}, are you sure to remove it? (yes/no) [no]");
	} else {
		logInfo("Directory '{$versionPath}' already exists, remove it? (yes/no) [no]");
	}
	$input = trim(fgets(STDIN));
	if($input !== "yes") {
		logInfo("Cancel");
		return;
	}

	logInfo("Remove exists directory {$versionPath}");
	runShell("rm -rf " . escapeshellarg($versionPath));
}
runShell("sudo -u " . escapeshellarg($config['user']) . " mkdir " . escapeshellarg($versionPath));

runShell(
	"git archive " . escapeshellarg($config['tag']) . " | " .
		"sudo -u " . escapeshellarg($config['user']) . " tar -x -C " . escapeshellarg($versionPath)
);

if(array_key_exists('pre-switch-script', $config)) {
	logInfo("Run pre switch script");
	$preSwitchScript = $versionPath . "/" . $config['pre-switch-script'];
	if(!is_file($preSwitchScript)) {
		throw new RuntimeException("Pre switch script '{$preSwitchScript}' not found");
	}
	if(!is_executable($preSwitchScript)) {
		runShell("sudo chmod +x " . escapeshellarg($preSwitchScript));
	}
	runShell(escapeshellcmd($preSwitchScript));
}

$applicationFiles = scandir($config['application-path']);

$versionFiles = scandir($versionPath);

foreach([&$applicationFiles, &$versionFiles] as &$files) {
	foreach(array_merge([".", "..", "versions"]) as $excludeFile) {
		$key = array_search($excludeFile, $files);
		if($key !== false) {
			unset($files[$key]);
		}
	}
}

logInfo("Create new links");
$linksCreated = false;
foreach($versionFiles as $versionFile) {
	if(!in_array($versionFile, $applicationFiles)) {
		runShell(
			"ln -sfT " . escapeshellarg("$currentLink/{$versionFile}") .
			" " . escapeshellarg("{$config['application-path']}/{$versionFile}")
		);
		$linksCreated = true;
	}
}

if($linksCreated === false) {
	logInfo("No new links found");
}


logInfo("Switch version");
runShell(
	"sudo -u " . escapeshellarg($config['user']) .
		" ln -sfT " . escapeshellarg($versionPath) . " " . escapeshellarg($currentLink)
);

logInfo("Remove old links");
$linksRemoved = false;
foreach($applicationFiles as $applicationFile) {
	if(!in_array($applicationFile, $versionFiles)) {
		$linksRemoved = true;
		runShell("rm " . escapeshellarg("{$config['application-path']}/{$applicationFile}"));
	}
}

if($linksRemoved === false) {
	logInfo("No links to remove found");
}

if(array_key_exists('post-switch-script', $config)) {
	logInfo("Run post switch script");
	$postSwitchScript = $versionPath . "/" . $config['post-switch-script'];
	if(!is_file($postSwitchScript)) {
		throw new RuntimeException("Post switch script '{$postSwitchScript}' not found");
	}
	if(!is_executable($postSwitchScript)) {
		runShell("sudo chmod +x " . escapeshellarg($postSwitchScript));
	}
	runShell(escapeshellcmd($postSwitchScript));
}

logInfo("Successful complete");
