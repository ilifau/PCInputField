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
        include_once('./Modules/Exercise/Assignment/class.ilExAssignment.php');
        $exc_assignment_data = ilExAssignment::getAssignmentDataOfExercise($this->exercise_id);

        //Get assignment we want to send field content
        $assignment = null;
        foreach ($exc_assignment_data as $assignment_data)
        {
            if ((int)$assignment_data['id'] == $this->assignment_id)
            {
                //create assignment object
                $assignment = new ilExAssignment($this->assignment_id);
                break;
            }
        }

        //If assignment is not in exercise send error message
        if (!is_a($assignment, 'ilExAssignment'))
        {
            $this->send_status = "ERROR_NO_ASSIGNMENT_IN_EXERCISE";
            $this->send_message = "ERROR_NO_ASSIGNMENT_IN_EXERCISE";
            return false;
        }

        //Check if user is in time to send the field content to the assignment
        if (is_null($assignment->getStartTime()) AND (((int)$assignment->getDeadline() - time()) > 0))
        {
            $sendable = TRUE;
        } elseif (is_null($assignment->getDeadline()) AND ((time() - (int)$assignment->getStartTime()) > 0))
        {
            $sendable = TRUE;
        } elseif (((time() - (int)$assignment->getStartTime()) > 0) AND (((int)$assignment->getDeadline() - time()) > 0))
        {
            $sendable = TRUE;
        } elseif (is_null($assignment->getStartTime()) AND is_null($assignment->getDeadline()))
        {
            $sendable = TRUE;
        } else
        {
            $sendable = FALSE;
        }

        if (!$sendable)
        {
            $this->send_status = "ERROR_NOT_IN_TIME";
            $this->send_message = "ERROR_NOT_IN_TIME";
            return false;
        }

        // add the user to the exercise
        include_once('Modules/Exercise/classes/class.ilObjExercise.php');
        $exercise = new ilObjExercise($this->exercise_id, false);
        $members = $exercise->members_obj;
        if (!$members->isAssigned($this->user_id))
        {
            $exc_set = new ilSetting("excs");
            $old = $exc_set->get("add_to_pd", true);
            $exc_set->set('add_to_pd', false);
            $members->assignMember($this->user_id);
            $exc_set->set('add_to_pd', $old);
        }

        //Create or update submission (NUR die originale Antwort)
        include_once('./Modules/Exercise/Submission/class.ilExSubmission.php');
        $exc_submission = new ilExSubmission($assignment, $this->user_id);
        $exc_submission->updateTextSubmission($this->field_value);

        // *** KI-BEWERTUNG INS FEEDBACK (nur wenn aktiviert) ***
        if ($this->field_ai_enabled) {
            // KI-Bewertung ohne Plugin-Objekt durchführen
            require_once(dirname(__FILE__) . '/class.ilPCInputFieldAIRating.php');
            $ai_result = ilPCInputFieldAIRating::evaluateText($this->field_value);
            
            // Setze KI-Bewertung als Feedback
            $this->setAIFeedback($assignment, $this->user_id, $ai_result);
        }

        //@see ilExSubmissionBaseGUI::handleNewUpload()
        $exercise->processExerciseStatus(
            $assignment,
            array($this->user_id),
            true);

        // return the date and time of the submission
        $submit_time_raw = $exc_submission->getLastSubmission();
        if ($submit_time_raw) {
            $submit_time = new ilDateTime($submit_time_raw, IL_CAL_DATETIME);
            return ilDatePresentation::formatDate($submit_time);
        }
        else {
            return "";
        }
    }

    /**
     * Setze KI-Bewertung als Exercise-Feedback
     */
    private function setAIFeedback($assignment, $user_id, $ai_result)
    {
        try {
            global $ilDB;
            
            // Erstelle Feedback-Text
            $feedback_text = $this->buildFeedbackText($ai_result);
            
            // Hole assignment_id
            $assignment_id = $assignment->getId();
            
            // Prüfe ob bereits ein Eintrag existiert
            $query = "SELECT * FROM exc_mem_ass_status 
                      WHERE ass_id = " . $ilDB->quote($assignment_id, 'integer') . " 
                      AND usr_id = " . $ilDB->quote($user_id, 'integer');
            $result = $ilDB->query($query);
            
            if ($ilDB->fetchAssoc($result)) {
                // UPDATE: Eintrag existiert bereits
                $update_query = "UPDATE exc_mem_ass_status 
                               SET u_comment = " . $ilDB->quote($feedback_text, 'text') . "
                               WHERE ass_id = " . $ilDB->quote($assignment_id, 'integer') . " 
                               AND usr_id = " . $ilDB->quote($user_id, 'integer');
                $ilDB->manipulate($update_query);
                
            } else {
                // INSERT: Neuer Eintrag
                $insert_query = "INSERT INTO exc_mem_ass_status (ass_id, usr_id, u_comment) 
                               VALUES (" . 
                               $ilDB->quote($assignment_id, 'integer') . ", " .
                               $ilDB->quote($user_id, 'integer') . ", " .
                               $ilDB->quote($feedback_text, 'text') . ")";
                $ilDB->manipulate($insert_query);
            }
            
            global $DIC;
            $DIC->logger()->info('KI-Feedback in exc_mem_ass_status gespeichert für User ' . $user_id . ', Assignment ' . $assignment_id);
            
        } catch (Exception $e) {
            global $DIC;
            $DIC->logger()->error('Fehler beim Speichern des KI-Feedbacks in Datenbank: ' . $e->getMessage());
        }
    }

    /**
     * Erstelle Feedback-Text aus KI-Bewertung
     */
    private function buildFeedbackText($ai_result)
    {
        $feedback_text = "=== KI-VORBEWERTUNG ===\n\n";
        
        if ($ai_result['success']) {
            
            if (isset($ai_result['score'])) {
                $feedback_text .= "🤖 Automatische Bewertung: " . $ai_result['score'] . "/100 Punkte\n\n";
            }
            
            if (isset($ai_result['feedback'])) {
                $feedback_text .= "📝 KI-Feedback:\n" . $ai_result['feedback'] . "\n\n";
            }
            
            $feedback_text .= "⏰ Bewertungszeitpunkt: " . date('d.m.Y H:i:s') . "\n";
            $feedback_text .= "ℹ️ Dies ist eine automatische Vorbewertung zur Orientierung.\n";
            $feedback_text .= "📋 Eine manuelle Nachbewertung durch den Dozenten ist möglich.\n\n";
            
            $feedback_text .= "--- Platz für Dozenten-Feedback ---\n\n";
            
        } else {
            $feedback_text .= "❌ Automatische Bewertung nicht verfügbar\n";
            $feedback_text .= "Grund: " . $ai_result['message'] . "\n\n";
            $feedback_text .= "📝 Manuelle Bewertung erforderlich.\n\n";
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