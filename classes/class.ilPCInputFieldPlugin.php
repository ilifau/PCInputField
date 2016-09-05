<?php
/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

include_once("./Services/COPage/classes/class.ilPageComponentPlugin.php");
 
/**
 * Page Component Input Field plugin
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */
class ilPCInputFieldPlugin extends ilPageComponentPlugin
{
    /**
     * @var string  context type
     */
    protected $context_type;

    /**
     * @var string  context id
     */
    protected $context_id;

    /**
     * @var string field_name
     */
    protected $field_name;

    /**
     * @var string field type
     */
    protected $field_type;

    /**
     * @var string  select type
     */
    protected $select_type;

	/**
	 * Get plugin name 
	 *
	 * @return string
	 */
	function getPluginName()
	{
		return "PCInputField";
	}
	
	
	/**
	 * Get plugin name 
	 *
	 * @return string
	 */
	function isValidParentType($a_parent_type)
	{
		if (in_array($a_parent_type, array("lm")))
		{
			return true;
		}
		return false;
	}
	
	/**
	 * Get Javascript files
	 */
	function getJavascriptFiles()
	{
        //return array();
		return array("js/pcinfi.js");
	}
	
	/**
	 * Get css files
	 */
	function getCssFiles()
	{
        return array();
		//return array("css/pcinfi.css");
	}

}

?>
