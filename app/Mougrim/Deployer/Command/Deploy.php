<?php
namespace Mougrim\Deployer\Command;

use Mougrim\Deployer\Kernel\AbstractCommand;

/**
 * @package Mougrim\Deployer\Command
 * @author  Mougrim <rinat@mougrim.ru>
 */
class Deploy extends AbstractCommand
{
    static public function getActionsSubActions()
    {
        return [
            'index' => [
                'init',
                'deploy',
                'switch',
            ],
        ];
    }

    static public function getRawRequestParamsInfo()
    {
        return [
            'init' => [
                'application-path'     => [
                    'require' => true,
                    'info'    => 'destination path',
                ],
                'user'                 => [
                    'require' => true,
                    'info'    => 'linux user',
                ],
                'group'                => [
                    'require' => true,
                    'info'    => 'linux group',
                ],
            ],
            'deploy' => [
                'tag'                  => [
                    'require' => true,
                    'info'    => 'git tag',
                ],
                'application-path'     => [
                    'require' => true,
                    'info'    => 'destination path',
                ],
                'user'                 => [
                    'require' => true,
                    'info'    => 'linux user',
                ],
                'group'                => [
                    'require' => true,
                    'info'    => 'linux group',
                ],
                'before-deploy-script'    => [
                    'multiple' => true,
                    'info'     => 'Scripts, run after git checkout tag',
                ],
                'after-deploy-script' => [
                    'multiple' => true,
                    'info'     => 'Scripts, run after deploy to new tag',
                ],
            ],
            'switch' => [
                'tag'                  => [
                    'require' => true,
                    'info'    => 'git tag',
                ],
                'application-path'     => [
                    'require' => true,
                    'info'    => 'destination path',
                ],
                'user'                 => [
                    'require' => true,
                    'info'    => 'linux user',
                ],
                'group'                => [
                    'require' => true,
                    'info'    => 'linux group',
                ],
                'after-switch-script'  => [
                    'multiple' => true,
                    'info'     => 'Scripts, run after switch to new tag',
                ],
            ],
        ];
    }

    static public function getInfo()
    {
        return 'deploy your project';
    }

    protected function getVersionsPath($applicationPath)
    {
        return "{$applicationPath}/versions";
    }

    protected function getCurrentLink($versionsPath)
    {
        return "{$versionsPath}/current";
    }

    protected function getVersionPath($versionsPath, $tag)
    {
        return "{$versionsPath}/{$tag}";
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
        $this->actionInit();
        $this->actionDeploy();
        $this->actionSwitch();
    }

    public function actionInit()
    {
        $applicationPath = $this->getRequestParam('application-path');
        $user            = $this->getRequestParam('user');
        $group           = $this->getRequestParam('group');
        $versionsPath    = $this->getVersionsPath($applicationPath);
        $this->getLogger()->info("Init...");
        if (!file_exists($applicationPath)) {
            $this->getShellHelper()->sudo()->mkdir($applicationPath, true);
            $this->getShellHelper()->sudo()->mkdir($versionsPath);
            $this->getShellHelper()->sudo()->chown($user, $group, $applicationPath, true);
            $this->getLogger()->info("Init completed");
        } else {
            $this->getLogger()->info("Already inited");
        }
    }

    public function actionDeploy()
    {
        $applicationPath     = $this->getRequestParam('application-path');
        $user                = $this->getRequestParam('user');
        $tag                 = $this->getRequestParam('tag');
        $beforeDeployScripts = $this->getRequestParam('before-deploy-script');
        $afterDeployScripts  = $this->getRequestParam('after-deploy-script');
        $versionsPath        = $this->getVersionsPath($applicationPath);
        $currentLink         = $this->getCurrentLink($versionsPath);

        $this->getLogger()->info("Pre deploy");
        $this->getShellHelper()->runCommand("git status");
        $this->getShellHelper()->runCommand("git fetch origin -p");
        // try checkout that to make sure that tag exists
        $this->getShellHelper()->runCommand("git checkout " . escapeshellarg($tag));

        if ($beforeDeployScripts !== null) {
            $this->getLogger()->info("Run before-deploy scripts");
            foreach ($beforeDeployScripts as $beforeDeployScript) {
                $this->getShellHelper()->runCommand($beforeDeployScript);
            }
        }

        $versionPath = $this->getVersionPath($versionsPath, $tag);
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
                $this->getApplication()->end(0);
                return;
            }

            $this->getLogger()->info("Remove exists directory {$versionPath}");
            $this->getShellHelper()->sudo()->rm($versionPath, true);
        }
        $this->getShellHelper()->sudo($user)->mkdir($versionPath);

        $this->getShellHelper()->runCommand(
            'tar -c ./ --exclude=.git |\\
    sudo -u ' . escapeshellarg($user) . ' tar -x -C ' . escapeshellarg($versionPath)
        );

        if ($afterDeployScripts !== null) {
            $this->getLogger()->info("Run after deploy scripts");
            foreach ($afterDeployScripts as $afterDeployScript) {
                $afterDeployScript = strtr($afterDeployScript, ['{{ version_path }}' => $versionPath]);
                $this->getShellHelper()->runCommand($afterDeployScript);
            }
        }

        $this->getLogger()->info("Deploy complete");
    }

    public function actionSwitch()
    {
        $applicationPath     = $this->getRequestParam('application-path');
        $user                = $this->getRequestParam('user');
        $tag                 = $this->getRequestParam('tag');
        $afterSwitchScripts  = $this->getRequestParam('after-switch-script');
        $versionsPath        = $this->getVersionsPath($applicationPath);
        $currentLink         = $this->getCurrentLink($versionsPath);

        $applicationFiles = scandir($applicationPath);

        $versionPath = $this->getVersionPath($versionsPath, $tag);
        $versionFiles = scandir($versionPath);

        foreach ([&$applicationFiles, &$versionFiles] as &$files) {
            foreach ([".", "..", "versions"] as $excludeFile) {
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
                $afterSwitchScript = strtr($afterSwitchScript, ['{{ version_path }}' => $versionPath]);
                $this->getShellHelper()->runCommand($afterSwitchScript);
            }
        }

        $this->getLogger()->info("Successful complete");
    }
}
