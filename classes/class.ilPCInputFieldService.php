<?php

/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation,
 * Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

/**
 * Page Component Input Field: service for handling inputs
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */
class ilPCInputFieldService
{

    // must correspond to ilPCInputfieldPluginGUI
    // repeated here to avoid instanciation
    const FIELD_TEXT = 'text';
    const FIELD_TEXTAREA = 'textarea';
    const FIELD_SELECT = 'select';
    const SELECT_SINGLE = 'single';
    const SELECT_MULTI = 'multi';

    /**
     * @var string path of the plugin's base directory
     */
    protected $plugin_path = '';

    /**
     * Constructor: general initialisations
     */
    public function __construct()
    {
        $this->plugin_path = realpath(dirname(__FILE__) . '/..');
    }

    /**
     * Handle an incoming request
     */
    public function handleRequest()
    {
        /** @var ilAccessHandler $ilAccess */
        global $ilAccess, $ilUser;
        try {
            if (!$ilAccess->checkAccess('read', '', $_GET['ref_id'])) {
                $this->respondHTTP(403, $ilUser->getLogin()); // forbidden
            }

            switch ($_POST['cmd']) {
                case 'saveInput':
                    $this->saveInput();
                    break;
                case 'sendInput':
                    $this->sendInput();
                    break;
                default:
                    $this->respondHTTP(501); // not implemented
                    break;
            }
        } catch (Exception $exception) {
            //$this->respondHTTP(500, $exception->getMessage());
        }
    }

    /**
     * Save an input that is sent
     */
    protected function saveInput()
    {
        global $ilUser;
        require_once($this->plugin_path . '/classes/class.ilPCInputFieldValue.php');
        $context_type = ilUtil::stripSlashes($_GET['context_type']);
        $context_id = ilUtil::stripSlashes(($_GET['context_id']));
        $field_name = ilUtil::stripSlashes($_GET['field_name']);
        $field_type = ilUtil::stripSlashes($_GET['field_type']);
        $select_type = ilUtil::stripSlashes($_GET['select_type']);

        // save the input (create if not exists)
        $valObj = ilPCInputFieldValue::getByKeys($context_type, $context_id, $ilUser->getId(), $field_name, true);

        if ($field_type == self::FIELD_SELECT) {
            $value = ilArrayUtil::stripSlashesArray((array)$_POST['value']);
            if ($select_type == self::SELECT_SINGLE) {
                $valObj->field_value = current($value);
            } else {
                $valObj->field_value = serialize($value);
            }
        } else {
            $valObj->field_value = ilUtil::stripSlashes($_POST['value']);
        }

        $valObj->save();
        $this->respondHTTP(200, json_encode($valObj->id));
    }

    /**
     * Send an input to an exercise
     */
    protected function sendInput()
    {
        global $ilUser;
        global $DIC;
        require_once($this->plugin_path . '/classes/class.ilPCInputFieldSend.php');
        $field_name = ilUtil::stripSlashes($_POST['name']);
        $field_type = ilUtil::stripSlashes($_POST['type']);
        $exercise_id = ilUtil::stripSlashes(($_POST['exercise']));
        $assignment_id = ilUtil::stripSlashes($_POST['assignment']);
        $select_type = ilUtil::stripSlashes($_GET['select_type']);

        // *** NEUE: HOLE FELD-SPEZIFISCHE KI-EINSTELLUNG ***
        $field_ai_enabled = $this->getFieldAIEnabled($field_name);

        // Send the input object
        $sendObj = ilPCInputFieldSend::init($ilUser->getId(), $field_name, $field_type, $exercise_id, $assignment_id);

        // *** NEUE: ÜBERGEBE KI-EINSTELLUNG AN SEND-OBJEKT ***
        $sendObj->field_ai_enabled = $field_ai_enabled;

        if ($field_type == self::FIELD_SELECT) {
            $value = ilArrayUtil::stripSlashesArray((array)$_POST['value']);
            if ($select_type == self::SELECT_SINGLE) {
                $sendObj->field_value = current($value);
            } else {
                $sendObj->field_value = serialize($value);
            }
        } else {
            $sendObj->field_value = ilUtil::stripSlashes($_POST['value']);
        }

        // Send the input to exercise assignment
        try {
            if ($submit_time_str = $sendObj->send()) {
                $this->respondHTTP(200, json_encode(array('submit_time_str' => $submit_time_str)));
            } else {
                $this->respondHTTP(500);
            }
        } catch (Exception $e) {
            // Log the error
            $DIC->logger()->error('Fehler beim Senden der Eingabe: ' . $e->getMessage());
            $this->respondHTTP(500, $e->getMessage()); // Send the error message back
        }
    }

    /**
     * Prüfe ob KI-Bewertung für dieses Feld aktiviert ist
     */
    private function getFieldAIEnabled($field_name)
    {
        try {
            global $DIC;
            
            // 1. Prüfe globale KI-Aktivierung
            $settings = $DIC->settings();
            $global_ai_enabled = $settings->get('pcinfi_ai_enabled', '0') === '1';
            
            if (!$global_ai_enabled) {
                return false; // Global deaktiviert
            }
            
            // 2. Hole Feld-spezifische Einstellung aus URL-Parameter (wird von GUI übergeben)
            $field_ai_enabled = ilUtil::stripSlashes($_GET['field_ai_enabled'] ?? '0');
            
            return $field_ai_enabled === '1';
            
        } catch (Exception $e) {
            // Bei Fehlern: Safe fallback auf global aktiviert
            global $DIC;
            $settings = $DIC->settings();
            return $settings->get('pcinfi_ai_enabled', '0') === '1';
        }
    }

    /**
     * Send a HTTP response
     * @param int    $status  HTTP status
     * @param string $message response message
     */
    protected function respondHTTP($status, $message = null)
    {
        switch ($status) {
            case 200:
                $text = 'OK';
                break;
            case 400:
                $text = 'Bad Request';
                break;
            case 401:
                $text = 'Unauthorized';
                break;
            case 403:
                $text = 'Forbidden';
                break;
            case 404:
                $text = 'Not Found';
                break;
            case 500:
                $text = 'Internal Server Error';
                break;
            case 501:
                $text = 'Not Implemented';
                break;
            default:
                $text = 'Unknown';
        }
        header('HTTP/1.1 ' . $status . ' ' . $text);
        header('Content-type: application/json');
        echo isset($message) ? $message : $text;
        exit;
    }
}