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
			default:
				// perform valid commands
				$cmd = $ilCtrl->getCmd();
				if (in_array($cmd, array("create", "save", "edit", "send", "update", "updateSend", "cancel")))
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
		$pg = $this->getPCGUI()->getContentObject()->getPage();
		$tpl->setContent($html . $form->getHTML());
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
		$pg = $this->getPCGUI()->getContentObject()->getPage();
		$tpl->setContent($html . $form->getHTML());
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
				$this->returnToParent();
			}

		}
		$form->setValuesByPost();
		$tpl->setContent($form->getHtml());
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

		//field exercise selector
		include_once("./Services/Form/classes/class.ilRepositorySelector2InputGUI.php");
		$exercise_selector = new ilRepositorySelector2InputGUI($this->txt('select_exercise'), 'select_exercise');
		$exercise_selector->getExplorerGUI()->setRootId(1);
		//Add types of objects that should be shown
		$exercise_selector->getExplorerGUI()->setTypeWhiteList(array_merge(array("exc"), array("root", "cat", "grp", "fold", "crs")));
		//Add types of objects that can be selected
		$exercise_selector->getExplorerGUI()->setSelectableTypes(array("exc"));
		$form->addItem($exercise_selector);


		//If exercise is selected, show assigments
		if ((int)$prop['select_exercise'] > 0)
		{
			$exercise_selector->setValue($prop['select_exercise']);

			include_once("./Modules/Exercise/classes/class.ilExAssignment.php");
			$exercise = ilObjectFactory::getInstanceByRefId((int)$prop['select_exercise']);
			$assignments_list = ilExAssignment::getAssignmentDataOfExercise($exercise->getId());
			include_once("./Services/Form/classes/class.ilSelectInputGUI.php");
			$assignment_selector = new ilSelectInputGUI("select_assignment", "select_assignment");

			$assignment_array = array();
			$assignment_array["0"] = $this->txt('no_assignment_selected');
			foreach ($assignments_list as $assignment)
			{
				$assignment_array[$assignment["id"]] = $assignment["title"];
			}

			$assignment_selector->setOptions($assignment_array);
			$assignment_selector->setValue($prop['select_assignment']);

			$form->addItem($assignment_selector);
		}


		if ($a_create)
		{


		} else
		{

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


	/**
	 * Get HTML for element
	 *
	 * @param string    page mode (edit, presentation, print, preview, offline)
	 * @return string   html code
	 */
	public function getElementHTML($a_mode, array $a_properties, $a_plugin_version)
	{
		global $ilCtrl, $ilUser;

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


		$tpl = $this->getPlugin()->getTemplate("tpl.content.html");

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
//            $tpl->setCurrentBlock("prop");
//            $tpl->setVariable("TXT_PROP", $this->getPlugin()->txt("property"));
//            $tpl->setVariable("PROP_NAME", $name);
//            $tpl->setVariable("PROP_VAL", $property);
//            $tpl->parseCurrentBlock();
//        }
//        $tpl->setCurrentBlock("debug");
//        $tpl->setVariable("TXT_VERSION", $this->getPlugin()->txt("content_plugin_version"));
//        $tpl->setVariable("TXT_MODE", $this->getPlugin()->txt("mode"));
//        $tpl->setVariable("VERSION", $a_plugin_version);
//        $tpl->setVariable("MODE", $a_mode);
//        $tpl->parseCurrentBlock();
//        // ---------------------------------------------------

		if ($a_mode == self::MODE_EDIT)
		{
			$tpl->setCurrentBlock('edit');
			$tpl->setVariable('FIELD_NAME', $a_properties['field_name']);
			switch ($a_properties['field_context'])
			{
				case self::CONTEXT_PAGE:
					$tpl->setVariable('FIELD_CONTEXT', $this->txt('context_page_short'));
					break;
				case self::CONTEXT_MODULE:
					$tpl->setVariable('FIELD_CONTEXT', $this->txt('context_module_short'));
					break;
				case self::CONTEXT_COURSE:
					$tpl->setVariable('FIELD_CONTEXT', $this->txt('context_course_short'));
					break;
			}
			$tpl->parseCurrentBlock();
		}

		// set input element(s)
		$name = rand(0, 9999999);
		switch ($a_properties['field_type'])
		{
			case self::FIELD_TEXT:
				$tpl->setCurrentBlock('text');
				$tpl->setVariable('ID', rand(0, 9999999));
				$tpl->setVariable('NAME', $name);
				$tpl->setVariable('SIZE', $a_properties['field_size']);
				$tpl->setVariable('MAXLENGTH', $a_properties['field_maxlength']);
				$tpl->setVariable('VALUE', ilUtil::prepareFormOutput($value));
				$tpl->parseCurrentBlock();
				break;

			case self::FIELD_TEXTAREA:
				$tpl->setCurrentBlock('textarea');
				$tpl->setVariable('ID', rand(0, 9999999));
				$tpl->setVariable('NAME', $name);
				$tpl->setVariable('COLS', $a_properties['field_cols']);
				$tpl->setVariable('ROWS', $a_properties['field_rows']);
				$tpl->setVariable('VALUE', ilUtil::prepareFormOutput($value));
				$tpl->parseCurrentBlock();
				break;

			case self::FIELD_SELECT:
				$choices = (array)unserialize($a_properties['select_choices']);
				foreach ($choices as $choice)
				{
					switch ($a_properties['select_type'])
					{
						case self::SELECT_SINGLE:
							$tpl->setCurrentBlock('single_choice');
							$tpl->setVariable('ID', rand(0, 9999999));
							$tpl->setVariable('NAME', $name);
							$tpl->setVariable("VALUE", ilUtil::prepareFormOutput($choice));
							if ($choice == $value)
							{
								$tpl->setVariable('CHECKED', 'checked="checked"');
							}
							$tpl->parseCurrentBlock();
							break;

						case self::SELECT_MULTI:
							$tpl->setCurrentBlock('multi_choice');
							$tpl->setVariable('ID', rand(0, 9999999));
							$tpl->setVariable('NAME', $name);
							$tpl->setVariable("VALUE", ilUtil::prepareFormOutput($choice));
							if (in_array($choice, (array)$value))
							{
								$tpl->setVariable('CHECKED', 'checked="checked"');
							}
							$tpl->parseCurrentBlock();
							break;
					}
				}
				break;
		}

		// set wrapping div
		switch ($a_mode)
		{
			case self::MODE_PREVIEW:
			case self::MODE_PRESENTATION:
				$service_url = ILIAS_HTTP_PATH . '/' . $this->getPlugin()->getDirectory() . '/service.php' . '?client_id=' . CLIENT_ID . '&amp;ref_id=' . (int)$_GET['ref_id'] . '&amp;context_type=' . urlencode($context_type) . '&amp;context_id=' . urlencode($context_id) . '&amp;field_name=' . urlencode($a_properties['field_name']) . '&amp;field_type=' . urlencode($a_properties['field_type']) . '&amp;select_type=' . urlencode($a_properties['select_type']);

				$tpl->setVariable('MODE_CLASS', 'ilPCInputFieldActive');
				$tpl->setVariable('SERVICE_URL', $service_url);
				$tpl->setVariable('FIELD_TYPE', $a_properties['field_type']);

				$tpl->setVariable('TXT_SAVING', $this->txt('saving'));
				$tpl->setVariable('IMG_LOADER', ilUtil::getImagePath("loader.svg"));
				break;

			case self::MODE_EDIT:
			case self::MODE_OFFLINE:
			case self::MODE_PRINT:
				$tpl->setVariable('MODE_CLASS', 'ilPCInputFieldInactive');
				break;

		}

		return $tpl->get();
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
}

?>