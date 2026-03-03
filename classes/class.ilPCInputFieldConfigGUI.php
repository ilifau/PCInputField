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
            case "testConnection":
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

        // ===== AI Provider Auswahl =====
        $ai_provider = new ilRadioGroupInputGUI($this->plugin_object->txt("ai_provider"), "ai_provider");
        $ai_provider->setInfo($this->plugin_object->txt("ai_provider_info"));
        $ai_provider->setValue($this->plugin_object->getSetting('ai_provider', 'lmstudio'));

        // Option: LM Studio (lokal)
        $lmstudio = new ilRadioOption($this->plugin_object->txt("ai_provider_lmstudio"), "lmstudio");
        $lmstudio->setInfo($this->plugin_object->txt("ai_provider_lmstudio_info"));

        $lm_endpoint = new ilTextInputGUI($this->plugin_object->txt("lm_endpoint_url"), "lm_endpoint_url");
        $lm_endpoint->setInfo($this->plugin_object->txt("lm_endpoint_url_info"));
        $lm_endpoint->setValue($this->plugin_object->getSetting('lm_endpoint_url', 'http://localhost:1234/v1/chat/completions'));
        $lm_endpoint->setSize(60);
        $lmstudio->addSubItem($lm_endpoint);

        $lm_model = new ilTextInputGUI($this->plugin_object->txt("lm_model"), "lm_model");
        $lm_model->setInfo($this->plugin_object->txt("lm_model_info"));
        $lm_model->setValue($this->plugin_object->getSetting('lm_model', 'qwen2.5-14b-instruct'));
        $lm_model->setSize(40);
        $lmstudio->addSubItem($lm_model);

        $ai_provider->addOption($lmstudio);

        // Option: ChatGPT (OpenAI)
        $chatgpt = new ilRadioOption($this->plugin_object->txt("ai_provider_chatgpt"), "chatgpt");
        $chatgpt->setInfo($this->plugin_object->txt("ai_provider_chatgpt_info"));

        $openai_key = new ilTextInputGUI($this->plugin_object->txt("openai_api_key"), "openai_api_key");
        $openai_key->setInfo($this->plugin_object->txt("openai_api_key_info"));
        $openai_key->setValue($this->plugin_object->getSetting('openai_api_key', ''));
        $openai_key->setSize(60);
        $chatgpt->addSubItem($openai_key);

        $openai_model = new ilSelectInputGUI($this->plugin_object->txt("openai_model"), "openai_model");
        $openai_model->setInfo($this->plugin_object->txt("openai_model_info"));
        $openai_model->setOptions([
            'gpt-5' => 'GPT-5 (empfohlen)',
            'gpt-5-mini' => 'GPT-5 Mini (ausgewogen)',
            'gpt-5-nano' => 'GPT-5 Nano (günstiger, schneller)',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4' => 'GPT-4'
        ]);
        $openai_model->setValue($this->plugin_object->getSetting('openai_model', 'gpt-5-mini'));
        $chatgpt->addSubItem($openai_model);

        $ai_provider->addOption($chatgpt);
        $form->addItem($ai_provider);

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
        $max_tokens->setValue($this->plugin_object->getSetting('max_tokens', '2000'));
        $max_tokens->setMinValue(100);
        $max_tokens->setMaxValue(32000);
        $form->addItem($max_tokens);

        // Temperature
        $temperature = new ilNumberInputGUI($this->plugin_object->txt("ai_temperature"), "ai_temperature");
        $temperature->setInfo($this->plugin_object->txt("ai_temperature_info"));
        $temperature->setValue($this->plugin_object->getSetting('ai_temperature', '0.3'));
        $temperature->setMinValue(0);
        $temperature->setMaxValue(2);
        $temperature->setDecimals(2);
        $form->addItem($temperature);

        // Timeout für API-Calls
        $timeout = new ilNumberInputGUI($this->plugin_object->txt("api_timeout"), "api_timeout");
        $timeout->setInfo($this->plugin_object->txt("api_timeout_info"));
        $timeout->setValue($this->plugin_object->getSetting('api_timeout', '60'));
        $timeout->setMinValue(10);
        $timeout->setMaxValue(300);
        $form->addItem($timeout);

        // Debug-Modus
        $debug_mode = new ilCheckboxInputGUI($this->plugin_object->txt("debug_mode"), "debug_mode");
        $debug_mode->setInfo($this->plugin_object->txt("debug_mode_info"));
        $debug_mode->setChecked($this->plugin_object->getSetting('debug_mode', '0') === '1');
        $form->addItem($debug_mode);

        $form->addCommandButton("save", $lng->txt("save"));
        $form->addCommandButton("testConnection", $this->plugin_object->txt("test_connection"));

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
            $this->plugin_object->setSetting('ai_provider', $form->getInput('ai_provider'));

            // LM Studio Einstellungen
            $this->plugin_object->setSetting('lm_endpoint_url', $form->getInput('lm_endpoint_url'));
            $this->plugin_object->setSetting('lm_model', $form->getInput('lm_model'));

            // ChatGPT/OpenAI Einstellungen
            $this->plugin_object->setSetting('openai_api_key', $form->getInput('openai_api_key'));
            $this->plugin_object->setSetting('openai_model', $form->getInput('openai_model'));

            // Gemeinsame Einstellungen
            $this->plugin_object->setSetting('ai_prompt', $form->getInput('ai_prompt'));
            $this->plugin_object->setSetting('max_tokens', $form->getInput('max_tokens'));
            $this->plugin_object->setSetting('ai_temperature', $form->getInput('ai_temperature'));
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
     * Test AI Connection
     */
    function testConnection(): void
    {
        global $tpl, $ilCtrl, $DIC;

        require_once(__DIR__ . '/class.ilPCInputFieldAIRating.php');

        // Teste mit einem einfachen Text
        $testText = "Dies ist ein Test der KI-Verbindung.";
        $result = ilPCInputFieldAIRating::evaluateText($testText);

        if ($result['success']) {
            $message = $this->plugin_object->txt("test_connection_success") . "<br><br>";
            $message .= "<strong>" . $this->plugin_object->txt("test_response") . ":</strong><br>";
            $message .= "<pre>" . htmlspecialchars($result['raw_response']) . "</pre>";

            $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $message, true);
        } else {
            $errorMsg = $this->plugin_object->txt("test_connection_failed") . "<br><br>";
            $errorMsg .= "<strong>" . $this->plugin_object->txt("error") . ":</strong> " . htmlspecialchars($result['message']);

            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $errorMsg, true);
        }

        $ilCtrl->redirect($this, "configure");
    }

    /**
     * Standard-Prompt für KI-Bewertung
     */
    private function getDefaultPrompt(): string
    {
        return "Analysiere die folgende Antwort eines Studenten und gib konstruktives Feedback.

Beachte dabei:
- Inhaltliche Korrektheit und Vollständigkeit
- Verständlichkeit und Struktur der Antwort
- Stärken der Antwort
- Verbesserungspotenzial und konkrete Vorschläge

Antwort des Studenten:
{answer}

Gib ein ausführliches, hilfreiches Feedback.

Optional kannst du eine Punktzahl (0-100) angeben, wenn du dies für sinnvoll hältst:
Format: Punkte: [0-100]
Feedback: [Dein ausführliches Feedback]";
    }
}
