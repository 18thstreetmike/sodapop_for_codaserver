<?php
/**
 * The base class for a table or form.
 *
 */
abstract class Sodapop_Database_Table_Abstract {
	
    protected $tableName = null;

    protected $displayName = null;

    public $id = null;

    protected $fields = array();

    protected $oldFields = array();

    protected $fieldDefinitions = array();

    protected $childTableDefinitions = array();

    protected $childTables = array();

    protected $lazyLoaded = false;

    public function  __construct($id = null) {
	$this->id = $id;
    }

    public function __get($name) {
	if (!$lazyLoaded && $id) {
	    $this->loadData();
	}
	if (array_key_exists($name, $this->fieldDefinitions) && $this->fieldDefinitions[$name]['type_name'] != 'REFERENCE' && $this->fieldDefinitions[$name]['array_flag'] == '0') {
	    // this is a regular field
	    return $this->fields[$name];
	} else if (array_key_exists($name, $this->fieldDefinitions) && $this->fieldDefinitions[$name]['type_name'] != 'REFERENCE' && $this->fieldDefinitions[$name]['array_flag'] == '1') {
	    // this is a regular array field
	    return explode('<sodapop_array_item_delim>', str_replace("','", '<sodapop_array_item_delim>', substr($this->fields[$name], 2, strlen($this->fields[$name]) - 4)));
	} else if (array_key_exists($name, $this->fieldDefinitions) && $this->fieldDefinitions[$name]['type_name'] == 'REFERENCE' && $this->fieldDefinitions[$name]['array_flag'] == '0') {
	    // this is a regular reference field
	    $className = Sodapop_Inflector::underscoresToCamelCaps($this->fieldDefinitions[$name]['ref_table_name'], false);
	    return new $className($this->fields[$name]);
	} else if (array_key_exists($name, $this->fieldDefinitions) && $this->fieldDefinitions[$name]['type_name'] == 'REFERENCE' && $this->fieldDefinitions[$name]['array_flag'] == '1') {
	    // this is a regular reference array field
	    $className = Sodapop_Inflector::underscoresToCamelCaps($this->fieldDefinitions[$name]['ref_table_name'], false);
	    $ids = explode('<sodapop_array_item_delim>', str_replace("','", '<sodapop_array_item_delim>', substr($this->fields[$name], 2, strlen($this->fields[$name]) - 4)));
	    $retval = array();
	    foreach ($ids as $item) {
		$retval[] = new $className($item);
	    }
	    return $retval;
	} else if (array_key_exists($name, $this->childTableDefinitions) && $this->childTableDefinitions[$name]['lazyLoaded'] == true) {
	    // this is a set of child tables which have already been loaded
	    return $this->childTables[$name];
	} else if (array_key_exists($name, $this->childTableDefinitions) && $this->childTableDefinitions[$name]['lazyLoaded'] == false) {
	    // this is a set of child tables which has not been loaded
	    $this->childTables[$name] = array();
	    $result = $this->getSubtableChildIds(Sodapop_Inflector::camelCapsToUnderscores($name), $this->id);
	    $className = Sodapop_Inflector::underscoresToCamelCaps(Sodapop_Inflector::camelCapsToUnderscores($name), false);
	    foreach ($result['data'] as $row) {
		$this->childTables[$name][] = new $className ($row[0]);
	    }
	    $this->childTableDefinitions[$name]['lazyLoaded'] = true;
	    return $this->childTables[$name];
	}
    }

    public function __set($name, $value) {
	if (!$lazyLoaded && $id) {
	    $this->loadData();
	}
	if (array_key_exists($name, $this->fieldDefinitions) && $this->fieldDefinitions[$name]['type_name'] != 'REFERENCE' && $this->fieldDefinitions[$name]['array_flag'] == '0') {
	    $this->fields[$name] = $value;
	} else if (array_key_exists($name, $this->fieldDefinitions) && $this->fieldDefinitions[$name]['type_name'] != 'REFERENCE' && $this->fieldDefinitions[$name]['array_flag'] == '1') {
	    $this->fields[$name] = "['".implode("','",$value)."']";
	} else if (array_key_exists($name, $this->fieldDefinitions) && $this->fieldDefinitions[$name]['type_name'] == 'REFERENCE' && $this->fieldDefinitions[$name]['array_flag'] == '0') {
	    $this->fields[$name] = $value->id;
	} else if (array_key_exists($name, $this->fieldDefinitions) && $this->fieldDefinitions[$name]['type_name'] == 'REFERENCE' && $this->fieldDefinitions[$name]['array_flag'] == '1') {
	    // this is a regular reference array field
	    $ids = array();
	    foreach ($value as $item) {
		$ids[] = $item->id;
	    }
	    $this->fields[$name] = "['".implode("','",$ids)."']";
	} else if (array_key_exists($name, $childTableDefinitions)) {
	    $this->childTables[$name] = $value;
	}
	return $this;
    }

    public abstract function loadData();

    public abstract function save();

    public abstract function getSubtableChildIds($subtableName, $parentRowId);

    public function getFieldDefinitions (){
	return $this->fieldDefinitions;
    }

    public function getChildTableDefinitions (){
	return $this->childTableDefinitions;
    }
}