<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 ********************************************************************
 */


/**
 * @ilCtrl_isCalledBy ilCronStatusMonitorConfigGUI: ilObjComponentSettingsGUI
 *
 * Class ilCronStatusMonitorConfigGUI
 * @author Thomas Famula <famula@leifos.de>
 */
class ilCronStatusMonitorConfigGUI extends ilPluginConfigGUI
{
    protected ilGlobalTemplateInterface $tpl;
    protected ilCtrl $ctrl;
    protected ilLanguage $lng;

    public function __construct()
    {
        global $DIC;
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
    }

    /**
     * @param string $cmd
     *
     * Handles all commands, default is "configure"
     */
    public function performCommand(string $cmd) : void
    {
        switch ($cmd) {
            default:
                $this->$cmd();
                break;
        }
    }

    /**
     * Show settings screen
     */
    public function configure(?ilPropertyFormGUI $form = null) : void
    {
        global $tpl;
        if (!$form instanceof ilPropertyFormGUI) {
            $form = $this->initConfigurationForm();
        }
        $tpl->setContent($form->getHTML());
    }

    public function initConfigurationForm() : ilPropertyFormGUI
    {
        //create the form
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->getPluginObject()->txt("gui_title"));

        //add button
        $form->addCommandButton("save", $this->lng->txt("save"));

        //text input
        $setting = new ilCronStatusMonitorSettings();
        $text = new ilTextInputGUI($this->getPluginObject()->txt("email_recipient"), "email_recipient");
        $text->setValue($setting->get("email_recipient"));
        $text->setInfo($this->getPluginObject()->txt("email_recipient_info"));
        $text->setRequired(true);
        $form->addItem($text);

        return $form;
    }

    public function save() : void
    {
        $form = $this->initConfigurationForm();
        if ($form->checkInput()) {
            $setting = new ilCronStatusMonitorSettings();
            $setting->setList($form->getInput("email_recipient"));
            $this->tpl->setOnScreenMessage(
                ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS,
                $this->lng->txt("settings_saved"),
                true
            );
            $this->ctrl->redirect($this, "configure");
        }
        $this->configure($form);
    }
}
