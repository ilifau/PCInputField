<?php
/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

include_once("./Services/COPage/classes/class.ilPageComponentPluginGUI.php");

/**
 * Page Component Input Field  plugin GUI
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
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


	/**
	 * Execute command
	 *
	 * @param
	 * @return
	 */
	public function executeCommand()
	{
		global $ilCtrl;

		$next_class = $ilCtrl->getNextClass();

		switch ($next_class)
		{
			// glossary selection in properties form
			case "ilpropertyformgui":
				$form = $this->initSendForm();
				$ilCtrl->setReturn($this, "updateExerciseRefId");
				$ilCtrl->forwardCommand($form);

				return;

			default:
				// perform valid commands
				$cmd = $ilCtrl->getCmd();
				if (in_array($cmd, array("create", "save", "edit", "send", "update", "updateSend", "updateExerciseRefId", "cancel")))
				{
					$this->$cmd();
				}
				break;
		}
	}


	/**
	 * Create
	 *
	 * @param
	 * @return
	 */
	public function insert()
	{
		global $tpl;

		$form = $this->initForm(true);
		$tpl->setContent($form->getHTML());
	}

	/**
	 * Save new pc input
	 */
	public function create()
	{
		global $tpl, $lng, $ilCtrl;

		$form = $this->initForm(true);
		if ($form->checkInput())
		{
			$properties = array('field_name' => $form->getInput('field_name'), 'field_type' => $form->getInput('field_type'), 'field_size' => $form->getInput('field_size'), 'field_maxlength' => $form->getInput('field_maxlength'), 'field_cols' => $form->getInput('field_cols'), 'field_rows' => $form->getInput('field_rows'), 'select_type' => $form->getInput('select_type'), 'select_choices' => serialize($form->getInput('select_choices')), 'field_context' => $form->getInput('field_context'),);
			if ($this->createElement($properties))
			{
				ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
				$this->returnToParent();
			}
		}
		$form->setValuesByPost();
		$tpl->setContent($form->getHtml());
	}

	/**
	 * Edit
	 *
	 * @param
	 * @return
	 */
	public function edit()
	{
		global $tpl;

		$this->setTabs("edit");
		$form = $this->initForm();
		$tpl->setContent($form->getHTML());
	}

	/**
	 * Update
	 *
	 * @param
	 * @return
	 */
	public function update()
	{

		global $tpl, $lng, $ilCtrl;

		$form = $this->initForm(true);
		if ($form->checkInput())
		{
			//Update only setting not related to sending to exercise
			$existing_properties = $this->getProperties();
			$properties = array('field_name' => $form->getInput('field_name'), 'field_type' => $form->getInput('field_type'), 'field_size' => $form->getInput('field_size'), 'field_maxlength' => $form->getInput('field_maxlength'), 'field_cols' => $form->getInput('field_cols'), 'field_rows' => $form->getInput('field_rows'), 'select_type' => $form->getInput('select_type'), 'select_choices' => serialize($form->getInput('select_choices')), 'field_context' => $form->getInput('field_context'),);

			foreach ($existing_properties as $property_name => $value)
			{
				if (key_exists($property_name, $properties))
				{
					$existing_properties[$property_name] = $properties[$property_name];
				}
			}

			if ($this->updateElement($existing_properties))
			{
				ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
				$this->returnToParent();
			}
		}
		$form->setValuesByPost();
		$tpl->setContent($form->getHtml());
	}

	public function send()
	{
		global $tpl;
		$this->setTabs("send");
		$form = $this->initSendForm();
		$tpl->setContent($form->getHTML());
	}

	/**
	 * Update
	 *
	 * @param
	 * @return
	 */
	public function updateSend()
	{
		global $tpl, $lng, $ilCtrl;

		$form = $this->initSendForm(true);

		if ($form->checkInput())
		{
			$exercise = array('select_exercise' => $form->getInput('select_exercise'));

			//if exercise is selected, select assignment
			if ((int)$form->getInput('select_exercise') > 0)
			{
				$assignment = array('select_assignment' => $form->getInput('select_assignment'));
			} else
			{
				$assignment = array('select_assignment' => "0");
			}

			$existing_properties = $this->getProperties();

			//Update also non-related settings
			if ($this->updateElement(array_merge($existing_properties, $exercise, $assignment)))
			{
				ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);

				$this->send();

				return;

			}

		}
		$form->setValuesByPost();
		$tpl->setContent($form->getHtml());
	}


	/**
	 * Save the exercise reference coming from the repository selector
	 * This is needed by the selector up to ILIAS 5.1
	 */
	public function updateExerciseRefId()
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


	/**
	 * Init editing form
	 *
	 * @param        int $a_mode Edit Mode
	 */
	protected function initForm($a_create = false)
	{
		global $lng, $ilCtrl;

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();

		// field name
		$name = new ilTextInputGUI($this->txt('field_name'), 'field_name');
		$name->setMaxLength(40);
		$name->setSize(40);
		$name->setRequired(true);
		$form->addItem($name);

		// field type
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

		// field context
		$context = new ilRadioGroupInputGUI($this->txt('field_context'), 'field_context');
		$context->setInfo($this->txt('field_context_info'));
		$page = new ilRadioOption($this->txt('context_page'), self::CONTEXT_PAGE);
		$context->addOption($page);
		$module = new ilRadioOption($this->txt('context_module'), self::CONTEXT_MODULE);
		$context->addOption($module);
		$course = new ilRadioOption($this->txt('context_course'), self::CONTEXT_COURSE);
		$context->addOption($course);
		$form->addItem($context);

		if ($a_create)
		{
			$name->setValue('');
			$type->setValue(self::FIELD_TEXT);
			$size->setValue(50);
			$maxlength->setValue(250);
			$cols->setValue(50);
			$rows->setValue(5);
			$select_type->setValue(self::SELECT_SINGLE);
			$select_choices->setValue(array());
			$context->setValue(self::CONTEXT_PAGE);

		} else
		{
			$prop = $this->getProperties();
			$name->setValue($prop['field_name']);
			$type->setValue($prop['field_type']);
			$size->setValue($prop['field_size']);
			$maxlength->setValue($prop['field_maxlength']);
			$cols->setValue($prop['field_cols']);
			$rows->setValue($prop['field_rows']);
			$select_type->setValue($prop['select_type']);
			$select_choices->setValue((array)unserialize($prop['select_choices']));
			$context->setValue($prop["field_context"]);
		}

		// save and cancel commands
		if ($a_create)
		{
			$this->addCreationButton($form);
			$form->addCommandButton("cancel", $lng->txt("cancel"));
			$form->setTitle($this->txt("cmd_insert"));
		} else
		{
			$form->addCommandButton("update", $lng->txt("save"));
			$form->addCommandButton("cancel", $lng->txt("cancel"));
			$form->setTitle($this->txt("edit_input_field"));
		}

		$form->setFormAction($ilCtrl->getFormAction($this));

		return $form;

	}

	/**
	 * Init send to exercise form
	 *
	 * @param        int $a_mode Edit Mode
	 */
	protected function initSendForm($a_create = false)
	{
		global $lng, $ilCtrl;

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();

		$prop = $this->getProperties();

		include_once("./Services/Form/classes/class.ilRepositorySelectorInputGUI.php");
		$exercise_selector = new ilRepositorySelectorInputGUI($this->txt('select_exercise'), 'select_exercise');
		$exercise_selector->setClickableTypes(array("exc"));
		$form->addItem($exercise_selector);


		//If exercise is selected, show assigments
		if ((int)$prop['select_exercise'] > 0)
		{
			$ex_ref_id = (int)$prop['select_exercise'];
			$ex_obj_id = ilObject::_lookupObjectId($ex_ref_id);

			$exercise_selector->setValue($ex_ref_id);

			include_once("./Modules/Exercise/classes/class.ilExAssignment.php");
			$assignments_list = ilExAssignment::getAssignmentDataOfExercise($ex_obj_id);
			$selected_assignment = null;
			include_once("./Services/Form/classes/class.ilSelectInputGUI.php");
			$assignment_selector = new ilSelectInputGUI($this->txt('select_assignment'), "select_assignment");

			$assignment_array = array();
			$assignment_array["0"] = $this->txt('no_assignment_selected');
			foreach ($assignments_list as $assignment)
			{
				//Take only those with type 5 "text"
				if ($assignment["type"] == "5")
				{
					$assignment_array[$assignment["id"]] = $assignment["title"];
					if (isset($prop['select_assignment']) AND ((int)$assignment["id"] == (int)$prop['select_assignment']))
					{
						$selected_assignment = new ilExAssignment((int)$assignment["id"]);
					}
				}
			}

			$assignment_selector->setOptions($assignment_array);
			$assignment_selector->setValue($prop['select_assignment']);

			$form->addItem($assignment_selector);

			//Schedule information of the selected assignment if exists
			if (is_a($selected_assignment, 'ilExAssignment'))
			{
				//Start time schedule
				$schedule_start = new ilNonEditableValueGUI($this->txt('assignment_schedule_start'), 'schedule_start_date');
				if ((int)$selected_assignment->getStartTime())
				{
					$start_date = new DateTime();
					$start_date->setTimestamp((int)$selected_assignment->getStartTime());
					$schedule_start->setValue($start_date->format('d.m.Y H:i:s'));
				} else
				{
					$schedule_start->setValue($this->txt('assignment_schedule_no_start_time'));
				}

				//Deadline schedule
				$schedule_deadline = new ilNonEditableValueGUI($this->txt('assignment_schedule_deadline'), 'schedule_deadline');
				if ((int)$selected_assignment->getDeadline())
				{
					$deadline_date = new DateTime();
					$deadline_date->setTimestamp((int)$selected_assignment->getDeadline());
					$schedule_deadline->setValue($deadline_date->format('d.m.Y H:i:s'));
				} else
				{
					$schedule_deadline->setValue($this->txt('assignment_schedule_no_deadline'));
				}

				//Add hidden timestamp
				$start_timestamp = new ilHiddenInputGUI('schedule_start_date_timestamp');
				$start_timestamp->setValue($selected_assignment->getStartTime());

				$deadline_timestamp = new ilHiddenInputGUI('schedule_deadline_timestamp');
				$deadline_timestamp->setValue($selected_assignment->getDeadline());

				//Path to exercise
				$link_to_exercise = new ilLocatorGUI();
				$link_to_exercise->addContextItems($ex_ref_id);
				$path_to_exercise = new ilNonEditableValueGUI($this->txt('path_to_related_exercise'), 'path_to_exercise');
				$path_to_exercise->setInfo($link_to_exercise->getHTML());

				//Add to form
				$form->addItem($schedule_start);
				$form->addItem($schedule_deadline);
				$form->addItem($start_timestamp);
				$form->addItem($deadline_timestamp);
				$form->addItem($path_to_exercise);
			}
		}


		// save and cancel commands
		if ($a_create)
		{
			$this->addCreationButton($form);
			$form->addCommandButton("cancel", $lng->txt("cancel"));
			$form->setTitle($this->txt("cmd_insert"));
		} else
		{
			$form->addCommandButton("updateSend", $lng->txt("save"));
			$form->addCommandButton("cancel", $lng->txt("cancel"));
			$form->setTitle($this->txt("send_to_exercise"));
		}

		$form->setFormAction($ilCtrl->getFormAction($this));

		return $form;
	}

	/**
	 * Cancel
	 */
	public function cancel()
	{
		$this->returnToParent();
	}


	/**
	 * Set tabs
	 *
	 * @param
	 * @return
	 */
	public function setTabs($a_active)
	{
		global $ilTabs, $ilCtrl;

		$pl = $this->getPlugin();

		$ilTabs->addTab("edit", $pl->txt("settings"), $ilCtrl->getLinkTarget($this, "edit"));

		$ilTabs->addTab("send", $pl->txt("send_to_exercise"), $ilCtrl->getLinkTarget($this, "send"));

		$ilTabs->activateTab($a_active);
	}

	/**
	 * Get a plugin text
	 * @param $a_var
	 * @return mixed
	 */
	protected function txt($a_var)
	{
		return $this->getPlugin()->txt($a_var);
	}


	protected function getJSTexts()
	{
		return array('submitted' => $this->plugin->txt('submitted'), 're_submit' => $this->plugin->txt('re_submit'), 'submit_success' => $this->plugin->txt('submit_success'));
	}

	/**
	 * Get HTML for element
	 *
	 * @param string    page mode (edit, presentation, print, preview, offline)
	 * @return string   html code
	 */
	public function getElementHTML($a_mode, array $a_properties, $a_plugin_version)
	{
		global $ilCtrl, $ilUser, $lng, $tpl;

		// determine the context
		$context_type = $a_properties['field_context'];
		$context_id = $this->getContextId($context_type, $a_mode);

		// get the value for the context
		$this->getPlugin()->includeClass('class.ilPCInputFieldValue.php');
		$valObj = ilPCInputFieldValue::getByKeys($context_type, $context_id, $ilUser->getId(), $a_properties['field_name'], false);
		if ($a_properties['field_type'] == self::FIELD_SELECT and $a_properties['select_type'] == self::SELECT_MULTI)
		{
			try
			{
				$value = (array)unserialize($valObj->field_value);
			} catch (Exception $e)
			{
				$value = array();
			}
		} else
		{
			$value = $valObj->field_value;
		}


		// adding the javascript here as url allows to add the plugin versio nas parameter
		// increase the plugin version number with each change in the javascript file
		$tpl->addJavaScript(ILIAS_HTTP_PATH . '/' . $this->plugin->getDirectory() . '/js/pcinfi.js?plugin_version=' . $this->plugin->getVersion());
		$tpl->addOnLoadCode('il.PCInputField.init(' . json_encode($this->getJSTexts()) . ');');

		$ctpl = $this->getPlugin()->getTemplate("tpl.content.html");


//        // debugging output -----------------------------------
//        $a_properties['context_type'] = $context_type;
//        $a_properties['context_id'] = $context_id;
//        $a_properties['value'] = $value;
//
//        foreach ($_GET as $name => $param)
//        {
//            $a_properties['GET '.$name] = $param;
//        }
//
//        foreach ($a_properties as $name => $property)
//        {
//            $ctpl->setCurrentBlock("prop");
//            $ctpl->setVariable("TXT_PROP", $this->getPlugin()->txt("property"));
//            $ctpl->setVariable("PROP_NAME", $name);
//            $ctpl->setVariable("PROP_VAL", $property);
//            $ctpl->parseCurrentBlock();
//        }
//        $ctpl->setCurrentBlock("debug");
//        $ctpl->setVariable("TXT_VERSION", $this->getPlugin()->txt("content_plugin_version"));
//        $ctpl->setVariable("TXT_MODE", $this->getPlugin()->txt("mode"));
//        $ctpl->setVariable("VERSION", $a_plugin_version);
//        $ctpl->setVariable("MODE", $a_mode);
//        $ctpl->parseCurrentBlock();
//        // ---------------------------------------------------

		// set input element(s)
		$name = rand(0, 9999999);

		if ($a_mode == self::MODE_EDIT)
		{
			$ctpl->setCurrentBlock('edit');
			$ctpl->setVariable('FIELD_NAME', $a_properties['field_name']);
			switch ($a_properties['field_context'])
			{
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


		switch ($a_properties['field_type'])
		{
			case self::FIELD_TEXT:
				$ctpl->setCurrentBlock('text');
				$ctpl->setVariable('ID', rand(0, 9999999));
				$ctpl->setVariable('NAME', $name);
				$ctpl->setVariable('SIZE', $a_properties['field_size']);
				$ctpl->setVariable('MAXLENGTH', $a_properties['field_maxlength']);
				$ctpl->setVariable('VALUE', ilUtil::prepareFormOutput($value));
				$ctpl->parseCurrentBlock();
				break;

			case self::FIELD_TEXTAREA:
				$ctpl->setCurrentBlock('textarea');
				$ctpl->setVariable('ID', rand(0, 9999999));
				$ctpl->setVariable('NAME', $name);
				$ctpl->setVariable('COLS', $a_properties['field_cols']);
				$ctpl->setVariable('ROWS', $a_properties['field_rows']);
				$ctpl->setVariable('VALUE', ilUtil::prepareFormOutput($value));
				$ctpl->parseCurrentBlock();
				break;

			case self::FIELD_SELECT:
				$choices = (array)unserialize($a_properties['select_choices']);
                $ctpl->setCurrentBlock('single_choice');
                $ctpl->setVariable('NAME', $name);
                $ctpl->parseCurrentBlock();

                foreach ($choices as $choice)
				{
					switch ($a_properties['select_type'])
					{
						case self::SELECT_SINGLE:
							$ctpl->setCurrentBlock('single_choice_options');
							$ctpl->setVariable('ID', rand(0, 9999999));
							$ctpl->setVariable('NAME', $name);
							$ctpl->setVariable("VALUE", ilUtil::prepareFormOutput($choice));
							if ($choice == $value)
							{
								$ctpl->setVariable('CHECKED', 'checked="checked"');
							}
							$ctpl->parseCurrentBlock();
							break;

						case self::SELECT_MULTI:
							$ctpl->setCurrentBlock('multi_choice');
							$ctpl->setVariable('ID', rand(0, 9999999));
							$ctpl->setVariable('NAME', $name);
							$ctpl->setVariable("VALUE", ilUtil::prepareFormOutput($choice));
							if (in_array($choice, (array)$value))
							{
								$ctpl->setVariable('CHECKED', 'checked="checked"');
							}
							$ctpl->parseCurrentBlock();
							break;
					}
				}
                $ctpl->setCurrentBlock('single_choice');
                $ctpl->parseCurrentBlock();

				break;
		}

		// set wrapping div
		switch ($a_mode)
		{
			case self::MODE_PREVIEW:
			case self::MODE_PRESENTATION:
				$service_url = ILIAS_HTTP_PATH . '/' . $this->getPlugin()->getDirectory() . '/service.php' . '?client_id=' . CLIENT_ID . '&amp;ref_id=' . (int)$_GET['ref_id'] . '&amp;context_type=' . urlencode($context_type) . '&amp;context_id=' . urlencode($context_id) . '&amp;field_name=' . urlencode($a_properties['field_name']) . '&amp;field_type=' . urlencode($a_properties['field_type']) . '&amp;select_type=' . urlencode($a_properties['select_type']);

				$ctpl->setVariable('MODE_CLASS', 'ilPCInputFieldActive');
				$ctpl->setVariable('SERVICE_URL', $service_url);
				$ctpl->setVariable('FIELD_TYPE', $a_properties['field_type']);

				$ctpl->setVariable('TXT_SAVING', $this->txt('saving'));
				$ctpl->setVariable('IMG_LOADER', ilUtil::getImagePath("loader.svg"));
				break;

			case self::MODE_EDIT:
			case self::MODE_OFFLINE:
			case self::MODE_PRINT:
				$ctpl->setVariable('MODE_CLASS', 'ilPCInputFieldInactive');
				break;

		}

		//Exercise related fields
		if (isset($a_properties['select_exercise']) AND isset($a_properties['select_assignment']))
		{
			if ((int)$a_properties['select_exercise'] AND (int)$a_properties['select_assignment'])
			{
				include_once("./Modules/Exercise/classes/class.ilExAssignment.php");
				$obj_id = ilObject::_lookupObjId($a_properties['select_exercise']);
				$assignments_list = ilExAssignment::getAssignmentDataOfExercise($obj_id);
				include_once("./Services/Form/classes/class.ilSelectInputGUI.php");

				$assignment_array = array();
				$assignment_array["0"] = $this->txt('no_assignment_selected');
				$selected_assignment = null;
				foreach ($assignments_list as $assignment)
				{
					//Take only those with type 5 "text"
					if ($assignment["type"] == "5")
					{
						$assignment_array[$assignment["id"]] = $assignment["title"];
						if (isset($a_properties['select_assignment']) AND ((int)$assignment["id"] == (int)$a_properties['select_assignment']))
						{
							$selected_assignment = new ilExAssignment((int)$assignment["id"]);
							break;
						}
					}
				}

				if (is_a($selected_assignment, 'ilExAssignment'))
				{
					$start_date = new ilDateTime($selected_assignment->getStartTime(), IL_CAL_UNIX);
					$deadline = new ilDateTime($selected_assignment->getDeadline(), IL_CAL_UNIX);

					$submit_time_raw = $this->getLastSubmission($selected_assignment);
					$submit_time = ($submit_time_raw ? new ilDateTime($submit_time_raw, IL_CAL_DATETIME) : '');

					//Can be sent?
					if (is_null($selected_assignment->getStartTime()) AND (((int)$selected_assignment->getDeadline() - time()) > 0))
					{
						$sendable = TRUE;
					} elseif (is_null($selected_assignment->getDeadline()) AND ((time() - (int)$selected_assignment->getStartTime()) > 0))
					{
						$sendable = TRUE;
					} elseif (((time() - (int)$selected_assignment->getStartTime()) > 0) AND (((int)$selected_assignment->getDeadline() - time()) > 0))
					{
						$sendable = TRUE;
					} else
					{
						$sendable = FALSE;
					}


					if ($sendable)
					{
						//Add (re-)submit button
						$ctpl->setCurrentBlock('submission');
						$ctpl->setVariable('BUTTON_ID', $name . '_' . $selected_assignment->getExerciseId() . '_' . $selected_assignment->getId());
						$ctpl->setVariable('VALUE', $this->plugin->txt($submit_time_raw ? 're_submit' : 'submit'));
						$ctpl->setVariable('CMD', 'cmd[sendInput]');
						$ctpl->parseCurrentBlock();

						//Add confirmation Modal if sendable but keep it hidden
						$ctpl->setCurrentBlock('confirmation');
						$ctpl->setVariable('NAME', $name);
						$ctpl->setVariable('CONFIRMATION_MODAL', $this->getConfirmationModal($name));
						$ctpl->parseCurrentBlock();
					}

					// show status
					$ctpl->setCurrentBlock('status');
					$ctpl->setVariable('NAME', $name);
					$ctpl->setVariable('STATUS', $this->plugin->txt($submit_time_raw ? 'submitted' : 'not_yet_submitted'));
					if ($submit_time_raw)
					{
						$ctpl->setVariable('TIME', ilDatePresentation::formatDate($submit_time));

					}
					$ctpl->parseCurrentBlock();


					//Add start date info
					if ((int)$selected_assignment->getStartTime())
					{
						$ctpl->setCurrentBlock('start_time');
						$ctpl->setVariable('START_DATE', $this->plugin->txt('assignment_schedule_start') . ': ');
						$ctpl->setVariable('START_DATE_VALUE', ilDatePresentation::formatDate($start_date));
						$ctpl->parseCurrentBlock();
					}

					//add deadline info
					if ((int)$selected_assignment->getDeadline())
					{
						$ctpl->setCurrentBlock('deadline');
						$ctpl->setVariable('DEADLINE', $this->plugin->txt('assignment_schedule_deadline') . ': ');
						$ctpl->setVariable('DEADLINE_VALUE', ilDatePresentation::formatDate($deadline));
						$ctpl->parseCurrentBlock();
					}
/*
					//add evaluation info
					$eval = $selected_assignment->getMemberStatus()->getStatus();
					$pic = $selected_assignment->getMemberStatus()->getStatusIcon();
					$ctpl->setCurrentBlock('evaluation');
					$ctpl->setVariable('IMG_EVAL', ilUtil::getImagePath($pic));
					$ctpl->setVariable('EVALUATION', $lng->txt("exc_" . $eval), true);
					$ctpl->setVariable('NAME', $name);
					$ctpl->parseCurrentBlock();*/
				}
			}

		}

		return $ctpl->get();
	}


	/**
	 * Get the id of the field context
	 * @param   string      context type
	 * @param   string      mode
	 * @return  integer     if of the context object
	 */
	protected function getContextId($a_context_type, $a_mode)
	{
		global $tree;

		// id of the current page (should be calculated only once per page)
		static $page_id = null;

		// id of the parent course (should be calculated only once per page)
		static $course_id = null;

		// only get a context for presentation or preview
		if ($a_mode != self::MODE_PRESENTATION and $a_mode != self::MODE_PREVIEW)
		{
			return 0;
		}

		// determine the context
		switch ($a_context_type)
		{
			case self::CONTEXT_PAGE:

				switch ($a_mode)
				{
					case self::MODE_PREVIEW:
						$context_id = $_GET['obj_id'];
						break;

					case self::MODE_PRESENTATION:
						if (!isset($page_id))
						{
							// not nice, but no other chance to get the page id
							$gui = new ilLMPresentationGUI;
							$page_id = $gui->getCurrentPageId();
							unset($gui);
						}
						$context_id = $page_id;
						break;

					default:
						// page cannot be determined
						$context_id = '0';
				}
				break;

			case self::CONTEXT_MODULE:
				$context_id = ilObject::_lookupObjId($_GET['ref_id']);
				break;

			case self::CONTEXT_COURSE:
				if (!isset($course_id))
				{
					$path = array_reverse($tree->getPathFull($_GET['ref_id']));
					foreach ($path as $key => $row)
					{
						if ($row['type'] == 'crs')
						{
							$course_id = ilObject::_lookupObjId($row['child']);
							break;
						}
					}
					if (!isset($course_id))
					{
						// not in a course: take the learning module
						$course_id = ilObject::_lookupObjId($_GET['ref_id']);
					}
				}
				$context_id = $course_id;
				break;
		}

		return $context_id;
	}

	protected function isAlreadySubmitted($assignment_id)
	{
		global $ilDB, $ilUser;

		$user_id = $ilUser->getId();
		$user_ids = array();

		$set = $ilDB->query("SELECT DISTINCT(user_id)" . " FROM exc_returned" . " WHERE ass_id = " . $ilDB->quote($assignment_id, "integer") . " AND user_id = " . $ilDB->quote($user_id, "integer"));
		while ($row = $ilDB->fetchAssoc($set))
		{
			$user_ids[] = $row["user_id"];
		}

		if (sizeof($user_ids))
		{
			return TRUE;
		} else
		{
			return FALSE;
		}
	}


	protected function getLastSubmission($assignmentObject)
	{
		global $ilUser;

		require_once('Modules/Exercise/classes/class.ilExSubmission.php');
		$subObj = new ilExSubmission($assignmentObject, $ilUser->getId());

		return $subObj->getLastSubmission();
	}

	protected function getConfirmationModal($name)
	{
		require_once 'Services/UIComponent/Modal/classes/class.ilModalGUI.php';

		$tpl = new ilTemplate('./Customizing/global/plugins/Services/COPage/PageComponent/PCInputField/templates/tpl.confirm.html', true, true);

		$button = ilLinkButton::getInstance();
		$button->setId('pcinfi_send_button');
		$button->setUrl('#');
		$button->setCaption($this->txt("submit"), false);
		$button->setPrimary(true);
		$tpl->setCurrentBlock('buttons');
		$tpl->setVariable('BUTTON', $button->render());
		$tpl->parseCurrentBlock();

		$button = ilLinkButton::getInstance();
		$button->setId('pcinfi_cancel_button');
		$button->setUrl('#');
		$button->setCaption('cancel');
		$button->setPrimary(false);
		$tpl->setCurrentBlock('buttons');
		$tpl->setVariable('BUTTON', $button->render());
		$tpl->parseCurrentBlock();

		$modal = ilModalGUI::getInstance();
		$modal->setId('pcinfi_' . $name . '_confirmation');
		$modal->setHeading($this->txt('save_on_navigation'));
		$modal->setBody($tpl->get());

		return $modal->getHTML();
	}
}

?>