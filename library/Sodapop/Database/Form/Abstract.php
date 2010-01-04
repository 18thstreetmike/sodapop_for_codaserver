<?php
/**
 * Description of Abstract
 *
 * @author michaelarace
 */
class Sodapop_Database_Form_Abstract extends Sodapop_Database_Table_Abstract {

    protected $formStatuses = array();

    public function getStatus() {
	return $this->formStatuses[$this->status];
    }

    public function getValidActions() {
	return $this->formStatuses[$this->status]['leads_to'];
    }
}
