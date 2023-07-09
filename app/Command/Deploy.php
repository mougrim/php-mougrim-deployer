<?php
declare(strict_types=1);

namespace Mougrim\Deployer\Command;

use Mougrim\Deployer\Helper\TemplateHelper;
use Mougrim\Deployer\Kernel\AbstractCommand;
use function escapeshellarg;
use function fgets;
use function file_exists;
use function in_array;
use function realpath;
use function scandir;
use function strtr;
use function trim;
use const STDIN;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class Deploy extends AbstractCommand
{
    static public function getActionsSubActions(): array
    {
        return [
            'index' => [
                'init',
                'deploy',
                'switch',
            ],
            'dev'   => [
                'init',
                'deploy',
                'switch',
            ],
        ];
    }

    static public function getRawRequestParamsInfo(): array
    {
        return [
            'init'   => [
                'application-path' => [
                    'require' => true,
                    'info'    => 'destination path',
                ],
                'user'             => [
                    'require' => true,
                    'info'    => 'linux user',
                ],
                'group'            => [
                    'require' => true,
                    'info'    => 'linux group',
                ],
            ],
            'deploy' => [
                'tag'                  => [
                    'require' => true,
                    'info'    => 'git tag',
                ],
                'skip-git'             => [
                    'info'    => 'skip git checkout tag, and other work with git for debug or dev deploy',
                    'default' => false,
                ],
                'skip-deploy-files'    => [
                    'info'    => 'skip deploy files to version dir for debug or dev deploy',
                    'default' => false,
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
                'before-deploy-script' => [
                    'multiple' => true,
                    'info'     => 'Scripts, run after git checkout tag, not template',
                ],
                'after-deploy-script'  => [
                    'multiple' => true,
                    'info'     => 'Scripts, run after deploy to new tag, as template',
                ],
                'template-files'       => [
                    'multiple' => true,
                    'info'     => implode(
                        "\n",
                        [
                            'Files, who needs process as templates.',
                            'Available variables, passed to template:',
                            "\t- parameters from config;",
                            "\t- parameters from request;",
                            "\t- 'versions-path' - versions dir path;",
                            "\t- 'version-path' - new version dir path;",
                            "\t- 'current-link-path' - current link path.",
                            "If we have param variable=value, and template 'Text {{ variable }} other text',",
                            "then template converted to 'Text value other text'.",
                            "You can use nested parameters, for example {{ template-parameters.nested-variable }}",
                            "Template path is also template, for example version-path=/project/versions/v1.0.0",
                            "and template path '{{ version-path }}/bin/script.sh' will replaces to '/project/versions/v1.0.0/bin/script.sh'",
                        ]
                    ),
                ],
                'template-parameters'  => [
                    'multiple' => true,
                    'info'     => "Custom template parameters, passed to templates - files in 'template-files' and commands names",
                ],
            ],
            'switch' => [
                'tag'                 => [
                    'require' => true,
                    'info'    => 'git tag',
                ],
                'application-path'    => [
                    'require' => true,
                    'info'    => 'destination path',
                ],
                'user'                => [
                    'require' => true,
                    'info'    => 'linux user',
                ],
                'group'               => [
                    'require' => true,
                    'info'    => 'linux group',
                ],
                'after-switch-script' => [
                    'multiple' => true,
                    'info'     => 'Scripts, run after switch to new tag, as template',
                ],
                'template-parameters' => [
                    'multiple' => true,
                    'info'     => "Parameters, passed to templates - files in 'template-files' and commands names",
                ],
            ],
            'dev'    => [
                'tag' => [
                    'require' => false,
                    'info'    => 'git tag',
                    'default' => 'dev',
                ],
            ],
        ];
    }

    static public function getInfo(): string
    {
        return 'deploy your project';
    }

    protected function getAdditionalParams(): array
    {
        $applicationPath                         = (string) $this->getRequestParam('application-path');
        $actionAdditionalParams                  = [];
        $actionAdditionalParams['versions-path'] = "{$applicationPath}/versions";
        if ($this->requestParamExists('tag')) {
            $tag                                    = (string) $this->getRequestParam('tag');
            $actionAdditionalParams['version-path'] = "{$actionAdditionalParams['versions-path']}/{$tag}";
        }
        $actionAdditionalParams['current-link-path'] = "{$actionAdditionalParams['versions-path']}/current";

        $additionalParams = [];
        foreach (static::getActionsIdList() as $actionId) {
            $additionalParams[$actionId] = $actionAdditionalParams;
        }
        return $additionalParams;
    }

    private TemplateHelper $templateHelper;

    private function getTemplateHelper(): TemplateHelper
    {
        if (!isset($this->templateHelper)) {
            $this->templateHelper = new TemplateHelper(
                shellHelper: $this->shellHelper,
                logger: $this->logger,
            );
        }

        return $this->templateHelper;
    }

    public function runTemplateCommand($command): void
    {
        $command = $this->getTemplateHelper()->processTemplateString($command, $this->getParams());
        $this->shellHelper->runCommand($command);
    }

    /**
     * 1. Initialization (create dirs, symlinks)
     * 2. git fetch origin -p
     * 3. git checkout tag
     * 4. sync
     * 5. Change symlink
     * 6. nginx reload
     */
    public function actionIndex(): void
    {
        $this->actionInit();
        $this->actionDeploy();
        $this->actionSwitch();
    }

    public function actionInit(): void
    {
        $applicationPath = $this->getParam('application-path');
        $user            = $this->getParam('user');
        $group           = $this->getParam('group');
        $versionsPath    = $this->getParam('versions-path');
        $this->logger->info('Init...');
        if (!file_exists($applicationPath)) {
            $this->shellHelper->sudo()->mkdir($applicationPath, true);
            $this->shellHelper->sudo()->mkdir($versionsPath);
            $this->shellHelper->sudo()->chown($user, $group, $applicationPath, true);
            $this->logger->info('Init completed');
        } else {
            $this->logger->info('Already init');
        }
    }

    public function actionDeploy(bool $isSkipGit = false): void
    {
        $user                = (string) $this->getParam('user');
        $tag                 = (string) $this->getParam('tag');
        $beforeDeployScripts = $this->getParam('before-deploy-script');
        $afterDeployScripts  = $this->getParam('after-deploy-script');
        $isSkipGit           = $this->getParam('skip-git') || $isSkipGit;
        $isSkipDeployFiles   = (boolean) $this->getParam('skip-deploy-files');
        $currentLink         = $this->getParam('current-link-path');
        $versionPath         = (string) $this->getParam('version-path');
        $templateFiles       = $this->getParam('template-files');

        $this->logger->info("Before deploy");
        if (!$isSkipGit) {
            $this->shellHelper->runCommand("git status");
            $this->shellHelper->runCommand("git fetch origin -p");
            // checkout tag for run actual scripts
            $this->shellHelper->runCommand("git checkout " . escapeshellarg($tag));
        }

        if ($beforeDeployScripts !== null) {
            $this->logger->info("Run before deploy scripts");
            foreach ($beforeDeployScripts as $beforeDeployScript) {
                $this->runTemplateCommand($beforeDeployScript);
            }
        }

        if (!$isSkipDeployFiles) {
            if (file_exists($versionPath)) {
                if (realpath($currentLink) === $versionPath) {
                    $this->logger->warning(
                        "Current link is point to {$versionPath}, are you sure to remove it? (yes/no) [no]"
                    );
                } else {
                    $this->logger->info("Directory '{$versionPath}' already exists, remove it? (yes/no) [no]");
                }
                $input = trim(fgets(STDIN));
                if ($input !== "yes") {
                    $this->logger->info("Cancel");
                    $this->application->end();
                }

                $this->logger->info("Remove exists directory {$versionPath}");
                $this->shellHelper->sudo()->rm($versionPath, true);
            }
            $this->shellHelper->sudo($user)->mkdir($versionPath);

            $this->shellHelper->runCommand(
                'tar -c --exclude=.git ./ |\\
    sudo -u ' . escapeshellarg($user) . ' tar -x -C ' . escapeshellarg($versionPath)
            );
        }

        if ($templateFiles !== null) {
            $this->logger->info("Process template files");
            foreach ($templateFiles as $templateFile) {
                $this->logger->info("Process template file {$templateFile}");
                $templateFile = $this->getTemplateHelper()->processTemplateString($templateFile, $this->getParams());
                $this->logger->info("Real template file path {$templateFile}");
                $this->getTemplateHelper()->processTemplateToFile($user, $templateFile, $this->getParams());
            }
        }

        if ($afterDeployScripts !== null) {
            $this->logger->info("Run after deploy scripts");
            foreach ($afterDeployScripts as $afterDeployScript) {
                $afterDeployScript = strtr($afterDeployScript, ['{{ version_path }}' => $versionPath]);
                $this->runTemplateCommand($afterDeployScript);
            }
        }

        $this->logger->info("Deploy complete");
    }

    public function actionSwitch(): void
    {
        $applicationPath    = (string) $this->getParam('application-path');
        $user               = (string) $this->getParam('user');
        $afterSwitchScripts = $this->getParam('after-switch-script');
        $currentLink        = (string) $this->getParam('current-link-path');
        $versionPath        = (string) $this->getParam('version-path');

        $applicationFiles = scandir($applicationPath);

        $versionFiles = scandir($versionPath);

        foreach ([&$applicationFiles, &$versionFiles] as &$files) {
            foreach ([".", "..", "versions"] as $excludeFile) {
                $key = array_search($excludeFile, $files);
                if ($key !== false) {
                    unset($files[$key]);
                }
            }
        }

        $this->logger->info("Create new links");
        $linksCreated = false;
        foreach ($versionFiles as $versionFile) {
            if (!in_array($versionFile, $applicationFiles)) {
                $this->shellHelper->sudo($user)->ln(
                    "{$applicationPath}/{$versionFile}",
                    "$currentLink/{$versionFile}"
                );
                $linksCreated = true;
            }
        }

        if ($linksCreated === false) {
            $this->logger->info("No new links found");
        }


        $this->logger->info("Switch version");
        $this->shellHelper->sudo($user)->ln(
            $currentLink,
            $versionPath
        );

        $this->logger->info("Remove old links");
        $linksRemoved = false;
        foreach ($applicationFiles as $applicationFile) {
            if (!in_array($applicationFile, $versionFiles)) {
                $linksRemoved = true;
                $this->shellHelper->sudo($user)->rm("{$applicationPath}/{$applicationFile}");
            }
        }

        if ($linksRemoved === false) {
            $this->logger->info("No links to remove found");
        }

        if ($afterSwitchScripts !== null) {
            $this->logger->info("Run after switch scripts");
            foreach ($afterSwitchScripts as $afterSwitchScript) {
                $afterSwitchScript = strtr($afterSwitchScript, ['{{ version_path }}' => $versionPath]);
                $this->runTemplateCommand($afterSwitchScript);
            }
        }

        $this->logger->info("Successful complete");
    }

    public function actionDev(): void
    {
        $this->actionInit();
        $this->actionDeploy(true);
        $this->actionSwitch();
    }
}
