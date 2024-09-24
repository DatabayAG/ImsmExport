<?php

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use Psr\Http\Message\ServerRequestInterface;

class ilImsmExportConfigGUI extends ilPluginConfigGUI
{
    /** @var ilImsmExportPlugin  */
    private $plugin;

    /** @var ilImsmExportConfig  */
    private $config;

    /** @var ilLanguage  */
    private $lng;

    /** @var ilCtrl  */
    private $ctrl;

    /** @var ilGlobalTemplateInterface $tpl */
    private $tpl;

    /** @var Factory  */
    private $factory;

    /** @var Renderer  */
    private $renderer;

    /** @var ServerRequestInterface  */
    private $request;


    /**
     * Handles all commands, default is "configure"
     */
    public function performCommand($cmd)
    {
        global $DIC;

        /** @var ilImsmExportPlugin $plugin */
        $plugin = $this->getPluginObject();

        $this->plugin = $plugin;
        $this->config = $this->plugin->getConfig();
        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->request = $DIC->http()->request();

        switch ($cmd) {
            case "configure":
                $this->$cmd();
                break;

            default:
                $this->tpl->setContent('unknown command');
                $this->tpl->printToStdout();
        }
    }

    /**
     * Show configuration screen screen
     */
    protected function configure()
    {
        $fields = [
            'use_login' => $this->factory->input()->field()->checkbox(
                $this->plugin->txt('use_login')
            )->withValue($this->config->getUseLogin()),

            'use_fullname' => $this->factory->input()->field()->checkbox(
                $this->plugin->txt('use_fullname')
            )->withValue($this->config->getUseFullname()),

            'use_matriculation' => $this->factory->input()->field()->checkbox(
                $this->plugin->txt('use_matriculation')
            )->withValue($this->config->getUseMatriculation()),
        ];
        $sections = [
            'privacy' => $this->factory->input()->field()->section(
                $fields,
                $this->plugin->txt('privacy'),
                $this->plugin->txt('privacy_info'),
            )
        ];
        $form = $this->factory->input()->container()->form()->standard($this->ctrl->getFormAction($this), $sections);

        if ($this->request->getMethod() == "POST") {
            $form = $form->withRequest($this->request);
            $result = $form->getInputGroup()->getContent();

            if ($result->isOK()) {
                $data = $form->getData();

                $this->config->setUseLogin((bool) ($data['privacy']['use_login'] ?? false));
                $this->config->setUseFullname((bool) ($data['privacy']['use_fullname'] ?? false));
                $this->config->setUseMatriculation((bool) ($data['privacy']['use_matriculation'] ?? false));

                $this->tpl->setOnScreenMessage(
                    ilGlobalTemplate::MESSAGE_TYPE_SUCCESS,
                    $this->lng->txt("settings_saved")
                );
            } else {
                $this->tpl->setOnScreenMessage(
                    ilGlobalTemplate::MESSAGE_TYPE_FAILURE,
                    $this->lng->txt("validation_error")
                );
            }
        }

        $this->tpl->setContent($this->renderer->render($form));
    }
}
