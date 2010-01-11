<?php
/**
 * Description of Abstract
 *
 * @author michaelarace
 */
abstract class Sodapop_Database_Form_Abstract extends Sodapop_Database_Table_Abstract {

	protected $formStatuses = array();

	public function __set($name, $value) {
		if ($name == 'status_id' || $name == 'status') {
			throw new Exception('Cannot set status directly. Call appropriate method instead.');
		}
		parent::_set($name, $value);
	}

	public function __call($name,  $arguments) {
		if (isset($arguments[0]) && is_array($arguments[0])) {
			foreach ($arguments[0] as $key =>$value) {
				if(array_key_exists($key, $this->fieldDefinitions) && (($this->fieldDefinitions[$key]['array_flag'] == 0 && !is_array($value)) || ($this->fieldDefinitions[$key]['array_flag'] == 1 && is_array($value)))) {
					$this->fields[$name] = $value;
				}
			}
		}
		if ($name == 'update') {
			$this->save(strtoupper($name));
		} else {
			foreach($this->formStatuses as $formStatus) {
				if (strtoupper($name) == strtoupper($formStatus['verb_status_name'])) {
					$this->save(strtoupper($name));
					break;
				}
			}
		}
	}

	public function getStatus() {
		return $this->formStatuses[$this->status_id];
	}

	public function getValidActions() {
		$retval = array();
		foreach($this->formStatuses[$this->status_id]['leads_to'] as $leadsTo) {
			$retval[] = $this->formStatuses[$leadsTo];
		}
		return $retval;
	}
}
