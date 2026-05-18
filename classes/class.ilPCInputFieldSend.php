<?php

/**
 * Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

/**
 * Page Component Input Field plugin: send to exercise
 *
 * @author Jesus Copado <jesus.copado@fim.uni-erlangen.de>
 * @version $Id$
 */
class ilPCInputFieldSend
{
    /**
     * @var string
     */
    public $send_status;

    /**
     * @var string
     */
    public $send_message;

    /**
     * @var integer
     */
    public $send_time;

    /**
     * @var integer
     */
    public $user_id;

    /**
     * @var string
     */
    public $field_name;

    /**
     * @var string
     */
    public $field_type;

    /**
     * @var integer
     */
    public $exercise_id;

    /**
     * @var integer
     */
    public $assignment_id;

    /**
     * @var string
     */
    public $field_value;

    /**
     * @var boolean
     */
    public $field_ai_enabled = false;

    /**
     * Individueller KI-Prompt für dieses Feld (überschreibt den globalen Admin-Prompt wenn gesetzt)
     * @var string
     */
    public $field_ai_prompt = '';

    /**
     * Zusätzlicher Kontext für die KI (z.B. Lernmodul-Seite oder Freitext)
     * @var string
     */
    public $field_ai_context = '';

    /**
     * @param $user_id integer
     * @param $field_name string
     * @param $field_type string
     * @param $exercise_id integer
     * @param $assignment_id integer
     * @return ilPCInputFieldSend
     */
    public static function init($user_id, $field_name, $field_type, $exercise_id, $assignment_id)
    {
        $obj = new ilPCInputFieldSend();
        $obj->user_id = (int)$user_id;
        $obj->field_name = $field_name;
        $obj->field_type = $field_type;
        $obj->exercise_id = (int)$exercise_id;
        $obj->assignment_id = (int) $assignment_id;

        return $obj;
    }

    public function send()
{
    /*
     * Previous checking of existence and availability of exercise assignment
     */

    //Get all assignments of exercise
    $exc_assignment_data = ilExAssignment::getAssignmentDataOfExercise($this->exercise_id);

    //Get assignment we want to send field content
    $assignment = null;
    foreach ($exc_assignment_data as $assignment_data) {
        if ((int)$assignment_data['id'] == $this->assignment_id) {
            $assignment = new ilExAssignment($this->assignment_id);
            break;
        }
    }

    //If assignment is not in exercise send error message
    if (!is_a($assignment, 'ilExAssignment')) {
        $this->send_status  = "ERROR_NO_ASSIGNMENT_IN_EXERCISE";
        $this->send_message = "Assignment gehört nicht zur Exercise.";
        return false;
    }

    //Check if user is in time to send the field content to the assignment
    // Treat deadline=0 same as null (ILIAS returns 0 for "Keine Abgabefrist")
    $no_start        = empty((int)$assignment->getStartTime());
    $no_deadline     = empty((int)$assignment->getDeadline());
    $after_start     = (time() - (int)$assignment->getStartTime()) > 0;
    $before_deadline = ((int)$assignment->getDeadline() - time()) > 0;

    if ($no_start && $no_deadline) {
        $sendable = true;
    } elseif ($no_start && $before_deadline) {
        $sendable = true;
    } elseif ($no_deadline && $after_start) {
        $sendable = true;
    } elseif ($after_start && $before_deadline) {
        $sendable = true;
    } else {
        $sendable = false;
    }

    if (!$sendable) {
        $this->send_status  = "ERROR_NOT_IN_TIME";
        $this->send_message = "Abgabezeitfenster geschlossen.";
        return false;
    }

    // add the user to the exercise
    $exercise = new ilObjExercise($this->exercise_id, false);
    $members = $exercise->members_obj;
    if (!$members->isAssigned($this->user_id)) {
        $exc_set = new ilSetting("excs");
        $old = $exc_set->get("add_to_pd", true);
        $exc_set->set('add_to_pd', false);
        $members->assignMember($this->user_id);
        $exc_set->set('add_to_pd', $old);
    }

    //Create or update submission (NUR die originale Antwort)
    $exc_submission = new ilExSubmission($assignment, $this->user_id);
    $exc_submission->updateTextSubmission($this->field_value);

    // *** KI-BEWERTUNG INS FEEDBACK (nur wenn aktiviert) ***
    if ($this->field_ai_enabled) {
        global $DIC;
        $DIC->logger()->root()->info('[PCInputField] KI-Bewertung wird gestartet für field_value: ' . substr($this->field_value, 0, 50));
        
        require_once(dirname(__FILE__) . '/class.ilPCInputFieldAIRating.php');
        $ai_result = ilPCInputFieldAIRating::evaluateText($this->field_value, $this->field_ai_prompt, $this->field_ai_context);
        
        $DIC->logger()->root()->info('[PCInputField] KI-Bewertung Ergebnis: success=' . ($ai_result['success'] ? 'YES' : 'NO'));
        
        $this->setAIFeedback($assignment, $this->user_id, $ai_result);
    } else {
        global $DIC;
        $DIC->logger()->root()->info('[PCInputField] KI-Bewertung ÜBERSPRUNGEN (field_ai_enabled=false)');
    }

    // Exercise-Status aktualisieren
    $exercise->processExerciseStatus(
        $assignment,
        array($this->user_id),
        true
    );

    // return the date and time of the submission
    $submit_time_raw = $exc_submission->getLastSubmission();
    if (!empty($submit_time_raw)) {
        $submit_time = new ilDateTime($submit_time_raw, IL_CAL_DATETIME);
        return ilDatePresentation::formatDate($submit_time);
    }

    // Fallback: wenn keine Zeit gespeichert wurde → jetzt nehmen
    $now = new ilDateTime(time(), IL_CAL_UNIX);
    return ilDatePresentation::formatDate($now);
}


    /**
     * Setze KI-Bewertung als Exercise-Feedback
     */
    private function setAIFeedback($assignment, $user_id, $ai_result)
    {
        try {
            global $ilDB, $DIC;

            $feedback_text = $this->buildFeedbackText($ai_result);
            $ass_id = (int)$assignment->getId();

            $res = $ilDB->queryF(
                "SELECT usr_id FROM exc_mem_ass_status WHERE ass_id = %s AND usr_id = %s",
                ['integer', 'integer'],
                [$ass_id, $user_id]
            );

            if ($ilDB->numRows($res)) {
                $ilDB->manipulateF(
                    "UPDATE exc_mem_ass_status SET u_comment = %s WHERE ass_id = %s AND usr_id = %s",
                    ['text', 'integer', 'integer'],
                    [$feedback_text, $ass_id, $user_id]
                );
            } else {
                $ilDB->manipulateF(
                    "INSERT INTO exc_mem_ass_status (ass_id, usr_id, u_comment) VALUES (%s,%s,%s)",
                    ['integer', 'integer', 'text'],
                    [$ass_id, $user_id, $feedback_text]
                );
            }

            $DIC->logger()->info("KI-Feedback gespeichert (ass_id={$ass_id}, usr_id={$user_id})");
        } catch (Throwable $e) {
            $DIC->logger()->error('Fehler beim Speichern des KI-Feedbacks: ' . $e->getMessage());
        }
    }



    /**
     * Erstelle Feedback-Text aus KI-Bewertung
     */
    private function buildFeedbackText($ai_result)
    {
        $feedback_text = "=== KI-VORBEWERTUNG ===\n\n";

        if ($ai_result['success']) {

            // Score nur anzeigen, wenn er auch wirklich von der KI geliefert wurde (nicht null)
            if (isset($ai_result['score']) && $ai_result['score'] !== null) {
                $feedback_text .= "Automatische Bewertung: " . $ai_result['score'] . "/100 Punkte\n\n";
            }

            if (isset($ai_result['feedback']) && !empty($ai_result['feedback'])) {
                $feedback_text .= "KI-Feedback:\n" . $ai_result['feedback'] . "\n\n";
            }

            $feedback_text .= "Bewertungszeitpunkt: " . date('d.m.Y H:i:s') . "\n";

            $feedback_text .= "--- Platz für Dozenten-Feedback ---\n\n";
        } else {
            $feedback_text .= "Automatische Bewertung nicht verfügbar\n";
            $feedback_text .= "Grund: " . $ai_result['message'] . "\n\n";
            $feedback_text .= "Manuelle Bewertung erforderlich.\n\n";
        }

        return $feedback_text;
    }

    /**
     * Konvertiere 0-100 Score zu ILIAS Mark
     */
    private function convertScoreToMark($score)
    {
        // Einfache Konvertierung - kann je nach Bedarf angepasst werden
        if ($score >= 90) return "1.0";
        if ($score >= 80) return "2.0";
        if ($score >= 70) return "3.0";
        if ($score >= 60) return "4.0";
        if ($score >= 50) return "4.5";
        return "5.0";

        // Alternative: Direkt den Score als "Punkte"
        // return $score . "/100";
    }
}
