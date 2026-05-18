<#1>
<?php
/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

/**
 * Page Component Input Field plugin: database update script
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */ 

/**
 * Field values
 */
if(!$ilDB->tableExists('pcinfi_values'))
{
    $fields = array(

        'id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
            'default' => 0
        ),

        'context_type' => array(
            'type' => 'text',
            'length' => 10,
            'notnull' => true,
        ),

        'context_id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
        ),
        'user_id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
        ),
        'field_name' => array(
            'type' => 'text',
            'length' => 40,
            'notnull' => true,
            'default' => ''
        ),
        'field_value' => array(
            'type' => 'clob',
            'notnull' => false,
        )
    );
    $ilDB->createTable('pcinfi_values', $fields);
    $ilDB->addPrimaryKey('pcinfi_values', array('id'));
    $ilDB->createSequence('pcinfi_values');
    $ilDB->addIndex('pcinfi_values', array('context_type', 'context_id'), 'con');
    $ilDB->addIndex('pcinfi_values', array('user_id'), 'usr');
    $ilDB->addIndex('pcinfi_values', array('field_name'), 'fld');
}
?>