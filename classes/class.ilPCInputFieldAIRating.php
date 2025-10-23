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
    public static function evaluateText($content)
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
        $system_prompt = $settings->get('pcinfi_ai_prompt', '');
        $max_tokens = (int)$settings->get('pcinfi_max_tokens', 500);
        $temperature = (float)$settings->get('pcinfi_ai_temperature', 0.3);
        $timeout = (int)$settings->get('pcinfi_api_timeout', 30);

        error_log('[PCInputField AI] Provider: ' . $ai_provider);

        // Provider-spezifische Konfiguration
        if ($ai_provider === 'chatgpt') {
            $endpoint_url = 'https://api.openai.com/v1/chat/completions';
            $api_key = $settings->get('pcinfi_openai_api_key', '');
            $model = $settings->get('pcinfi_openai_model', 'gpt-4o-mini');

            error_log('[PCInputField AI] ChatGPT - Model: ' . $model . ', Key length: ' . strlen($api_key));

            if (empty($api_key)) {
                return ['success' => false, 'message' => 'OpenAI API Key ist nicht konfiguriert'];
            }
        } else {
            // LM Studio (default)
            $endpoint_url = $settings->get('pcinfi_lm_endpoint_url', 'http://localhost:1234/v1/chat/completions');
            $api_key = ''; // LM Studio benötigt keinen API Key
            $model = $settings->get('pcinfi_lm_model', 'qwen2.5-14b-instruct');

            error_log('[PCInputField AI] LM Studio - Model: ' . $model);
        }

        if (empty($endpoint_url)) {
            return ['success' => false, 'message' => 'KI-Endpoint URL ist nicht konfiguriert'];
        }

        try {
            // Führe KI-Bewertung durch
            $result = self::callAI($endpoint_url, $api_key, $model, $system_prompt, $content, $max_tokens, $temperature, $timeout);

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
    private static function callAI($endpoint_url, $api_key, $model, $system_prompt, $user_content, $max_tokens, $temperature, $timeout = 30)
    {
        // Bereite Request vor
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt
            ),
            array(
                'role' => 'user',
                'content' => "Bitte bewerte folgende studentische Antwort:\n\n" . $user_content
            )
        );

        $data = array(
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature
        );

        // Headers
        $headers = array(
            'Content-Type: application/json'
        );

        // API Key nur hinzufügen wenn vorhanden (für LM Studio optional)
        if (!empty($api_key)) {
            $headers[] = 'Authorization: Bearer ' . $api_key;
        }

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
            curl_close($ch);
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('HTTP Error ' . $http_code . ': ' . $response);
        }

        $response_data = json_decode($response, true);
        if ($response_data === null) {
            throw new Exception('Invalid JSON from AI endpoint: ' . json_last_error_msg());
        }


        if (!isset($response_data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response: ' . $response);
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

        // Versuche Score zu extrahieren
        if (preg_match('/SCORE:\s*(\d+)/i', $ai_response, $matches)) {
            $score = min(100, max(0, (int)$matches[1]));
        }

        // Versuche Feedback zu extrahieren
        if (preg_match('/FEEDBACK:\s*(.*)/is', $ai_response, $matches)) {
            $feedback = trim($matches[1]);
        }

        // Fallback: Wenn kein strukturiertes Format gefunden wird
        if ($score === null) {
            // Versuche Zahlen in der Antwort zu finden
            if (preg_match('/(\d+)\s*(?:\/\s*100|\s*Punkte|\s*points)/i', $ai_response, $matches)) {
                $score = min(100, max(0, (int)$matches[1]));
            } else {
                $score = 50; // Default falls nichts gefunden wird
            }
        }

        return array(
            'score' => $score,
            'feedback' => $feedback
        );
    }
}
