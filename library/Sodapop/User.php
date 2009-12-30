<?php
/**
 * The Sodapop user object.  This is stored in the Session.
 *
 * @author michaelarace
 */
class Sodapop_User {
    //put your code here

    protected $connection = null;

    protected $username = null;

    protected $properties = array();

    protected $permissions = array();

    protected $roles = array();

    protected $tablePermissions = array();

    protected $formPermissions = array();

    protected $procedurePermissions = array();

    protected $serverPermissions = array();

    protected $applicationPermissions = array();

    public function __construct($connection) {
	$this->connection = $connection;
    }

    public function __set($name, $value) {
	$validFields = array('username', 'properties', 'permissions', 'roles', 'tablePermissions', 'formPermissions', 'procedurePermissions'. 'serverPermissions', 'applicationPermissions');
	if (in_array($name, $validFields)) {
	    $this->$name = $value;
	}
	return $this;
    }

    public function  __get($name) {
	if ($name == 'username') {
	    return $this->username;
	} else {
	    return $this->properties[strtoupper($name)];
	}
    }

    public function getRoles() {
	return $this->roles;
    }

    public function hasPermission($permission) {
	return in_array(strtoupper($permission), $this->permissions);
    }

    public function getPermissionsForTable($table) {
	return $this->tablePermissions[strtoupper($table)];
    }

    public function hasTablePermission($table, $permission) {
	if (is_array($this->tablePermissions[strtoupper($table)])) {
	    return in_array(strtoupper($permission), $this->tablePermissions[strtoupper($table)]);
	} else {
	    return false;
	}
    }

    public function getPermissionsForForm($form) {
	return $this->formPermissions[strtoupper($form)];
    }

    public function hasFormPermission($form, $statusAdjective, $permission) {
	if (is_array($this->formPermissions[strtoupper($form)])) {
	    if (is_array($this->formPermissions[strtoupper($form)][strtoupper($statusAdjective)])) {
		return in_array(strtoupper($permission), $this->formPermissions[strtoupper($form)][strtoupper($statusAdjective)]);
	    } else {
		return false;
	    }
	} else {
	    return false;
	}
    }

    public function hasProcedurePermission($procedure, $permission) {
	if (is_array($this->procedurePermissions[strtoupper($procedure)])) {
	    return in_array(strtoupper($permission), $this->procedurePermissions[strtoupper($procedure)]);
	} else {
	    return false;
	}
    }

    public function hasApplicationPermission($permission) {
	return in_array(strtoupper($permission), $this->applicationPermissions);
    }

    public function hasServerPermission($permission) {
	return in_array(strtoupper($permission), $this->serverPermissions);
    }
}
