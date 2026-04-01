<?php

/**
 * Copyright (c) 2025 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

/**
 * PCInputField Plugin: KI-Bewertung (Synchron)
 */
class ilPCInputFieldAIRating
{
    /**
     * Führe KI-Bewertung durch und gib Ergebnis sofort zurück
     */
    public static function evaluateText($content, $custom_prompt = '', $context = '')
    {
        error_log('[PCInputField AI] evaluateText() aufgerufen mit: ' . substr($content, 0, 50));

        global $DIC;

        // Lade Plugin-Konfiguration - WICHTIG: Settings haben 'pcinfi_' Präfix!
        $settings = $DIC->settings();

        $ai_enabled = $settings->get('pcinfi_ai_enabled', '0') === '1';
        if (!$ai_enabled) {
            return ['success' => false, 'message' => 'KI-Bewertung ist nicht aktiviert'];
        }

        $ai_provider = $settings->get('pcinfi_ai_provider', 'lmstudio');
        // Individueller Prompt überschreibt den globalen Admin-Prompt
        $system_prompt = (!empty($custom_prompt)) ? $custom_prompt : $settings->get('pcinfi_ai_prompt', '');
        $max_tokens = (int)$settings->get('pcinfi_max_tokens', 2000);
        $temperature = (float)$settings->get('pcinfi_ai_temperature', 0.3);
        $timeout = (int)$settings->get('pcinfi_api_timeout', 60);

        error_log('[PCInputField AI] Provider: ' . $ai_provider);

        // Provider-spezifische Konfiguration
        if ($ai_provider === 'chatgpt') {
            $endpoint_url = 'https://api.openai.com/v1/chat/completions';
            $api_key = $settings->get('pcinfi_openai_api_key', '');
            $model = $settings->get('pcinfi_openai_model', 'gpt-5-mini');

            error_log('[PCInputField AI] ChatGPT - Model: ' . $model . ', Key length: ' . strlen($api_key));

            if (empty($api_key)) {
                return ['success' => false, 'message' => 'OpenAI API Key ist nicht konfiguriert'];
            }
        } else {
            // LM Studio (default)
            $endpoint_url = $settings->get('pcinfi_lm_endpoint_url', 'http://localhost:1234/v1/chat/completions');
            $api_key = ''; // LM Studio benötigt keinen API Key
            $model = $settings->get('pcinfi_lm_model', 'qwen2.5-14b-instruct');
            $num_ctx = (int)$settings->get('pcinfi_lm_num_ctx', 8192);

            error_log('[PCInputField AI] LM Studio - Model: ' . $model);
        }

        if (empty($endpoint_url)) {
            return ['success' => false, 'message' => 'KI-Endpoint URL ist nicht konfiguriert'];
        }

        try {
            // Führe KI-Bewertung durch
            $result = self::callAI($endpoint_url, $api_key, $model, $system_prompt, $content, $max_tokens, $temperature, $timeout, $context, $num_ctx ?? 0);

            if ($result['success']) {
                return array(
                    'success' => true,
                    'score' => $result['score'],
                    'feedback' => $result['feedback'],
                    'raw_response' => $result['raw_response']
                );
            } else {
                return array(
                    'success' => false,
                    'message' => $result['error']
                );
            }
        } catch (Exception $e) {
            $DIC->logger()->error('KI-Bewertung fehlgeschlagen: ' . $e->getMessage());

            return array(
                'success' => false,
                'message' => 'KI-Bewertung nicht verfügbar: ' . $e->getMessage()
            );
        }
    }

    /**
     * KI-API Aufruf (funktioniert mit OpenAI API und LM Studio)
     */
    private static function callAI($endpoint_url, $api_key, $model, $system_prompt, $user_content, $max_tokens, $temperature, $timeout = 30, $context = '', $num_ctx = 0)
    {
        // Baue User-Nachricht zusammen (mit optionalem Kontext)
        if (!empty($context)) {
            $user_message = "Hier ist der relevante Lehrinhalt als Kontext:\n\n"
                . $context
                . "\n\n---\n\nBitte bewerte folgende studentische Antwort:\n\n"
                . $user_content;
        } else {
            $user_message = "Bitte bewerte folgende studentische Antwort:\n\n" . $user_content;
        }

        // Bereite Request vor
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt
            ),
            array(
                'role' => 'user',
                'content' => $user_message
            )
        );

        // GPT-5 Modelle verwenden max_completion_tokens statt max_tokens
        $is_gpt5_model = strpos($model, 'gpt-5') !== false;

        $data = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature
        );

        // Verwende den richtigen Parameter je nach Modell
        if ($is_gpt5_model) {
            $data['max_completion_tokens'] = $max_tokens;
        } else {
            $data['max_tokens'] = $max_tokens;
        }

        // num_ctx nur für LM Studio (kein API-Key = lokales Modell)
        if (empty($api_key) && $num_ctx > 0) {
            $data['num_ctx'] = $num_ctx;
        }

        // Headers
        $headers = array(
            'Content-Type: application/json'
        );

        // API Key nur hinzufügen wenn vorhanden (für LM Studio optional)
        if (!empty($api_key)) {
            $headers[] = 'Authorization: Bearer ' . $api_key;
        }

        // Debug: Log request data
        error_log('[PCInputField AI] Request data: ' . json_encode($data));

        // cURL Request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Für lokale LM Studio Instanzen

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_error($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log('[PCInputField AI] cURL Error: ' . $error);
            throw new Exception('cURL Error: ' . $error);
        }

        curl_close($ch);

        // Debug: Log response
        error_log('[PCInputField AI] HTTP Code: ' . $http_code);
        error_log('[PCInputField AI] Response: ' . substr($response, 0, 500));

        if ($http_code !== 200) {
            throw new Exception('HTTP Error ' . $http_code . ': ' . $response);
        }

        $response_data = json_decode($response, true);
        if ($response_data === null) {
            error_log('[PCInputField AI] JSON decode error: ' . json_last_error_msg());
            throw new Exception('Invalid JSON from AI endpoint: ' . json_last_error_msg());
        }

        // Debug: Log decoded response structure
        error_log('[PCInputField AI] Response structure: ' . json_encode(array_keys($response_data)));

        if (!isset($response_data['choices'][0]['message']['content'])) {
            error_log('[PCInputField AI] Invalid response structure: ' . json_encode($response_data));
            throw new Exception('Invalid API response structure. Response: ' . json_encode($response_data));
        }

        $ai_response = $response_data['choices'][0]['message']['content'];

        // Parse Antwort
        $parsed = self::parseAIResponse($ai_response);

        return array(
            'success' => true,
            'score' => $parsed['score'],
            'feedback' => $parsed['feedback'],
            'raw_response' => $ai_response
        );
    }

    /**
     * Parse KI-Antwort nach Score und Feedback
     */
    private static function parseAIResponse($ai_response)
    {
        $score = null;
        $feedback = $ai_response;

        // Versuche Score zu extrahieren (nur wenn explizit vorhanden)
        if (preg_match('/SCORE:\s*(\d+)/i', $ai_response, $matches)) {
            $score = min(100, max(0, (int)$matches[1]));
        }
        // Alternative Formate für Score
        elseif (preg_match('/(?:Punkte|Punktzahl|Score|Points):\s*(\d+)/i', $ai_response, $matches)) {
            $score = min(100, max(0, (int)$matches[1]));
        }
        // Format: "X/100" oder "X von 100"
        elseif (preg_match('/(\d+)\s*(?:\/|von)\s*100/i', $ai_response, $matches)) {
            $score = min(100, max(0, (int)$matches[1]));
        }

        // Versuche Feedback zu extrahieren
        if (preg_match('/FEEDBACK:\s*(.*)/is', $ai_response, $matches)) {
            $feedback = trim($matches[1]);
        }
        // Alternative: Falls Score gefunden wurde, entferne die Score-Zeile aus dem Feedback
        elseif ($score !== null) {
            // Entferne Score-Zeilen aus dem Feedback
            $feedback = preg_replace('/^.*(?:Punkte|Punktzahl|Score|Points):\s*\d+.*$/im', '', $ai_response);
            $feedback = preg_replace('/^\d+\s*(?:\/|von)\s*100.*$/im', '', $feedback);
            $feedback = trim($feedback);
        }

        // WICHTIG: Kein Fallback-Score mehr! Score bleibt null wenn nicht explizit angegeben

        return array(
            'score' => $score,  // kann null sein!
            'feedback' => $feedback
        );
    }
}
