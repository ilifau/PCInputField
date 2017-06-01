<?php
/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

/**
 * Page Component Input Field plugin: saved value
 *
 * @author Fred Neumann <fred.neumann@fim.uni-erlangen.de>
 * @version $Id$
 */
class ilPCInputFieldValue
{
    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $context_type;

    /**
     * @var integer
     */
    public $context_id;

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
    public $field_value;


    /**
     * Get a result by id
     * @param integer $a_id
     * @return ilPCInputFieldValue or null if not exists
     */
    public static function getById($a_id)
    {
        global $ilDB;

        $query = 'SELECT * FROM pcinfi_values'
            .' WHERE id = '. $ilDB->quote($a_id,'integer');

        $res = $ilDB->query($query);
        if ($row = $ilDB->fetchAssoc($res))
        {
            $obj = new ilPCInputFieldValue;
            $obj->fillData($row);
            return $obj;
        }
        else
        {
            return null;
        }
    }


    /**
     * Get a result by keys
     *
     * @param string    $a_context_type
     * @param integer   $a_context_id
     * @param integer   $a_user_id
     * @param string    $a_field_name
     * @param boolean   $a_create   save a new result object result if not exists
     *
     * @return ilPCInputFieldValue
     */
    public static function getByKeys($a_context_type, $a_context_id, $a_user_id, $a_field_name, $a_create = false)
    {
        global $ilDB;

        $query = 'SELECT * FROM pcinfi_values'
            .' WHERE context_type = '. $ilDB->quote($a_context_type,'text')
            .' AND context_id = '. $ilDB->quote($a_context_id,'integer')
            .' AND user_id = '. $ilDB->quote($a_user_id,'integer')
            .' AND field_name = '. $ilDB->quote($a_field_name,'text');

        $res = $ilDB->query($query);
        if ($row = $ilDB->fetchAssoc($res))
        {
            $obj = new ilPCInputFieldValue;
            $obj->fillData($row);
            return $obj;
        }
        elseif ($a_create)
        {
            $obj = new ilPCInputFieldValue;
            $obj->context_type = $a_context_type;
            $obj->context_id = $a_context_id;
            $obj->user_id = $a_user_id;
            $obj->field_name = $a_field_name;
            $obj->field_value = null;
            $obj->save();
            return $obj;
        }
        else
        {
            return null;
        }
    }


    /**
     * Fill the properties with data from an array
     * @param array $data (assoc data)
     */
    protected function fillData($data)
    {
        $this->id = $data['id'];
        $this->context_type = $data['context_type'];
        $this->context_id = $data['context_id'];
        $this->user_id = $data['user_id'];
        $this->field_name = $data['field_name'];
        $this->field_value = $data['field_value'];
    }


    /**
     * Save a value
     */
    public function save()
    {
        global $ilDB;

        if (empty($this->context_type) or empty($this->context_id) or empty($this->user_id) or empty($this->field_name))
        {
            return false;
        }
        if (!isset($this->id))
        {
            $this->id = $ilDB->nextId('pcinfi_values');
        }
        $ilDB->replace('pcinfi_values',
            array(
                'id' => array('integer', $this->id)
            ),
            array(
                'context_type' => array('text', $this->context_type),
                'context_id' => array('integer', $this->context_id),
                'user_id' => array('integer', $this->user_id),
                'field_name' => array('text', $this->field_name),
                'field_value' => array('clob', $this->field_value)
            )
        );
        return true;
    }
} 