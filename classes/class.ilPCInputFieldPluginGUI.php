<?php

/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation,
 * Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

use ILIAS\UI\Component\MessageBox\Factory as ilUIMessage;

/**
 * Page Component Input Field plugin GUI
 *
 * @ilCtrl_isCalledBy ilPCInputFieldPluginGUI: ilPCPluggedGUI
 * @ilCtrl_Calls ilPCInputFieldPluginGUI: ilPropertyFormGUI
 */
class ilPCInputFieldPluginGUI extends ilPageComponentPluginGUI
{
    const CONTEXT_PAGE = 'page';
    const CONTEXT_MODULE = 'module';
    const CONTEXT_COURSE = 'course';

    const MODE_EDIT = 'edit';
    const MODE_OFFLINE = 'offline';
    const MODE_PRINT = 'print';
    const MODE_PRESENTATION = 'presentation';
    const MODE_PREVIEW = 'preview';

    const FIELD_TEXT = 'text';
    const FIELD_TEXTAREA = 'textarea';
    const FIELD_SELECT = 'select';

    const SELECT_SINGLE = 'single';
    const SELECT_MULTI = 'multi';

    public function executeCommand(): void
    {
        global $ilCtrl;

        $next_class = $ilCtrl->getNextClass();

        switch ($next_class) {
            case "ilpropertyformgui":
                // ILIAS setzt $_GET['postvar'] auf den Namen des Formfeldes das die Navigation ausgelöst hat
                $postvar = $_GET['postvar'] ?? '';
                if ($postvar === 'field_ai_context_lm_ref_id') {
                    $form = $this->initForm();
                    $ilCtrl->setReturn($this, "updateContextLMRefId");
                } else {
                    $form = $this->initSendForm();
                    $ilCtrl->setReturn($this, "updateExerciseRefId");
                }
                $ilCtrl->forwardCommand($form);
                return;

            default:
                $cmd = $ilCtrl->getCmd();
                if (in_array($cmd, array("create", "create_plug", "save", "edit", "send", "update", "updateSend", "updateExerciseRefId", "updateContextLMRefId", "cancel"))) {
                    $this->$cmd();
                }
                break;
        }
    }

    public function insert(): void
    {
        global $tpl;

        $form = $this->initForm(true);
        $tpl->setContent($form->getHTML());
    }

    public function create(): void
    {
        global $tpl, $lng, $ilCtrl, $DIC;

        $form = $this->initForm(true);
        if ($form->checkInput()) {
            
            // Prüfe ob User Admin ist (für KI-Einstellung)
            $is_admin = $DIC->rbac()->system()->checkAccess('visible', SYSTEM_FOLDER_ID);
            
            $properties = array(
                'field_name' => $form->getInput('field_name'),
                'field_ai_enabled' => ($is_admin && $form->getInput('field_ai_enabled')) ? '1' : '0',
                'field_ai_prompt' => $is_admin ? $form->getInput('field_ai_prompt') : '',
                'field_ai_context_source' => $is_admin ? ($form->getInput('field_ai_context_source') ?: 'none') : 'none',
                'field_ai_context_text' => $is_admin ? $form->getInput('field_ai_context_text') : '',
                'field_ai_context_lm_ref_id' => $is_admin ? (int)$form->getInput('field_ai_context_lm_ref_id') : 0,
                'field_ai_context_page_id' => $is_admin ? (int)$form->getInput('field_ai_context_page_id') : 0,
                'field_type' => $form->getInput('field_type'),
                'field_size' => $form->getInput('field_size'),
                'field_maxlength' => $form->getInput('field_maxlength'),
                'field_cols' => $form->getInput('field_cols'),
                'field_rows' => $form->getInput('field_rows'),
                'select_type' => $form->getInput('select_type'),
                'select_choices' => serialize($form->getInput('select_choices')),
                'field_context' => $form->getInput('field_context'),
            );

            if ($this->createElement($properties)) {
                $messageBox = $GLOBALS['DIC']->ui()->factory()->messageBox()->success("das wurde fei verändert"); //$lng->txt("msg_obj_modified")
                $renderedMessage = $GLOBALS['DIC']->ui()->renderer()->render($messageBox);
                $tpl->setContent($renderedMessage);

                $this->returnToParent();
                return;
            }
        }
        $form->setValuesByPost();
        $tpl->setContent($form->getHtml());
    }

    public function edit(): void
    {
        global $tpl;

        $this->setTabs("edit");
        $form = $this->initForm();
        $tpl->setContent($form->getHTML());
    }

    public function update(): void
    {
        global $tpl, $lng, $DIC;

        $form = $this->initForm(false); // WICHTIG: false für update!
        if ($form->checkInput()) {
            $existing_properties = $this->getProperties();
            
            // Prüfe ob User Admin ist (für KI-Einstellung)
            $is_admin = $DIC->rbac()->system()->checkAccess('visible', SYSTEM_FOLDER_ID);
            
            // Wenn Nicht-Admin: Behalte existierende KI-Werte
            $ai_enabled_value = $is_admin
                ? ($form->getInput('field_ai_enabled') ? '1' : '0')
                : ($existing_properties['field_ai_enabled'] ?? '0');

            $ai_prompt_value = $is_admin
                ? $form->getInput('field_ai_prompt')
                : ($existing_properties['field_ai_prompt'] ?? '');

            $ai_context_source = $is_admin
                ? ($form->getInput('field_ai_context_source') ?: 'none')
                : ($existing_properties['field_ai_context_source'] ?? 'none');
            $ai_context_text = $is_admin
                ? $form->getInput('field_ai_context_text')
                : ($existing_properties['field_ai_context_text'] ?? '');
            $ai_context_lm_ref_id = $is_admin
                ? (int)$form->getInput('field_ai_context_lm_ref_id')
                : (int)($existing_properties['field_ai_context_lm_ref_id'] ?? 0);
            $ai_context_page_id = $is_admin
                ? (int)$form->getInput('field_ai_context_page_id')
                : (int)($existing_properties['field_ai_context_page_id'] ?? 0);

            $properties = array(
                'field_name' => $form->getInput('field_name'),
                'field_ai_enabled' => $ai_enabled_value,
                'field_ai_prompt' => $ai_prompt_value,
                'field_ai_context_source' => $ai_context_source,
                'field_ai_context_text' => $ai_context_text,
                'field_ai_context_lm_ref_id' => $ai_context_lm_ref_id,
                'field_ai_context_page_id' => $ai_context_page_id,
                'field_type' => $form->getInput('field_type'),
                'field_size' => $form->getInput('field_size'),
                'field_maxlength' => $form->getInput('field_maxlength'),
                'field_cols' => $form->getInput('field_cols'),
                'field_rows' => $form->getInput('field_rows'),
                'select_type' => $form->getInput('select_type'),
                'select_choices' => serialize($form->getInput('select_choices')),
                'field_context' => $form->getInput('field_context'),
            );

            // WICHTIG: Merge properties richtig - neue Properties hinzufügen
            $updated_properties = array_merge($existing_properties, $properties);

            if ($this->updateElement($updated_properties)) {
                $messageBox = $GLOBALS['DIC']->ui()->factory()->messageBox()->success($lng->txt("msg_obj_modified"));
                $renderedMessage = $GLOBALS['DIC']->ui()->renderer()->render($messageBox);
                $tpl->setContent($renderedMessage);

                $this->returnToParent();
                return;
            }
        }
        $form->setValuesByPost();
        $tpl->setContent($form->getHtml());
    }

    public function send(): void
    {
        global $tpl;
        $this->setTabs("send");
        $form = $this->initSendForm();
        $tpl->setContent($form->getHTML());
    }

    public function updateSend(): void
    {
        global $tpl, $lng;

        $form = $this->initSendForm(true);

        if ($form->checkInput()) {
            $exercise = array('select_exercise' => $form->getInput('select_exercise'));

            if ((int)$form->getInput('select_exercise') > 0) {
                $assignment = array('select_assignment' => $form->getInput('select_assignment'));
            } else {
                $assignment = array('select_assignment' => "0");
            }

            $existing_properties = $this->getProperties();

            if ($this->updateElement(array_merge($existing_properties, $exercise, $assignment))) {
                $messageBox = $GLOBALS['DIC']->ui()->factory()->messageBox()->success($lng->txt("msg_obj_modified"));
                $renderedMessage = $GLOBALS['DIC']->ui()->renderer()->render($messageBox);
                $tpl->setContent($renderedMessage);

                $this->send();
                return;
            }
        }
        $form->setValuesByPost();
        $tpl->setContent($form->getHtml());
    }

    public function updateExerciseRefId(): void
    {
        global $ilCtrl;

        $form = $this->initSendForm();
        $input = $form->getItemByPostVar('select_exercise');
        $input->readFromSession();

        $properties = $this->getProperties();
        $properties['select_exercise'] = $input->getValue();
        $properties['select_assignment'] = 0;
        $this->updateElement($properties);
        $ilCtrl->redirect($this, 'send');
    }

    public function updateContextLMRefId(): void
    {
        global $ilCtrl;

        $form = $this->initForm();
        $input = $form->getItemByPostVar('field_ai_context_lm_ref_id');
        $input->readFromSession();

        $properties = $this->getProperties();
        $properties['field_ai_context_lm_ref_id'] = $input->getValue();
        $properties['field_ai_context_page_id'] = 0;
        $this->updateElement($properties);
        $ilCtrl->redirect($this, 'edit');
    }

    protected function initForm($a_create = false)
    {
        global $lng, $ilCtrl;

        $form = new ilPropertyFormGUI();

        $name = new ilTextInputGUI($this->txt('field_name'), 'field_name');
        $name->setMaxLength(40);
        $name->setSize(40);
        $name->setRequired(true);
        $form->addItem($name);

        // *** KI-Aktivierung für dieses Feld - NUR FÜR ADMINISTRATOREN ***
        global $DIC;
        $is_admin = $DIC->rbac()->system()->checkAccess('visible', SYSTEM_FOLDER_ID);
        
        if ($is_admin) {
            $ai_enabled = new ilCheckboxInputGUI($this->txt('field_ai_enabled'), 'field_ai_enabled');
            $ai_enabled->setInfo($this->txt('field_ai_enabled_info'));

            $ai_prompt = new ilTextAreaInputGUI($this->txt('field_ai_prompt'), 'field_ai_prompt');
            $ai_prompt->setInfo($this->txt('field_ai_prompt_info'));
            $ai_prompt->setRows(6);
            $ai_prompt->setCols(60);
            $ai_enabled->addSubItem($ai_prompt);

            // *** KI-Kontext-Quelle ***
            $context_source = new ilRadioGroupInputGUI($this->txt('field_ai_context_source'), 'field_ai_context_source');
            $context_source->setInfo($this->txt('field_ai_context_source_info'));
            $context_source->setValue('none');

            $ctx_none = new ilRadioOption($this->txt('field_ai_context_none'), 'none');
            $context_source->addOption($ctx_none);

            $ctx_text = new ilRadioOption($this->txt('field_ai_context_text'), 'text');
            $ctx_text_input = new ilTextAreaInputGUI($this->txt('field_ai_context_text_label'), 'field_ai_context_text');
            $ctx_text_input->setRows(6);
            $ctx_text_input->setCols(60);
            $ctx_text->addSubItem($ctx_text_input);
            $context_source->addOption($ctx_text);

            $ctx_lm = new ilRadioOption($this->txt('field_ai_context_lm'), 'lm_page');
            $lm_selector = new ilRepositorySelectorInputGUI($this->txt('field_ai_context_lm_select'), 'field_ai_context_lm_ref_id');
            $lm_selector->setClickableTypes(['lm']);
            $lm_selector->setHeaderMessage($this->txt('field_ai_context_lm_select'));
            $ctx_lm->addSubItem($lm_selector);

            // Seiten-Dropdown: immer erstellen (auch leer), damit getItemByPostVar() ihn stets findet
            $page_selector = new ilSelectInputGUI($this->txt('field_ai_context_page_select'), 'field_ai_context_page_id');
            $page_options = [0 => $this->txt('field_ai_context_page_none')];
            // Seiten befüllen wenn LM bereits ausgewählt (sicher abrufen)
            if (!$a_create) {
                try {
                    $prop_lm_ref_id = (int)($this->getProperties()['field_ai_context_lm_ref_id'] ?? 0);
                    if ($prop_lm_ref_id > 0) {
                        foreach ($this->getLMPages($prop_lm_ref_id) as $page) {
                            $page_options[$page['obj_id']] = $page['title'];
                        }
                    }
                } catch (Throwable $e) {
                    // getProperties() nicht verfügbar in diesem Kontext – Dropdown bleibt leer
                }
            }
            $page_selector->setOptions($page_options);
            $ctx_lm->addSubItem($page_selector);

            $context_source->addOption($ctx_lm);
            $ai_enabled->addSubItem($context_source);

            $form->addItem($ai_enabled);

            // ilRepositorySelectorInputGUI braucht explizit das Parent-Form gesetzt,
            // da bei tief verschachtelten Sub-Items die Propagation nicht greift.
            $lm_selector->setParentForm($form);
        }

        $type = new ilRadioGroupInputGUI($this->txt('field_type'), 'field_type');

        $textfield = new ilRadioOption($this->txt('field_type_text'), self::FIELD_TEXT);
        $size = new ilNumberInputGUI($this->txt('field_size'), 'field_size');
        $size->setMinValue(1);
        $size->setMaxValue(100);
        $size->setDecimals(0);
        $size->setSize(3);
        $textfield->addSubItem($size);

        $maxlength = new ilNumberInputGUI($this->txt('field_maxlength'), 'field_maxlength');
        $maxlength->setMinValue(1);
        $maxlength->setMaxValue(250);
        $maxlength->setDecimals(0);
        $maxlength->setSize(3);
        $textfield->addSubItem($maxlength);
        $type->addOption($textfield);

        $textarea = new ilRadioOption($this->txt('field_type_textarea'), self::FIELD_TEXTAREA);
        $cols = new ilNumberInputGUI($this->txt('field_cols'), 'field_cols');
        $cols->setMinValue(1);
        $cols->setMaxValue(100);
        $cols->setDecimals(0);
        $cols->setSize(3);
        $textarea->addSubItem($cols);

        $rows = new ilNumberInputGUI($this->txt('field_rows'), 'field_rows');
        $rows->setMinValue(1);
        $rows->setMaxValue(100);
        $rows->setDecimals(0);
        $rows->setSize(3);
        $textarea->addSubItem($rows);
        $type->addOption($textarea);

        $select = new ilRadioOption($this->txt('field_type_select'), self::FIELD_SELECT);
        $select_type = new ilRadioGroupInputGUI($this->txt('select_type'), 'select_type');
        $select_single = new ilRadioOption($this->txt('select_type_single'), self::SELECT_SINGLE);
        $select_type->addOption($select_single);
        $select_multi = new ilRadioOption($this->txt('select_type_multi'), self::SELECT_MULTI);
        $select_type->addOption($select_multi);
        $select->addSubItem($select_type);

        $select_choices = new ilTextInputGUI($this->txt('select_choices'), 'select_choices');
        $select_choices->setMulti(true, true, true);
        $select->addSubItem($select_choices);
        $type->addOption($select);

        $form->addItem($type);

        $context = new ilRadioGroupInputGUI($this->txt('field_context'), 'field_context');
        $context->setInfo($this->txt('field_context_info'));
        $page = new ilRadioOption($this->txt('context_page'), self::CONTEXT_PAGE);
        $context->addOption($page);
        $module = new ilRadioOption($this->txt('context_module'), self::CONTEXT_MODULE);
        $context->addOption($module);
        $course = new ilRadioOption($this->txt('context_course'), self::CONTEXT_COURSE);
        $context->addOption($course);
        $form->addItem($context);

        if ($a_create) {
            $name->setValue('');
            if ($is_admin) {
                $ai_enabled->setChecked(false); // Standard: KI deaktiviert pro Feld
            }
            $type->setValue(self::FIELD_TEXT);
            $size->setValue(50);
            $maxlength->setValue(250);
            $cols->setValue(50);
            $rows->setValue(5);
            $select_type->setValue(self::SELECT_SINGLE);
            $select_choices->setValue(array());
            $context->setValue(self::CONTEXT_PAGE);
            $this->addCreationButton($form);
            $form->addCommandButton("cancel", $lng->txt("cancel"));
            $form->setTitle($this->txt("cmd_insert"));
        } else {
            $prop = $this->getProperties();
            $name->setValue($prop['field_name']);
            
            // WICHTIG: Prüfe ob field_ai_enabled existiert, falls nicht setze auf '0'
            if ($is_admin) {
                $ai_enabled_value = isset($prop['field_ai_enabled']) ? $prop['field_ai_enabled'] : '0';
                $ai_enabled->setChecked($ai_enabled_value === '1');
                $ai_prompt->setValue($prop['field_ai_prompt'] ?? '');
                $context_source->setValue($prop['field_ai_context_source'] ?? 'none');
                $ctx_text_input->setValue($prop['field_ai_context_text'] ?? '');
                $lm_selector->setValue($prop['field_ai_context_lm_ref_id'] ?? '');
                $page_selector->setValue($prop['field_ai_context_page_id'] ?? 0);
            }
            
            $type->setValue($prop['field_type']);
            $size->setValue($prop['field_size']);
            $maxlength->setValue($prop['field_maxlength']);
            $cols->setValue($prop['field_cols']);
            $rows->setValue($prop['field_rows']);
            $select_type->setValue($prop['select_type']);
            $select_choices->setValue((array)unserialize($prop['select_choices']));
            $context->setValue($prop["field_context"]);

            $form->addCommandButton("update", $lng->txt("save"));
            $form->addCommandButton("cancel", $lng->txt("cancel"));
            $form->setTitle($this->txt("edit_input_field"));
        }

        $form->setFormAction($ilCtrl->getFormAction($this));

        return $form;
    }

    protected function initSendForm($a_create = false)
    {
        global $lng, $ilCtrl;

        $form = new ilPropertyFormGUI();

        $prop = $this->getProperties();

        $exercise_selector = new ilRepositorySelectorInputGUI($this->txt('select_exercise'), 'select_exercise');
        $exercise_selector->setClickableTypes(array("exc"));
        $exercise_selector->setHeaderMessage($this->plugin->txt('send_to_exercise'));
        $form->addItem($exercise_selector);

        // Verwende Null-Kohaleszenz-Operator, falls 'select_exercise' nicht vorhanden ist
        $select_exercise = $prop['select_exercise'] ?? 0;

        if ((int)$select_exercise > 0) {
            $ex_ref_id = (int)$select_exercise;
            $ex_obj_id = ilObject::_lookupObjectId($ex_ref_id);
            $exercise_selector->setValue($ex_ref_id);
            $assignments_list = ilExAssignment::getAssignmentDataOfExercise($ex_obj_id);
            $selected_assignment = null;
            $assignment_selector = new ilSelectInputGUI($this->txt('select_assignment'), "select_assignment");

            $assignment_array = array();
            $assignment_array["0"] = $this->txt('no_assignment_selected');

            // Falls 'select_assignment' nicht vorhanden ist, auf "0" setzen
            $select_assignment = $prop['select_assignment'] ?? "0";

            foreach ($assignments_list as $assignment) {
                if ($assignment["type"] == "5") {
                    $assignment_array[$assignment["id"]] = $assignment["title"];
                    if ((int)$assignment["id"] == (int)$select_assignment) {
                        $selected_assignment = new ilExAssignment((int)$assignment["id"]);
                    }
                }
            }

            $assignment_selector->setOptions($assignment_array);
            $assignment_selector->setValue($select_assignment);
            $form->addItem($assignment_selector);

            // Weitere Logik für $selected_assignment, falls vorhanden...
            if (is_a($selected_assignment, 'ilExAssignment')) {
                $schedule_start = new ilNonEditableValueGUI($this->txt('assignment_schedule_start'), 'schedule_start_date');
                if ((int)$selected_assignment->getStartTime()) {
                    $start_date = new DateTime();
                    $start_date->setTimestamp((int)$selected_assignment->getStartTime());
                    $schedule_start->setValue($start_date->format('d.m.Y H:i:s'));
                } else {
                    $schedule_start->setValue($this->txt('assignment_schedule_no_start_time'));
                }

                $schedule_deadline = new ilNonEditableValueGUI($this->txt('assignment_schedule_deadline'), 'schedule_deadline');
                if ((int)$selected_assignment->getDeadline()) {
                    $deadline_date = new DateTime();
                    $deadline_date->setTimestamp((int)$selected_assignment->getDeadline());
                    $schedule_deadline->setValue($deadline_date->format('d.m.Y H:i:s'));
                } else {
                    $schedule_deadline->setValue($this->txt('assignment_schedule_no_deadline'));
                }

                $start_timestamp = new ilHiddenInputGUI('schedule_start_date_timestamp');
                $start_timestamp->setValue($selected_assignment->getStartTime());

                $deadline_timestamp = new ilHiddenInputGUI('schedule_deadline_timestamp');
                $deadline_timestamp->setValue($selected_assignment->getDeadline());

                $link_to_exercise = new ilLocatorGUI();
                $link_to_exercise->addContextItems($ex_ref_id);
                $path_to_exercise = new ilNonEditableValueGUI($this->txt('path_to_related_exercise'), 'path_to_exercise');
                $path_to_exercise->setInfo($link_to_exercise->getHTML());

                $form->addItem($schedule_start);
                $form->addItem($schedule_deadline);
                $form->addItem($start_timestamp);
                $form->addItem($deadline_timestamp);
                $form->addItem($path_to_exercise);
            }
        }

        if ($a_create) {
            $this->addCreationButton($form);
            $form->addCommandButton("cancel", $lng->txt("cancel"));
            $form->setTitle($this->txt("cmd_insert"));
        } else {
            $form->addCommandButton("updateSend", $lng->txt("save"));
            $form->addCommandButton("cancel", $lng->txt("cancel"));
            $form->setTitle($this->txt("send_to_exercise"));
        }

        $form->setFormAction($ilCtrl->getFormAction($this));

        return $form;
    }

    public function cancel(): void
    {
        $this->returnToParent();
    }

    public function setTabs($a_active)
    {
        global $ilTabs, $ilCtrl;

        $pl = $this->getPlugin();

        $ilTabs->addTab("edit", $pl->txt("settings"), $ilCtrl->getLinkTarget($this, "edit"));
        $ilTabs->addTab("send", $pl->txt("send_to_exercise"), $ilCtrl->getLinkTarget($this, "send"));
        $ilTabs->activateTab($a_active);
    }

    protected function txt($a_var)
    {
        return $this->getPlugin()->txt($a_var);
    }

    protected function getJSTexts()
    {
        return array(
            'submitted' => $this->plugin->txt('submitted'),
            're_submit' => $this->plugin->txt('re_submit'),
            'submit_success' => $this->plugin->txt('submit_success')
        );
    }

    protected function getPluginWebDir(): string
    {
        $plugin = $this->getPlugin();
        if (method_exists($plugin, 'getRelativeDirectory')) {
            return $plugin->getRelativeDirectory(); // ILIAS 10: relativer Pfad ab public/
        }
        return $plugin->getDirectory(); // ILIAS 9: bereits relativ
    }

    public function getElementHTML(string $a_mode, array $a_properties, string $a_plugin_version): string
    {
        global $ilUser, $lng, $tpl, $DIC;

        $context_type = $a_properties['field_context'];
        $context_id = $this->getContextId($context_type, $a_mode);
        
        $DIC->logger()->root()->debug('[PCInputField] getElementHTML START: mode=' . $a_mode . ', context=' . $context_type . ':' . $context_id . ', field=' . $a_properties['field_name']);

        require_once $this->getPlugin()->getDirectory() . '/classes/class.ilPCInputFieldValue.php';
        $valObj = ilPCInputFieldValue::getByKeys($context_type, $context_id, $ilUser->getId(), $a_properties['field_name'], false);

        if ($valObj === null) {
            if ($a_properties['field_type'] == self::FIELD_SELECT && $a_properties['select_type'] == self::SELECT_MULTI) {
                $value = [];
            } else {
                $value = '';
            }
            $DIC->logger()->root()->debug('[PCInputField] getElementHTML: NO VALUE FOUND for field=' . $a_properties['field_name']);
        } else {
            if ($a_properties['field_type'] == self::FIELD_SELECT && $a_properties['select_type'] == self::SELECT_MULTI) {
                try {
                    $value = (array)unserialize($valObj->field_value);
                } catch (Exception $e) {
                    $value = [];
                }
            } else {
                $value = $valObj->field_value;
            }
            $logValue = is_array($value) ? json_encode($value) : ($value ?? '');
            $DIC->logger()->root()->debug('[PCInputField] getElementHTML: LOADED value for field=' . $a_properties['field_name'] . ', value=' . substr($logValue, 0, 50));
        }

        $ctpl = $this->getPlugin()->getTemplate("tpl.content.html");

        if ($a_mode == self::MODE_PRESENTATION) {
            $tpl->addJavaScript(ILIAS_HTTP_PATH . '/' . $this->getPluginWebDir() . '/js/pcinfi.js?plugin_version=' . $this->plugin->getVersion());
            $tpl->addOnLoadCode('il.PCInputField.init(' . json_encode($this->getJSTexts()) . ');');
        }

        // Use field_name as base for consistent ID, but keep compatible with existing JS parsing logic
        // JS expects format: NAME_exerciseID_assignmentID for submit buttons
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $a_properties['field_name']);

        if ($a_mode == self::MODE_EDIT) {
            $ctpl->setCurrentBlock('edit');
            $ctpl->setVariable('FIELD_NAME', $a_properties['field_name']);
            switch ($a_properties['field_context']) {
                case self::CONTEXT_PAGE:
                    $ctpl->setVariable('FIELD_CONTEXT', $this->txt('context_page_short'));
                    break;
                case self::CONTEXT_MODULE:
                    $ctpl->setVariable('FIELD_CONTEXT', $this->txt('context_module_short'));
                    break;
                case self::CONTEXT_COURSE:
                    $ctpl->setVariable('FIELD_CONTEXT', $this->txt('context_course_short'));
                    break;
            }
            $ctpl->parseCurrentBlock();
        }

        if ($a_mode == self::MODE_PRINT && ($a_properties['field_type'] == self::FIELD_TEXT || $a_properties['field_type'] == self::FIELD_TEXTAREA)) {
            $ctpl->setCurrentBlock('printtext');
            $ctpl->setVariable('NAME', $name);
            $ctpl->setVariable('VALUE', htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
            $ctpl->parseCurrentBlock();
        } else {
            switch ($a_properties['field_type']) {
                case self::FIELD_TEXT:
                    $ctpl->setCurrentBlock('text');
                    $ctpl->setVariable('ID', rand(0, 9999999));
                    $ctpl->setVariable('NAME', $name);
                    $ctpl->setVariable('SIZE', $a_properties['field_size']);
                    $ctpl->setVariable('MAXLENGTH', $a_properties['field_maxlength']);
                    //  $ctpl->setVariable('VALUE', htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
                    $ctpl->setVariable('VALUE', htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'));

                    $ctpl->parseCurrentBlock();
                    break;

                case self::FIELD_TEXTAREA:
                    $ctpl->setCurrentBlock('textarea');
                    $ctpl->setVariable('ID', rand(0, 9999999));
                    $ctpl->setVariable('NAME', $name);
                    $ctpl->setVariable('COLS', $a_properties['field_cols']);
                    $ctpl->setVariable('ROWS', $a_properties['field_rows']);
                    $ctpl->setVariable('VALUE', htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'));
                    $ctpl->parseCurrentBlock();
                    break;

                case self::FIELD_SELECT:
                    $choices = (array)unserialize($a_properties['select_choices']);

                    switch ($a_properties['select_type']) {
                        case self::SELECT_SINGLE:
                            foreach ($choices as $choice) {
                                $ctpl->setCurrentBlock('single_choice_option');
                                $ctpl->setVariable('ID', rand(0, 9999999));
                                $ctpl->setVariable('NAME', $name);
                                $ctpl->setVariable("VALUE", htmlspecialchars($choice, ENT_QUOTES, 'UTF-8'));
                                if ($choice == $value) {
                                    $ctpl->setVariable('CHECKED', 'checked="checked"');
                                }
                                $ctpl->parseCurrentBlock();
                            }
                            $ctpl->setCurrentBlock('single_choice');
                            $ctpl->setVariable('NAME', $name);
                            $ctpl->parseCurrentBlock();
                            break;

                        case self::SELECT_MULTI:
                            foreach ($choices as $choice) {
                                $ctpl->setCurrentBlock('multi_choice_option');
                                $ctpl->setVariable('ID', rand(0, 9999999));
                                $ctpl->setVariable('NAME', $name);
                                $ctpl->setVariable("VALUE", htmlspecialchars($choice, ENT_QUOTES, 'UTF-8'));
                                if (is_array($value) && in_array($choice, $value)) {
                                    $ctpl->setVariable('CHECKED', 'checked="checked"');
                                }
                                $ctpl->parseCurrentBlock();
                            }
                            $ctpl->setCurrentBlock('multi_choice');
                            $ctpl->setVariable('NAME', $name);
                            $ctpl->parseCurrentBlock();
                            break;
                    }
                    break;
            }
        }

        switch ($a_mode) {
            case self::MODE_PREVIEW:
            case self::MODE_PRESENTATION:
                $service_url = ILIAS_HTTP_PATH . '/' . $this->getPluginWebDir() . '/service.php'
                    . '?client_id=' . CLIENT_ID
                    . '&amp;ref_id=' . (int)$_GET['ref_id']
                    . '&amp;context_type=' . urlencode($context_type)
                    . '&amp;context_id=' . urlencode($context_id)
                    . '&amp;field_name=' . urlencode($a_properties['field_name'])
                    . '&amp;field_type=' . urlencode($a_properties['field_type'])
                    . '&amp;select_type=' . urlencode($a_properties['select_type'])
                    . '&amp;field_ai_enabled=' . urlencode($a_properties['field_ai_enabled'] ?? '0')
                    . '&amp;field_ai_prompt=' . urlencode($a_properties['field_ai_prompt'] ?? '')
                    . '&amp;field_ai_context_source=' . urlencode($a_properties['field_ai_context_source'] ?? 'none')
                    . '&amp;field_ai_context_text=' . urlencode($a_properties['field_ai_context_text'] ?? '')
                    . '&amp;field_ai_context_lm_ref_id=' . (int)($a_properties['field_ai_context_lm_ref_id'] ?? 0)
                    . '&amp;field_ai_context_page_id=' . (int)($a_properties['field_ai_context_page_id'] ?? 0);

                $exc_back_ref_id = (int)($_GET['exc_back_ref_id'] ?? 0);
                if ($exc_back_ref_id > 0) {
                    $type = ilObjectFactory::getTypeByRefId($exc_back_ref_id);
                    if ($type == "exc") {
                        $service_url = ILIAS_HTTP_PATH . '/' . $this->getPluginWebDir() . '/service.php'
                            . '?client_id=' . CLIENT_ID
                            . '&amp;ref_id=' . $exc_back_ref_id
                            . '&amp;context_type=' . urlencode($context_type)
                            . '&amp;context_id=' . urlencode($context_id)
                            . '&amp;field_name=' . urlencode($a_properties['field_name'])
                            . '&amp;field_type=' . urlencode($a_properties['field_type'])
                            . '&amp;select_type=' . urlencode($a_properties['select_type'])
                            . '&amp;field_ai_enabled=' . urlencode($a_properties['field_ai_enabled'] ?? '0')
                            . '&amp;field_ai_prompt=' . urlencode($a_properties['field_ai_prompt'] ?? '')
                            . '&amp;field_ai_context_source=' . urlencode($a_properties['field_ai_context_source'] ?? 'none')
                            . '&amp;field_ai_context_text=' . urlencode($a_properties['field_ai_context_text'] ?? '')
                            . '&amp;field_ai_context_lm_ref_id=' . (int)($a_properties['field_ai_context_lm_ref_id'] ?? 0)
                            . '&amp;field_ai_context_page_id=' . (int)($a_properties['field_ai_context_page_id'] ?? 0);
                    }
                }

                $ctpl->setVariable('MODE_CLASS', 'ilPCInputFieldActive');
                $ctpl->setVariable('SERVICE_URL', $service_url);
                $ctpl->setVariable('FIELD_TYPE', $a_properties['field_type']);

                $ctpl->setVariable('TXT_SAVING', $this->txt('saving'));
                $ctpl->setVariable('IMG_LOADER', './templates/default/images/loader.svg');
                break;

            case self::MODE_EDIT:
            case self::MODE_OFFLINE:
                $ctpl->setVariable('MODE_CLASS', 'ilPCInputFieldInactive');
                break;

            case self::MODE_PRINT:
                $ctpl->setVariable('MODE_CLASS', 'ilPCInputFieldInactive ilc_section_Block');
                break;
        }

        if (isset($a_properties['select_exercise']) and isset($a_properties['select_assignment'])) {
            if ((int)$a_properties['select_exercise'] and (int)$a_properties['select_assignment']) {
                //include_once("./Modules/Exercise/classes/class.ilExAssignment.php");
                $obj_id = ilObject::_lookupObjId($a_properties['select_exercise']);
                $assignments_list = ilExAssignment::getAssignmentDataOfExercise($obj_id);

                $assignment_array = array();
                $assignment_array["0"] = $this->txt('no_assignment_selected');
                $selected_assignment = null;
                foreach ($assignments_list as $assignment) {
                    if ($assignment["type"] == "5") {
                        $assignment_array[$assignment["id"]] = $assignment["title"];
                        if ((int)$assignment["id"] == (int)$a_properties['select_assignment']) {
                            $selected_assignment = new ilExAssignment((int)$assignment["id"]);
                            break;
                        }
                    }
                }

                if (is_a($selected_assignment, 'ilExAssignment')) {
                    $start_date = new ilDateTime($selected_assignment->getStartTime(), IL_CAL_UNIX);
                    $raw_deadline = (int)$selected_assignment->getDeadline();
                    if (empty($raw_deadline)) {
                        $raw_deadline = PHP_INT_MAX; // Fallback auf "niemals"
                    }
                    $deadline = new ilDateTime($raw_deadline, IL_CAL_UNIX);

                    $submit_time_raw = $this->getLastSubmission($selected_assignment);
                    $submit_time = ($submit_time_raw ? new ilDateTime($submit_time_raw, IL_CAL_DATETIME) : '');

                    // Treat deadline=0 same as null (ILIAS returns 0 for "Keine Abgabefrist")
                    $no_start    = empty((int)$selected_assignment->getStartTime());
                    $no_deadline = empty((int)$selected_assignment->getDeadline());
                    $after_start   = (time() - (int)$selected_assignment->getStartTime()) > 0;
                    $before_deadline = ((int)$selected_assignment->getDeadline() - time()) > 0;

                    if ($no_start && $no_deadline) {
                        $sendable = TRUE;
                    } elseif ($no_start && $before_deadline) {
                        $sendable = TRUE;
                    } elseif ($no_deadline && $after_start) {
                        $sendable = TRUE;
                    } elseif ($after_start && $before_deadline) {
                        $sendable = TRUE;
                    } else {
                        $sendable = FALSE;
                    }

                    if ($sendable) {
                        $ctpl->setCurrentBlock('submission');
                        $ctpl->setVariable('BUTTON_ID', $name . '_' . $selected_assignment->getExerciseId() . '_' . $selected_assignment->getId());
                        
                        // Zeige KI-Text am Submit-Button wenn KI aktiviert ist
                        $submit_text = $this->plugin->txt($submit_time_raw ? 're_submit' : 'submit');
                        if (($a_properties['field_ai_enabled'] ?? '0') === '1' && $this->plugin->getSetting('ai_enabled', '0') === '1') {
                            $submit_text = $submit_text . ' (KI-Bewertung)';
                        }
                        
                        $ctpl->setVariable('VALUE', $submit_text);
                        $ctpl->setVariable('CMD', 'cmd[sendInput]');
                        $ctpl->parseCurrentBlock();

                        $ctpl->setCurrentBlock('confirmation');
                        $ctpl->setVariable('NAME', $name);
                        $ctpl->setVariable('CONFIRMATION_MODAL', $this->getConfirmationModal($name));
                        $ctpl->parseCurrentBlock();
                    }

                    $ctpl->setCurrentBlock('status');
                    $ctpl->setVariable('NAME', $name);
                    $ctpl->setVariable('STATUS', $this->plugin->txt($submit_time_raw ? 'submitted' : 'not_yet_submitted'));
                    if ($submit_time_raw) {
                        $ctpl->setVariable('TIME', ilDatePresentation::formatDate($submit_time));
                    }
                    $ctpl->parseCurrentBlock();

                    // *** NEUE KI-FEEDBACK ANZEIGE (nur wenn KI für dieses Feld aktiviert ist) ***
                    if ($submit_time_raw && ($a_properties['field_ai_enabled'] ?? '0') === '1') {
                        $this->showAIFeedback($ctpl, $name, $selected_assignment, $ilUser->getId());
                    }

                    if ((int)$selected_assignment->getStartTime()) {
                        $ctpl->setCurrentBlock('start_time');
                        $ctpl->setVariable('START_DATE', $this->plugin->txt('assignment_schedule_start') . ': ');
                        $ctpl->setVariable('START_DATE_VALUE', ilDatePresentation::formatDate($start_date));
                        $ctpl->parseCurrentBlock();
                    }

                    if ((int)$selected_assignment->getDeadline()) {
                        $ctpl->setCurrentBlock('deadline');
                        $ctpl->setVariable('DEADLINE', $this->plugin->txt('assignment_schedule_deadline') . ': ');
                        $ctpl->setVariable('DEADLINE_VALUE', ilDatePresentation::formatDate($deadline));
                        $ctpl->parseCurrentBlock();
                    }
                }
            }
        }

        return $ctpl->get();
    }

    /**
     * Neue Methode: Zeige KI-Feedback für Student (nur wenn KI für Feld aktiviert)
     */
    protected function showAIFeedback($ctpl, $name, $assignment, $user_id)
    {
        try {
            global $ilDB;

            $assignment_id = $assignment->getId();

            // Sicher & typisiert
            $res = $ilDB->queryF(
                "SELECT u_comment
                 FROM exc_mem_ass_status
                 WHERE ass_id = %s AND usr_id = %s
                   AND u_comment IS NOT NULL AND u_comment <> ''",
                ['integer','integer'],
                [$assignment_id, $user_id]
            );
            $row = $ilDB->fetchAssoc($res);

            if (!$row || trim((string)$row['u_comment']) === '') {
                return; // nichts zu zeigen
            }

            $feedback_text_raw = (string)$row['u_comment'];

            // Marker-Prüfung weicher machen (zur Sicherheit alles anzeigen)
            $is_ai = (stripos($feedback_text_raw, 'KI-VORBEWERTUNG') !== false)
                  || (stripos($feedback_text_raw, 'KI-Feedback') !== false);

            if (!$is_ai) {
                return; // kein KI-Feedback, still bleiben
            }

            // Parsen (robuster)
            $parsed = $this->parseAIFeedbackForDisplay($feedback_text_raw);
            $score   = $parsed['score'];
            $text    = $parsed['feedback'];

            // Fallbacks
            if ($text === '' || $text === null) {
                // Wenn Parsing fehlte, ganzen Kommentar zeigen (escape + nl2br)
                $text = $feedback_text_raw;
            }

            // HTML vorbereiten (einfach, ohne Child-Blöcke)
            $score_html = '';
            if ($score !== null && $score !== '') {
                $score_html = '<div class="ai-score"><strong>Automatische Bewertung:</strong> '
                            . htmlspecialchars((string)$score, ENT_QUOTES, 'UTF-8')
                            . '/100</div>';
            }

            $text_html = '<div class="ai-text" style="margin-top:.35rem;">'
                       . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'))
                       . '</div>';

            $combined_html = $score_html . $text_html;

            // Parent-Block setzen und NUR hier Variablen füllen
            $ctpl->setCurrentBlock('ai_feedback_display');
            $ctpl->setVariable('NAME', $name);
            $ctpl->setVariable('AI_SCORE_BOX', $score_html);
            $ctpl->setVariable('AI_FEEDBACK_TEXT_BOX', $text_html);
            $ctpl->parseCurrentBlock();

        } catch (Exception $e) {
            // optionales Logging
            if (isset($GLOBALS['DIC'])) {
                $GLOBALS['DIC']->logger()->root()->debug('[PCInputField] KI-Feedback Anzeige: ' . $e->getMessage());
            }
        }
    }

    /**
     * Parse KI-Feedback für schönere Anzeige
     */
    protected function parseAIFeedbackForDisplay($feedback_text)
    {
        $score = null;
        $feedback = '';

        // Extrahiere Score
        if (preg_match('/Automatische Bewertung:\s*(\d+)\/100 Punkte/i', $feedback_text, $matches)) {
            $score = $matches[1];
        }

        // Extrahiere Feedback-Text
        if (preg_match('/📝 KI-Feedback:\s*\n(.*?)\n\n⏰/s', $feedback_text, $matches)) {
            $feedback = trim($matches[1]);
        }

        return array(
            'score' => $score,
            'feedback' => $feedback
        );
    }

    protected function getContextId($a_context_type, $a_mode)
    {
        global $tree;

        static $page_id = null;
        static $course_id = null;

        if (!in_array($a_mode, array(self::MODE_PREVIEW, self::MODE_PRESENTATION, self::MODE_PRINT))) {
            return 0;
        }

        switch ($a_context_type) {
            case self::CONTEXT_PAGE:
                switch ($a_mode) {
                    case self::MODE_PREVIEW:
                        $context_id = $_GET['obj_id'];
                        break;

                    case self::MODE_PRESENTATION:
                    case self::MODE_PRINT:
                        if (!isset($page_id)) {
                            $page_id = $this->plugin->getPageId();
                        }
                        $context_id = $page_id;
                        break;

                    default:
                        $context_id = '0';
                }
                break;

            case self::CONTEXT_MODULE:
                $context_id = ilObject::_lookupObjId($_GET['ref_id']);
                break;

            case self::CONTEXT_COURSE:
                if (!isset($course_id)) {
                    $path = array_reverse($tree->getPathFull($_GET['ref_id']));
                    foreach ($path as $key => $row) {
                        if ($row['type'] == 'crs') {
                            $course_id = ilObject::_lookupObjId($row['child']);
                            break;
                        }
                    }
                    if (!isset($course_id)) {
                        $course_id = ilObject::_lookupObjId($_GET['ref_id']);
                    }
                }
                $context_id = $course_id;
                break;

            default:
                $context_id = 0;
        }

        return $context_id;
    }

    protected function isAlreadySubmitted($assignment_id)
    {
        global $ilDB, $ilUser;

        $user_id = $ilUser->getId();
        $user_ids = array();

        $set = $ilDB->query("SELECT DISTINCT(user_id) FROM exc_returned WHERE ass_id = " . $ilDB->quote($assignment_id, "integer") . " AND user_id = " . $ilDB->quote($user_id, "integer"));
        while ($row = $ilDB->fetchAssoc($set)) {
            $user_ids[] = $row["user_id"];
        }

        return (sizeof($user_ids) > 0);
    }

    protected function getLastSubmission($assignmentObject)
    {
        global $ilUser;
        // require_once('Modules/Exercise/classes/class.ilExSubmission.php');
        $subObj = new ilExSubmission($assignmentObject, $ilUser->getId());

        return $subObj->getLastSubmission();
    }

    protected function getConfirmationModal($name): string
    {
        $tpl = $this->getPlugin()->getTemplate('tpl.confirm.html');

        $send_btn = '<button id="pcinfi_send_button" class="btn btn-primary">'
            . htmlspecialchars($this->txt('submit'), ENT_QUOTES, 'UTF-8') . '</button>';
        $tpl->setCurrentBlock('buttons');
        $tpl->setVariable('BUTTON', $send_btn);
        $tpl->parseCurrentBlock();

        $cancel_btn = '<button id="pcinfi_cancel_button" class="btn btn-default">'
            . htmlspecialchars($this->txt('cancel'), ENT_QUOTES, 'UTF-8') . '</button>';
        $tpl->setCurrentBlock('buttons');
        $tpl->setVariable('BUTTON', $cancel_btn);
        $tpl->parseCurrentBlock();

        $heading = htmlspecialchars($this->txt('save_on_navigation'), ENT_QUOTES, 'UTF-8');

        return '<dialog id="pcinfi_' . $name . '_confirmation"'
            . ' style="padding:1.5rem;border-radius:6px;border:1px solid #ccc;min-width:320px;max-width:500px;">'
            . '<h4 style="margin-top:0;">' . $heading . '</h4>'
            . $tpl->get()
            . '</dialog>';
    }

    /**
     * Lade alle Seiten eines Lernmoduls für den Seiten-Picker
     * @param int $lm_ref_id  Referenz-ID des Lernmoduls
     * @return array  Array of ['obj_id' => int, 'title' => string]
     */
    protected function getLMPages(int $lm_ref_id): array
    {
        global $ilDB;

        try {
            $lm_obj_id = ilObject::_lookupObjId($lm_ref_id);
            if (!$lm_obj_id) {
                return [];
            }

            $res = $ilDB->queryF(
                "SELECT obj_id, title FROM lm_data WHERE lm_id = %s AND type = %s ORDER BY title",
                ['integer', 'text'],
                [$lm_obj_id, 'pg']
            );

            $pages = [];
            while ($row = $ilDB->fetchAssoc($res)) {
                $pages[] = [
                    'obj_id' => (int)$row['obj_id'],
                    'title'  => $row['title'],
                ];
            }
            return $pages;
        } catch (Throwable $e) {
            return [];
        }
    }
}