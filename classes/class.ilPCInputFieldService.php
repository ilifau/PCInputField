<?php

/**
 * Page Component Input Field: service for handling inputs (AJAX)
 * Hardened for PHP 8 and consistent JSON responses
 */
class ilPCInputFieldService
{
    // must correspond to ilPCInputFieldPluginGUI
    const FIELD_TEXT     = 'text';
    const FIELD_TEXTAREA = 'textarea';
    const FIELD_SELECT   = 'select';
    const SELECT_SINGLE  = 'single';
    const SELECT_MULTI   = 'multi';

    /**
     * @var string path of the plugin's base directory
     */
    protected $plugin_path = '';

    public function __construct()
    {
        $this->plugin_path = realpath(dirname(__FILE__) . '/..');
    }

    /**
     * Sanitize user input (remove null bytes, excessive whitespace)
     */
    private function sanitizeInput(string $input, int $maxLength = 65535): string
    {
        // Remove null bytes (security)
        $input = str_replace("\0", '', $input);
        
        // Limit length (CLOB in MySQL is typically 64KB)
        if (strlen($input) > $maxLength) {
            $input = substr($input, 0, $maxLength);
        }
        
        return $input;
    }

    /**
     * Einheitliche JSON-Antwort
     */
    protected function respondJSON(int $status, array $payload)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    /**
     * Handle an incoming request
     */
    public function handleRequest()
    {
        global $ilAccess;

        try {
            $ref_id = (int)($_GET['ref_id'] ?? 0);
            if (!$ref_id || !$ilAccess->checkAccess('read', '', $ref_id)) {
                return $this->respondJSON(403, ['status' => 403, 'message' => 'Forbidden']);
            }

            $cmd = $_POST['cmd'] ?? '';
            switch ($cmd) {
                case 'saveInput':
                    return $this->saveInput();
                case 'sendInput':
                    return $this->sendInput();
                default:
                    return $this->respondJSON(501, ['status' => 501, 'message' => 'Not Implemented']);
            }
        } catch (Throwable $e) {
            return $this->respondJSON(500, ['status' => 500, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Save an input that is sent
     */
    protected function sendInput()
    {
        global $ilUser, $DIC;

        require_once $this->plugin_path . '/classes/class.ilPCInputFieldSend.php';
        require_once $this->plugin_path . '/classes/class.ilPCInputFieldValue.php';

        $field_name    = $_POST['name'] ?? '';
        $field_type    = $_POST['type'] ?? '';
        $exercise_id   = $_POST['exercise'] ?? '0';
        $assignment_id = $_POST['assignment'] ?? '0';
        $select_type   = $_GET['select_type'] ?? self::SELECT_SINGLE;

        $field_ai_enabled = $this->getFieldAIEnabled($field_name);
        
        // DEBUG
        $DIC->logger()->root()->info('[PCInputField] sendInput called: field=' . $field_name . ', ai_enabled=' . ($field_ai_enabled ? 'YES' : 'NO'));

        $sendObj = ilPCInputFieldSend::init($ilUser->getId(), $field_name, $field_type, $exercise_id, $assignment_id);
        if (!($sendObj instanceof ilPCInputFieldSend)) {
            return $this->respondJSON(500, ['status' => 500, 'message' => 'Init failed: ilPCInputFieldSend is null']);
        }
        $sendObj->field_ai_enabled = $field_ai_enabled;

        $raw = $_POST['value'] ?? null;
        if ($field_type === self::FIELD_SELECT) {
            if (is_array($raw)) {
                $value_arr = array_map([$this, 'sanitizeInput'], $raw);
            } elseif ($raw !== null) {
                $value_arr = [$this->sanitizeInput($raw)];
            } else {
                $value_arr = [];
            }
            if ($select_type === self::SELECT_SINGLE) {
                $sendObj->field_value = $value_arr ? current($value_arr) : '';
            } else {
                $sendObj->field_value = serialize($value_arr);
            }
        } else {
            $sendObj->field_value = ($raw !== null) ? $this->sanitizeInput($raw) : '';
        }

        try {
            if ($submit_time_str = $sendObj->send()) {
                // *** WICHTIG: Auch in pcinfi_values speichern, damit nach Reload der Wert sichtbar ist ***
                $context_type = $_GET['context_type'] ?? '';
                $context_id   = $_GET['context_id'] ?? '';
                
                $DIC->logger()->root()->info('[PCInputField] sendInput SUCCESS - Saving to DB: context=' . $context_type . ':' . $context_id . ', field=' . $field_name . ', value=' . substr($sendObj->field_value, 0, 50));
                
                $valObj = ilPCInputFieldValue::getByKeys($context_type, $context_id, $ilUser->getId(), $field_name, true);
                $valObj->field_value = $sendObj->field_value;
                $valObj->save();
                
                $DIC->logger()->root()->info('[PCInputField] Value saved with ID=' . $valObj->id);
                
                // *** Merker setzen: gerade gesendet (Race-Protection für saveInput) ***
                $key = implode(':', [(int)$ilUser->getId(), $context_type, $context_id, $field_name]);
                if (!isset($_SESSION)) {
                    @session_start();
                }
                $_SESSION['pcinfi_last_send'][$key] = time();

                return $this->respondJSON(200, ['status' => 200, 'submit_time_str' => $submit_time_str]);
            }
            return $this->respondJSON(500, ['status' => 500, 'message' => $sendObj->send_message ?? 'Unknown error']);
        } catch (Throwable $e) {
            $DIC->logger()->error('Fehler beim Senden der Eingabe: ' . $e->getMessage());
            return $this->respondJSON(500, ['status' => 500, 'message' => $e->getMessage()]);
        }
    }



    /**
     * Send an input to an exercise
     */
    protected function saveInput()
    {
        global $ilUser, $DIC;
        require_once $this->plugin_path . '/classes/class.ilPCInputFieldValue.php';

        $context_type = $_GET['context_type'] ?? '';
        $context_id   = $_GET['context_id'] ?? '';
        $field_name   = $_GET['field_name'] ?? '';
        $field_type   = $_GET['field_type'] ?? '';
        $select_type  = $_GET['select_type'] ?? self::SELECT_SINGLE;

        $DIC->logger()->root()->debug('[PCInputField] saveInput: context=' . $context_type . ':' . $context_id . ', field=' . $field_name);

        // create if not exists (ohne zu überschreiben)
        $valObj = ilPCInputFieldValue::getByKeys($context_type, $context_id, $ilUser->getId(), $field_name, true);

        // --- RACE-PROTECTION: Wenn kurz vorher ein sendInput lief und value leer ist -> NICHT speichern ---
        $has_value_key = array_key_exists('value', $_POST);
        $raw           = $has_value_key ? $_POST['value'] : null;

        // "Leer" definieren ('' oder leeres Array)
        $is_empty_submission =
            ($raw === '' || $raw === null ||
                (is_array($raw) && count(array_filter($raw, static function ($v) {
                    return $v !== '' && $v !== null;
                })) === 0));

        // Schlüssel wie in sendInput
        $key = implode(':', [(int)$ilUser->getId(), $context_type, $context_id, $field_name]);
        if (!isset($_SESSION)) {
            @session_start();
        }
        $recent_send_ts = $_SESSION['pcinfi_last_send'][$key] ?? 0;
        $recent_send    = $recent_send_ts && (time() - (int)$recent_send_ts) <= 5; // 5 Sekunden Fenster

        if ($recent_send && $is_empty_submission) {
            // Überschreiben verhindern – alten Wert behalten
            return $this->respondJSON(200, ['status' => 200, 'id' => $valObj->id, 'skipped' => true]);
        }

        // Wenn value-Key gar nicht existiert: ebenfalls nichts ändern
        if (!$has_value_key) {
            return $this->respondJSON(200, ['status' => 200, 'id' => $valObj->id, 'skipped' => true]);
        }

        // --- Normale Speicherung ---
        if ($field_type === self::FIELD_SELECT) {
            if (is_array($raw)) {
                $value_arr = array_map([$this, 'sanitizeInput'], $raw);
            } elseif ($raw !== null) {
                $value_arr = [$this->sanitizeInput($raw)];
            } else {
                $value_arr = [];
            }
            if ($select_type === self::SELECT_SINGLE) {
                $valObj->field_value = $value_arr ? current($value_arr) : '';
            } else {
                $valObj->field_value = serialize($value_arr);
            }
        } else {
            $valObj->field_value = ($raw !== null) ? $this->sanitizeInput($raw) : '';
        }

        $valObj->save();
        return $this->respondJSON(200, ['status' => 200, 'id' => $valObj->id]);
    }


    /**
     * Prüfe, ob KI-Bewertung für dieses Feld aktiv ist (global + Feld)
     */
    private function getFieldAIEnabled(string $field_name): bool
    {
        try {
            global $DIC;

            // 1) Globales Flag
            $settings = $DIC->settings();
            $global_ai_enabled = $settings->get('pcinfi_ai_enabled', '0') === '1';
            
            $DIC->logger()->root()->debug('[PCInputField] Global AI enabled: ' . ($global_ai_enabled ? 'YES' : 'NO'));
            
            if (!$global_ai_enabled) {
                return false;
            }

            // 2) Feld-Flag aus URL (SERVICE_URL hängt field_ai_enabled=0/1 an)
            $field_flag = $_GET['field_ai_enabled'] ?? '0';
            
            $DIC->logger()->root()->debug('[PCInputField] Field AI flag from GET: ' . $field_flag);
            
            return $field_flag === '1';
        } catch (Throwable $e) {
            // Fallback: nur globales Flag
            global $DIC;
            $DIC->logger()->root()->warning('[PCInputField] Error in getFieldAIEnabled: ' . $e->getMessage());
            return $DIC->settings()->get('pcinfi_ai_enabled', '0') === '1';
        }
    }
}
