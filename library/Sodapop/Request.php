<?php
/**
 * Represents the request
 *
 * @author michaelarace
 */
class Sodapop_Request {
    private $requestVariables = array();

    private $requestVariablesNumeric = array();

    private $isPost = false;

    private $isGet = false;

    public function __construct() {
	if ($_POST) {
	    $this->isPost = true;
	} else {
	    $this->isGet = true;
	}

	if (count($_REQUEST) > 1) {
	    $this->requestVariables = $_REQUEST;
	}
    }

    public function  __get($name) {
	return $this->requestVariables[$name];
    }

    public function setRequestVariables($requestVariables) {
	$this->requestVariables = $requestVariables;
	return $this;
    }

    public function setRequestVariablesNumeric($requestVariablesNumeric) {
	$this->requestVariablesNumeric = $requestVariablesNumeric;
	return $this;
    }

    public function variables($numeric = false) {
	if ($numeric) {
	    return $this->requestVariablesNumeric;
	} else {
	    return $this->requestVariables;
	}
    }

    public function isPost() {
	return $this->isPost;
    }

    public function isGet() {
	return $this->isGet;
    }
}
