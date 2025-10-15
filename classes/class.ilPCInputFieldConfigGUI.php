<?php

/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation,
 * Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

include_once("./Services/Component/classes/class.ilPluginConfigGUI.php");

/**
 * @ilCtrl_isCalledBy ilPCInputFieldConfigGUI: ilObjComponentSettingsGUI
 *
 * Page Component Input Field plugin configuration GUI
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */
class ilPCInputFieldConfigGUI extends ilPluginConfigGUI
{
    /**
     * Handles all commmands, default is "configure"
     */
    function performCommand(string $cmd): void
    {
        switch ($cmd) {
            case "configure":
            case "save":
                $this->$cmd();
                break;
        }
    }

    /**
     * Configure screen
     */
    function configure(): void
    {
        global $tpl;

        $form = $this->initConfigurationForm();
        $tpl->setContent($form->getHTML());
    }

    /**
     * Initialize configuration form.
     */
    public function initConfigurationForm(): ilPropertyFormGUI
    {
        global $lng, $ilCtrl;

        include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
        $form = new ilPropertyFormGUI();

        // Globale KI-Aktivierung
        $ai_enabled = new ilCheckboxInputGUI($this->plugin_object->txt("ai_enabled"), "ai_enabled");
        $ai_enabled->setInfo($this->plugin_object->txt("ai_enabled_info"));
        $ai_enabled->setChecked($this->plugin_object->getSetting('ai_enabled', '0') === '1');
        $form->addItem($ai_enabled);

        // OpenAI API Key
        $api_key = new ilTextInputGUI($this->plugin_object->txt("openai_api_key"), "openai_api_key");
        $api_key->setInfo($this->plugin_object->txt("openai_api_key_info"));
        $api_key->setValue($this->plugin_object->getSetting('openai_api_key', ''));
        $api_key->setSize(50);
        $form->addItem($api_key);

        // KI-Modell Auswahl
        $ai_model = new ilTextInputGUI($this->plugin_object->txt("ai_model"), "ai_model");
        $ai_model->setInfo($this->plugin_object->txt("ai_model_info") ?? "z.B. qwen2.5-14b-instruct, gpt-4, etc.");
        $ai_model->setSize(30);
        $ai_model->setMaxLength(100);
        $ai_model->setValue($this->plugin_object->getSetting('ai_model', 'qwen2.5-14b-instruct'));
        $form->addItem($ai_model);

        // Standard-Prompt für KI-Bewertung
        $ai_prompt = new ilTextAreaInputGUI($this->plugin_object->txt("ai_prompt"), "ai_prompt");
        $ai_prompt->setInfo($this->plugin_object->txt("ai_prompt_info"));
        $ai_prompt->setValue($this->plugin_object->getSetting('ai_prompt', $this->getDefaultPrompt()));
        $ai_prompt->setRows(10);
        $ai_prompt->setCols(80);
        $form->addItem($ai_prompt);

        // Max. Anzahl Tokens
        $max_tokens = new ilNumberInputGUI($this->plugin_object->txt("max_tokens"), "max_tokens");
        $max_tokens->setInfo($this->plugin_object->txt("max_tokens_info"));
        $max_tokens->setValue($this->plugin_object->getSetting('max_tokens', '500'));
        $max_tokens->setMinValue(100);
        $max_tokens->setMaxValue(2000);
        $form->addItem($max_tokens);

        // Timeout für API-Calls
        $timeout = new ilNumberInputGUI($this->plugin_object->txt("api_timeout"), "api_timeout");
        $timeout->setInfo($this->plugin_object->txt("api_timeout_info"));
        $timeout->setValue($this->plugin_object->getSetting('api_timeout', '30'));
        $timeout->setMinValue(10);
        $timeout->setMaxValue(120);
        $form->addItem($timeout);

        // Debug-Modus
        $debug_mode = new ilCheckboxInputGUI($this->plugin_object->txt("debug_mode"), "debug_mode");
        $debug_mode->setInfo($this->plugin_object->txt("debug_mode_info"));
        $debug_mode->setChecked($this->plugin_object->getSetting('debug_mode', '0') === '1');
        $form->addItem($debug_mode);

        $form->addCommandButton("save", $lng->txt("save"));

        $form->setTitle($this->plugin_object->txt("configuration"));
        $form->setFormAction($ilCtrl->getFormAction($this));

        return $form;
    }

    /**
     * Save form input
     */
    function save(): void
    {
        global $tpl, $lng, $ilCtrl;

        $form = $this->initConfigurationForm();
        if ($form->checkInput()) {
            // Speichere Einstellungen
            $this->plugin_object->setSetting('ai_enabled', $form->getInput('ai_enabled') ? '1' : '0');
            $this->plugin_object->setSetting('openai_api_key', $form->getInput('openai_api_key'));
            $this->plugin_object->setSetting('ai_model', $form->getInput('ai_model'));
            $this->plugin_object->setSetting('ai_prompt', $form->getInput('ai_prompt'));
            $this->plugin_object->setSetting('max_tokens', $form->getInput('max_tokens'));
            $this->plugin_object->setSetting('api_timeout', $form->getInput('api_timeout'));
            $this->plugin_object->setSetting('debug_mode', $form->getInput('debug_mode') ? '1' : '0');

            // Bestätigungsmeldung
            global $DIC;
            $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $lng->txt("settings_saved"), true);
            $ilCtrl->redirect($this, "configure");
        } else {
            $form->setValuesByPost();
            $tpl->setContent($form->getHTML());
        }
    }

    /**
     * Standard-Prompt für KI-Bewertung
     */
    private function getDefaultPrompt(): string
    {
        return "Bewerte die folgende Antwort eines Studenten auf einer Skala von 0-100 Punkten. 

Gib eine strukturierte Bewertung aus mit:
1. Einer Punktzahl (0-100)
2. Konstruktivem Feedback zur Antwort
3. Verbesserungsvorschlägen

Antwort des Studenten:
{answer}

Antworte im folgenden Format:
Punkte: [0-100]
Feedback: [Dein ausführliches Feedback]";
    }
}
