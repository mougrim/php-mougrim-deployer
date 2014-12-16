<?php
namespace Mougrim\Deployer\Command;

use Mougrim\Deployer\Kernel\AbstractCommand;

/**
 * @package Mougrim\Deployer\Command
 * @author  Mougrim <rinat@mougrim.ru>
 */
class Deploy extends AbstractCommand
{
    static public function getRequestParamsInfo()
    {
        return array(
            'index' => array(
                'tag'                  => array(
                    'require' => true,
                    'info'    => 'git tag',
                ),
                'application-path'     => array(
                    'require' => true,
                    'info'    => 'destination path',
                ),
                'user'                 => array(
                    'require' => true,
                    'info'    => 'linux user',
                ),
                'group'                => array(
                    'require' => true,
                    'info'    => 'linux group',
                ),
                'init-script'          => array(
                    'multiple' => true,
                    'info'     => 'Scripts, run after git checkout tag',
                ),
                'before-switch-script' => array(
                    'multiple' => true,
                    'info'     => 'Scripts, run before switch to new tag',
                ),
                'after-switch-script'  => array(
                    'multiple' => true,
                    'info'     => 'Scripts, run after switch to new tag',
                ),
            ),
        );
    }

    static public function getInfo()
    {
        return 'deploy your project';
    }

    /**
     * 1. Инициализация (создание дирректорий, симлинок)
     * 2. git fetch origin -p
     * 3. git checkout tag
     * 4. sync
     * 5. Change symlink
     * 6. nginx reload
     */
    public function actionIndex()
    {
        $applicationPath     = $this->getRequestParam('application-path');
        $user                = $this->getRequestParam('user');
        $group               = $this->getRequestParam('group');
        $tag                 = $this->getRequestParam('tag');
        $initScripts         = $this->getRequestParam('init-script');
        $beforeSwitchScripts = $this->getRequestParam('before-switch-script');
        $afterSwitchScripts  = $this->getRequestParam('after-switch-script');
        $versionsPath        = "{$applicationPath}/versions";
        $currentLink         = "{$versionsPath}/current";

        if (!file_exists($applicationPath)) {
            $this->getLogger()->info("Init");
            $this->getShellHelper()->sudo()->mkdir($applicationPath, true);
            $this->getShellHelper()->sudo()->mkdir($versionsPath);
            $this->getShellHelper()->sudo()->chown($user, $group, $applicationPath, true);
        }

        $this->getLogger()->info("Deploy");
        $this->getShellHelper()->runCommand("git status");
        $this->getShellHelper()->runCommand("git fetch origin -p");
        // try checkout that to make sure that tag exists
        $this->getShellHelper()->runCommand("git checkout " . escapeshellarg($tag));

        if ($initScripts !== null) {
            $this->getLogger()->info("Run init scripts");
            foreach ($initScripts as $initScript) {
                $this->getShellHelper()->runCommand($initScript);
            }
        }

        $versionPath = "{$versionsPath}/{$tag}";
        if (file_exists($versionPath)) {
            if (realpath($currentLink) === $versionPath) {
                $this->getLogger()->info(
                    "[WARNING] Current link is point to {$versionPath}, are you sure to remove it? (yes/no) [no]"
                );
            } else {
                $this->getLogger()->info("Directory '{$versionPath}' already exists, remove it? (yes/no) [no]");
            }
            $input = trim(fgets(STDIN));
            if ($input !== "yes") {
                $this->getLogger()->info("Cancel");
                return;
            }

            $this->getLogger()->info("Remove exists directory {$versionPath}");
            $this->getShellHelper()->rm($versionPath, true);
        }
        $this->getShellHelper()->sudo($user)->mkdir($versionPath);

        $this->getShellHelper()->runCommand(
            'tar -c ./ --exclude=.git |\\
	sudo -u ' . escapeshellarg($user) . ' tar -x -C ' . escapeshellarg($versionPath)
        );

        if ($beforeSwitchScripts !== null) {
            $this->getLogger()->info("Run pre switch scripts");
            foreach ($beforeSwitchScripts as $beforeSwitchScript) {
                $beforeSwitchScript = strtr($beforeSwitchScript, array('{{ version_path }}' => $versionPath));
                $this->getShellHelper()->runCommand($beforeSwitchScript);
            }
        }

        $applicationFiles = scandir($applicationPath);

        $versionFiles = scandir($versionPath);

        foreach ([&$applicationFiles, &$versionFiles] as &$files) {
            foreach (array_merge([".", "..", "versions"]) as $excludeFile) {
                $key = array_search($excludeFile, $files);
                if ($key !== false) {
                    unset($files[$key]);
                }
            }
        }

        $this->getLogger()->info("Create new links");
        $linksCreated = false;
        foreach ($versionFiles as $versionFile) {
            if (!in_array($versionFile, $applicationFiles)) {
                $this->getShellHelper()->sudo($user)->ln(
                    "{$applicationPath}/{$versionFile}",
                    "$currentLink/{$versionFile}"
                );
                $linksCreated = true;
            }
        }

        if ($linksCreated === false) {
            $this->getLogger()->info("No new links found");
        }


        $this->getLogger()->info("Switch version");
        $this->getShellHelper()->sudo($user)->ln(
            $currentLink,
            $versionPath
        );

        $this->getLogger()->info("Remove old links");
        $linksRemoved = false;
        foreach ($applicationFiles as $applicationFile) {
            if (!in_array($applicationFile, $versionFiles)) {
                $linksRemoved = true;
                $this->getShellHelper()->sudo($user)->rm("{$applicationPath}/{$applicationFile}");
            }
        }

        if ($linksRemoved === false) {
            $this->getLogger()->info("No links to remove found");
        }

        if ($afterSwitchScripts !== null) {
            $this->getLogger()->info("Run after switch scripts");
            foreach ($afterSwitchScripts as $afterSwitchScript) {
                $afterSwitchScript = strtr($afterSwitchScript, array('{{ version_path }}' => $versionPath));
                $this->getShellHelper()->runCommand($afterSwitchScript);
            }
        }

        $this->getLogger()->info("Successful complete");
    }
}
