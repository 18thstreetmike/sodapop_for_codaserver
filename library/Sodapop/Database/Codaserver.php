<?php

/**
 * Description of Sodapop_Database_Codaserver
 *
 * @author michaelarace
 */
require_once('codaserver.lib.php');

class Sodapop_Database_Codaserver extends Sodapop_Database_Abstract {

	private $codaserverConnection = null;

	public $codaserverTablePrefix = 'c_';

	public function connect($hostname, $port, $username,$password) {
		try {
			$codaserverConnection = codaserver_connect($hostname, $port, $username, $password);
			if (!$codaserverConnection) {
				throw new Sodapop_Database_Exception('Invalid Username or Password', 1);
			} else {
				$this->codaserverConnection = $codaserverConnection;
			}
		} catch (Exception $e) {
			throw new Sodapop_Database_Exception($e->getMessage(), 1);
		}
	}

	public static function getUser($hostname, $port, $username, $password, $schema, $environment, $group, $availableModels) {
		// connect to the database and set the application
		$connection = new Sodapop_Database_Codaserver();
		$connection->connect($hostname, $port, $username, $password);
		switch ($environment) {
			case 'dev':
			case 'development':
				$environment = 'dev';
				break;
			case 'test':
			case 'testing':
				$environment = 'test';
				break;
			case 'prod':
			case 'production':
				$environment = 'prod';
				break;
			}
		try {
			$success = codaserver_set_application($connection->codaserverConnection, $schema, $environment, $group);
			$prefixResult = codaserver_query($connection->codaserverConnection, "raw select: select system_value from coda_system_information where system_property = 'PREFIX'");
			$connection->codaserverTablePrefix = $prefixResult['data'][0][0];
		} catch (Exception $e) {
			throw new Sodapop_Database_Exception($e->getMessage(), 2);
		}

		if (!$success) {
			throw new Sodapop_Database_Exception('Unable to log in to the specified application.', 2);
		}

		// create the user
		$user = new Sodapop_User($connection);

		// assign the username
		$user->username = $username;

		// load the properties
		$propertiesResult = codaserver_describe_user($connection->codaserverConnection, $username);
		$properties = array();
		for ($i = 0; $i < count($propertiesResult['columns']); $i++ ) {
			$properties[strtoupper($propertiesResult['columns'][$i]['columnname'])] = $propertiesResult['data'][0][$i];
		}
		$user->properties = $properties;

		// load the permissions
		$permissionsResult = codaserver_show_permissions($connection->codaserverConnection, $username);
		$permissions = array();
		foreach ($permissionsResult['data'] as $permission) {
			$permissions[] = $permission[0];
		}
		$user->permissions = $permissions;

		// load the roles
		$rolesResult = codaserver_show_roles($connection->codaserverConnection, $username);
		$roles = array();
		foreach ($rolesResult['data'] as $role) {
			$roles[] = $role[0];
		}
		$user->roles = $roles;

		// load the table permissions
		$tablePermissionsResult = codaserver_show_table_permissions($connection->codaserverConnection, $username);
		$tablePermissions = array();
		foreach ($tablePermissionsResult['data'] as $tablePermission) {
			$tablePermissions[$tablePermission[1]] = array();
			for($i = 2; $i < 6; $i++) {
				if ($tablePermission[$i] == '1') {
					switch ($i) {
						case 2:
							$tablePermissions[$tablePermission[1]][] = 'SELECT';
							break;
						case 3:
							$tablePermissions[$tablePermission[1]][] = 'INSERT';
							break;
						case 4:
							$tablePermissions[$tablePermission[1]][] = 'UPDATE';
							break;
						case 5:
							$tablePermissions[$tablePermission[1]][] = 'DELETE';
							break;
					}
				}
			}
		}
		$user->tablePermissions = $tablePermissions;

		// load the form permissions
		$formPermissionsResult = codaserver_show_form_permissions($connection->codaserverConnection, $username);
		$formPermissions = array();
		foreach ($formPermissionsResult['data'] as $formPermission) {
			if (!isset($formPermissions[$formPermission[1]])) {
				$formPermissions[$formPermission[1]] = array();
			}
			$formPermissions[$formPermission[1]][$formPermission[2]] = array();
			for ($i = 4; $i < 7; $i++) {
				if ($formPermission[$i] == '1') {
					switch ($i) {
						case 4:
							$formPermissions[$formPermission[1]][$formPermission[2]][] = 'VIEW';
							break;
						case 5:
							$formPermissions[$formPermission[1]][$formPermission[2]][] = 'CALL';
							break;
						case 6:
							$formPermissions[$formPermission[1]][$formPermission[2]][] = 'UPDATE';
							break;
					}
				}
			}
		}
		$user->formPermissions = $formPermissions;

		// get the procedure permissions
		$procedurePermissionsResult = codaserver_show_procedure_permissions($connection->codaserverConnection, $username);
		$procedurePermissions = array();
		foreach ($procedurePermissionsResult['data'] as $procedurePermission) {
			$procedurePermissions[$procedurePermission[1]] = array();
			if ($procedurePermission[2] == 1) {
				$procedurePermissions[$procedurePermission[1]] = array('EXECUTE');
			}
		}
		$user->procedurePermissions = $procedurePermissions;

		// get the server permissions
		$serverPermissionsResult = codaserver_show_server_permissions($connection->codaserverConnection, $username);
		$serverPermissions = array();
		foreach ($serverPermissionsResult['data'] as $serverPermission) {
			$serverPermissions[] = $serverPermission[1];
		}
		$user->serverPermissions = $serverPermissions;

		// get application permissions
		$applicationPermissionsResult = codaserver_show_application_permissions($connection->codaserverConnection, $username);
		$applicationPermissions = array();
		foreach($applicationPermissionsResult['data'] as $applicationPermission) {
			if ($applicationPermission[2] == strtoupper($schema)) {
				if (!$applicationPermission[3]) {
					$applicationPermissions[] = $applicationPermission[4];
				} else {
					$environmentId = 1;
					switch ($environment) {
						case 'production':
							$environmentId = 3;
							break;
						case 'staging':
							$environmentId = 2;
							break;
						case 'development':
							$environmentId = 1;
							break;
					}
					if ($applicationPermission[3] == $environmentId) {
						$applicationPermissions[] = $applicationPermission[4];
					}
				}
			}
		}
		$user->applicationPermissions = $applicationPermissions;

		$validAvailableModels = array();
		foreach ($availableModels as $model) {
			if ($user->hasTablePermission($model, 'SELECT') || $user->hasFormPermission($model, false, 'VIEW')) {
				$validAvailableModels[] = $model;
			}
		}

		$user->availableModels = $validAvailableModels;

		return $user;
	}


	public function destroy() {
		codaserver_query($this->codaserverConnection, 'DISCONNECT');
		return $this;
	}

	public function runParameterizedQuery($query, $params) {
		foreach ($params as $key => $value) {
			$query = str_replace(':'.$key, str_replace("'", "''", $value), $query);
		}
		try {
			$result = codaserver_query($this->codaserverConnection, $query);
		} catch (Exception $e) {
			throw new Sodapop_Database_Exception($e->getMessage(), 3);
		}

		$errors = codaserver_errors();
		if ($errors) {
			if (count($errors) == 1) {
				throw new Sodapop_Database_Exception($errors[0]['errormessage'], $errors[0]['errorcode']);
			} else {
				throw new Sodapop_Database_Exception('Some errors occurred', 3, $errors);
			}
		} else {
			return $result;
		}
	}

	public function runParameterizedUpdate($statement, $params) {
		foreach ($params as $key => $value) {
			$statement = str_replace(':{'.$key.'}', str_replace("'", "''", $value), $statement);
		}
		try {
			$result = codaserver_query($this->codaserverConnection, $statement);
		} catch (Exception $e) {
			throw new Sodapop_Database_Exception($e->getMessage(), 3);
		}

		$errors = codaserver_errors();
		if ($errors) {
			if (count($errors) == 1) {
				throw new Sodapop_Database_Exception($errors[0]['errormessage'], $errors[0]['errorcode']);
			} else {
				throw new Sodapop_Database_Exception('Some errors occurred', 3, $errors);
			}
		} else {
			return $result;
		}
	}

	public function runQuery($query) {
		try {
			$result = codaserver_query($this->codaserverConnection, $query);
		} catch (Exception $e) {
			throw new Sodapop_Database_Exception($e->getMessage(), 3);
		}

		$errors = codaserver_errors();
		if ($errors) {
			if (count($errors) == 1) {
				throw new Sodapop_Database_Exception($errors[0]['errormessage'], $errors[0]['errorcode']);
			} else {
				throw new Sodapop_Database_Exception('Some errors occurred', 3, $errors);
			}
		} else {
			return $result;
		}
	}

	public function runUpdate($statement) {
		try {
			$result = codaserver_query($this->codaserverConnection, $statement);
		} catch (Exception $e) {
			throw new Sodapop_Database_Exception($e->getMessage(), 3);
		}

		$errors = codaserver_errors();
		if ($errors) {
			if (count($errors) == 1) {
				throw new Sodapop_Database_Exception($errors[0]['errormessage'], $errors[0]['errorcode']);
			} else {
				throw new Sodapop_Database_Exception('Some errors occurred', 3, $errors);
			}
		} else {
			return $result;
		}
	}

	public function defineTableClass($tableName, $className) {
		$className = Sodapop_Inflector::underscoresToCamelCaps($tableName, false);
		$columns = codaserver_describe_table_columns($this->codaserverConnection, $tableName);
		$columnString = $this->getColumnDefinitionString($tableName);
		$childTableString = $this->getChildTableString($tableName);
		$overriddenFunctions = '
			public function loadData() {
				$result = $_SESSION[\'user\']->connection->runQuery("SELECT * FROM '.$tableName.' WHERE ID = \'".$this->id."\' ");
				if (count($result) > 0) {
					for($i = 0; $i < count($result[\'columns\']); $i++) {
					$this->fields[Sodapop_Inflector::underscoresToCamelCaps($result[\'columns\'][$i][\'columnname\'], true, false)] = $result[\'data\'][0][$i];
					$this->oldFields[Sodapop_Inflector::underscoresToCamelCaps($result[\'columns\'][$i][\'columnname\'], true, false)] = $result[\'data\'][0][$i];
					}
				}
				$this->lazyLoaded = true;
			}

			public function getSubtableChildIds($subtableName, $parentRowId) {
				return $_SESSION[\'user\']->connection->runUpdate("SELECT id FROM ".$subtableName." WHERE parent_table_id = \'".$parentRowId."\'");
			}

			protected function save($action = \'UPDATE\') {
				if (strtoupper($action) == \'DELETE\' && $this->id) {
					$_SESSION[\'user\']->connection->runQuery("DELETE FROM '.$tableName.' WHERE ID = \'".$this->id."\' ");
				} else {
					$setClause = \'\';
					foreach($this->fields as $fieldName => $newValue) {
						if (!isset($this->oldFields[$fieldName]) || $newValue != $this->oldFields[$fieldName]) {
							if ($setClause != \'\') {
								$setClause .= \',\';
							}
							$setClause .= Sodapop_Inflector::camelCapsToUnderscores($fieldName)." = \':{".$fieldName."}\'";
						}
					}
					if (trim($setClause) != "") {
						if (strtoupper($action) == \'INSERT\') {
							$statement = "INSERT INTO '.strtolower($tableName).' SET ".$setClause;
							$result = $_SESSION[\'user\']->connection->runParameterizedUpdate($statement, $this->fields);
							$this->id = $result["data"][0][0];
						} else if (strtoupper($action) == \'UPDATE\') {
							$_SESSION[\'user\']->connection->runParameterizedUpdate("UPDATE '.strtolower($tableName).' SET ".$setClause." WHERE ID = \'".$this->id."\' ", $this->fields);
						}
					}
				}
			}';
		$classDef = "class ".$className." extends Sodapop_Database_Table_Abstract {\n".$columnString."\n".$childTableString."\n".$overriddenFunctions."\n}";
		eval($classDef);
	}

	public function defineFormClass($formName, $className) {
		$className = Sodapop_Inflector::underscoresToCamelCaps($formName, false);
		$columnString = $this->getColumnDefinitionString($formName, true);
		$childTableString = $this->getChildTableString($formName);
		$formStatusString = $this->getFormStatusString($formName);
		$overriddenFunctions = '
			public function loadData() {
				$result = $_SESSION[\'user\']->connection->runQuery("SELECT t.*, s.adj_status_name AS status FROM '.$formName.' t INNER JOIN '.$this->codaserverTablePrefix.'form_statuses s ON s.id = t.status_id WHERE ID = \'".$this->id."\' ");
				if (count($result) > 0) {
					for($i = 0; $i < count($result[\'columns\']); $i++) {
					$this->fields[Sodapop_Inflector::underscoresToCamelCaps($result[\'columns\'][$i][\'columnname\'], true, false)] = $result[\'data\'][0][$i];
					$this->oldFields[Sodapop_Inflector::underscoresToCamelCaps($result[\'columns\'][$i][\'columnname\'], true, false)] = $result[\'data\'][0][$i];
					}
				}
				$this->lazyLoaded = true;
			}

			public function getSubtableChildIds($subtableName, $parentRowId) {
				return $_SESSION[\'user\']->connection->runUpdate("SELECT id FROM ".$subtableName." WHERE parent_table_id = \'".$parentRowId."\'");
			}

			protected function save($action = \'UPDATE\') {
				$setClause = \'\';
				foreach($this->fields as $fieldName => $newValue) {
					if (!isset($this->oldFields[$fieldName]) || $newValue != $this->oldFields[$fieldName]) {
						if ($setClause != \'\') {
							$setClause .= \',\';
						}
						$setClause .= Sodapop_Inflector::camelCapsToUnderscores($fieldName)." = \':{".$fieldName."}\'";
					}
				}
				if (trim($setClause) != "") {
					if ($action == \'UPDATE\') {
						$_SESSION[\'user\']->connection->runParameterizedUpdate("UPDATE '.strtolower($formName).' SET ".$setClause." WHERE ID = \'".$this->id."\' ", $this->fields);
					} else {
						foreach($this->formStatuses as $statusArray) {
							if (strtoupper($action) == $statusArray[\'verb\']) {
								$result = $_SESSION[\'user\']->connection->runParameterizedUpdate(strtouppser($action)." '.strtolower($formName).' SET ".$setClause." ".(!is_null($this->id) ? "WHERE ID = \'".$this->id."\' " : "" ), $this->fields);
								if (is_null($this->id)) {
									$this->id = $result["data"][0][0];
								}
								break;
							}
						}
					}
				}
			}

			public function getStatusTableName() {
				return "'.$this->codaserverTablePrefix.'form_statuses";
			}';

		$classDef = "class ".$className." extends Sodapop_Database_Form_Abstract {\n".$columnString."\n".$childTableString."\n".$formStatusString."\n".$overriddenFunctions."\n}";
		eval($classDef);
	}

	protected function getColumnDefinitionString ($tableName, $formFlag = false) {
		$columns = array();
		if (!$formFlag) {
			$columns = codaserver_describe_table_columns($this->codaserverConnection, $tableName);
		} else {
			$columns = codaserver_describe_form_fields($this->codaserverConnection, $tableName);
		}
		$columnString = 'protected $fieldDefinitions = array(';
		foreach ($columns['data'] as $column) {
			if ($columnString != 'protected $fieldDefinitions = array(') {
				$columnString .= ',';
			}
			$columnString .= "'".Sodapop_Inflector::underscoresToCamelCaps(strtolower($column[0]), true, false)."' => array(";
			for($i = 0; $i < count($column); $i++) {
				if ($i > 0) {
					$columnString .= ',';
				}
				$columnString .= "'".strtolower($columns['columns'][$i]['columnname'])."' => '".addslashes($column[$i])."'";
			}
			$columnString .= ")";
		}
		$columnString .= ');';
		return $columnString;
	}

	protected function getChildTableString($tableName) {
		$childTables = codaserver_query($this->codaserverConnection, "SHOW TABLES WHERE p.table_name = '".strtoupper($tableName)."'");
		$childTableString = 'protected $childTableDefinitions = array(';
		if ($childTables) {
			foreach ($childTables['data'] as $childTable) {
				if ($childTableString != 'protected $childTableDefinitions = array(') {
					$childTableString .= ',';
				}
				$childTableString .= "'".Sodapop_Inflector::underscoresToCamelCaps($childTable[0], true, false)."' => array(";
				for($i = 0; $i < count($childTable); $i++) {
					if ($i > 0) {
						$childTableString .= ',';
					}
					$childTableString .= "'".strtolower($childTables['columns'][$i]['columnname'])."' => '".addslashes($childTable[$i])."'";
				}
				$childTableString .= ",'lazyLoaded' => false, 'updated' => false);";
			}
		}
		$childTableString .= ');';
		return $childTableString;
	}

	protected function getFormStatusString($tableName) {
		$formStatusesRelationshipResult = codaserver_describe_form_status_relationships($this->codaserverConnection, $tableName);
		$formStatuses = array();
		foreach ($formStatusesRelationshipResult['data'] as $relationship) {
			if (!array_key_exists($relationship[0], $formStatuses)) {
				$formStatuses[$relationship[0]] = array('leads_to' => array());
			}
			$formStatuses[$relationship[0]]['leads_to'][] = $relationship[3];
		}
		$formStatusResult = codaserver_describe_form_statuses($this->codaserverConnection, $tableName);
		foreach($formStatusResult['data'] as $formStatus) {
			if (!array_key_exists($formStatus[0], $formStatuses)) {
				$formStatuses[$formStatus[0]] = array('leads_to' => array(), 'adjective' => $formStatus[1], 'display_adjective' => $formStatus[2], 'verb' => $formStatus[3], 'display_verb' => $formStatus[4], 'initial_flag' => $formStatus[5]);
			} else {
				$formStatuses[$formStatus[0]]['adjective'] = $formStatus[1];
				$formStatuses[$formStatus[0]]['display_adjective'] = $formStatus[2];
				$formStatuses[$formStatus[0]]['verb'] = $formStatus[3];
				$formStatuses[$formStatus[0]]['display_verb'] = $formStatus[4];
				$formStatuses[$formStatus[0]]['initial_flag'] = $formStatus[5];
			}
		}
		$formStatusString = 'protected $formStatuses = array(';
		foreach ($formStatuses as $statusId => $statusArray) {
			if ($formStatusString != 'protected $formStatuses = array(') {
				$formStatusString .= ',';
			}
			$formStatusString .= "'".$statusId."' => array(";
			$first = true;
			foreach($statusArray as $key => $value) {
				if (!$first) {
					$formStatusString .= ',';
				} else {
					$first = false;
				}
				$formStatusString .= "'".$key."' => ";
				if (is_array($value)) {
					if (count($value) > 0) {
						$formStatusString .= "array('".implode("','", $value)."')";
					} else {
						$formStatusString .= "array()";
					}
				} else {
					$formStatusString .= "'".addslashes($value)."'";
				}
			}
			$formStatusString .= ")";
		}
		$formStatusString .= ");";
		return $formStatusString;

	}
}

