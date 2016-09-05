<?php
/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
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
    const SELECT_MULTI  ='multi';


    /**
    * @var string  path of the plugin's base directory
    */
    protected $plugin_path = '';


    /**
    * Constructor: general initialisations
    */
    public function __construct()
    {
        $this->plugin_path = realpath(dirname(__FILE__).'/..');
    }


    /**
    * Handle an incoming request
    */
    public function handleRequest()
    {
        global $ilAccess; $ilUser;

        try
        {
            if (!$ilAccess->checkAccess('read', '', $_GET[ref_id]))
            {
                $this->respondHTTP(403); // forbidden
            }


            switch($_POST['cmd'])
            {
                case 'saveInput':
                    $this->saveInput();
                    break;

                default:
                    $this->respondHTTP(501); // not implemented
                    break;
            }
        }
        catch (Exception $exception)
        {
            $this->respondHTTP(500, $exception->getMessage());
        }
    }


    /**
     * Save an input that is sent
     */
    protected function saveInput()
    {
        global $ilUser;

        require_once($this->plugin_path.'/classes/class.ilPCInputFieldValue.php');

        $context_type = ilUtil::stripSlashes($_GET['context_type']);
        $context_id = ilUtil::stripSlashes(($_GET['context_id']));
        $field_name = ilUtil::stripSlashes($_GET['field_name']);
        $field_type = ilUtil::stripSlashes($_GET['field_type']);
        $select_type = ilUtil::stripSlashes($_GET['select_type']);

        // save the input (create if not exists)
        $valObj = ilPCInputFieldValue::getByKeys($context_type, $context_id, $ilUser->getId(), $field_name, true);
        if ($field_type == self::FIELD_SELECT)
        {
			$value = ilUtil::stripSlashesArray((array) $_POST['value']);
			if ($select_type == self::SELECT_SINGLE)
			{
				$valObj->field_value = current($value);
			}
			else
			{
				$valObj->field_value = serialize($value);
			}
        }
        else
        {
            $valObj->field_value = ilUtil::stripSlashes($_POST['value']);
        }
        $valObj->save();

        $this->respondHTTP(200, json_encode($valObj->id));
    }


    /**
     * Send a HTTP response
     * @param string  response message
     */
    protected function respondHTTP($status, $message = null)
    {
        switch ($status)
        {
            case 200: $text = 'OK'; break;
            case 400: $text = 'Bad Request'; break;
            case 401: $text = 'Unauthorized'; break;
            case 403: $text = 'Forbidden'; break;
            case 404: $text = 'Not Found'; break;
            case 500: $text = 'Internal Server Error'; break;
            case 501: $text = 'Not Implemented'; break;

        }
        header('HTTP/1.1 '. $status .' '. $text);
        header('Content-type: text/plain');
        echo isset($message) ? $message : $text;
    }
}
