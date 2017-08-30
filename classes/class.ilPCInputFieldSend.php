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
		include_once('./Modules/Exercise/classes/class.ilExAssignment.php');
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
		//Can be sent?
		if (is_null($assignment->getStartTime()) AND (((int)$assignment->getDeadline() - time()) > 0))
		{
			$sendable = TRUE;
		} elseif (is_null($assignment->getDeadline()) AND ((time() - (int)$assignment->getStartTime()) > 0))
		{
			$sendable = TRUE;
		} elseif (((time() - (int)$assignment->getStartTime()) > 0) AND (((int)$assignment->getDeadline() - time()) > 0))
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

		//Create or update submission
		include_once('./Modules/Exercise/classes/class.ilExSubmission.php');
		$exc_submission = new ilExSubmission($assignment, $this->user_id);
		$exc_submission->updateTextSubmission($this->field_value);


		//@see ilExSubmissionBaseGUI::handleNewUpload()
		$exercise->processExerciseStatus(
			$assignment,
			array($this->user_id),
			true);


		// return the date and time of the submission
		$submit_time_raw = $exc_submission->getLastSubmission();
		$submit_time = ($submit_time_raw ? new ilDateTime($submit_time_raw, IL_CAL_DATETIME) : '');
		return ilDatePresentation::formatDate($submit_time);
	}

} 