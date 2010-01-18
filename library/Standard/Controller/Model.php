<?php
/**
 * Description of indexController
 *
 * @author michaelarace
 */
class Standard_Controller_Model extends Sodapop_Controller {
	protected $model;

	protected $modelClassname = '';
	protected $listSelectClause = '';
	protected $listFromClause = '';
	protected $joinedTables = array();
	protected $selectedColumns = array();

	public function preDispatch() {
		$modelClassname = str_replace('Controller','', get_class($this));
		$this->modelClassname = $modelClassname;
		$this->model = new $modelClassname();
		$this->listFromClause = strtolower(Sodapop_Inflector::camelCapsToUnderscores($modelClassname, false)).' AS '.Sodapop_Inflector::camelCapsToUnderscores($modelClassname, true).' ';
		if ($this->model instanceof Sodapop_Database_Form_Abstract) {
			$this->listFromClause .= ' INNER JOIN '.$this->model->getStatusTableName().' AS model_statuses ON model_statuses.id = '.Sodapop_Inflector::camelCapsToUnderscores($modelClassname, true).'.status_id ';
			$this->joinedTables[] = 'model_statuses';
		}
		$this->view->stylesheets = array('/styles/style.css');
		$this->view->currentTab = 'index';
	}

	public function indexAction() {
		$this->viewPath = 'model/list';
		$this->listAction();
	}

	public function listAction() {
		$filterVars = $this->processListFilterRequest();
		$this->view->filter = $this->buildFilter($filterVars);
		$grid = $this->buildGrid($filterVars);
		$this->view->grid = $grid;
		$this->view->data = $this->getData($grid, $filterVars);
	}

	protected function processListFilterRequest(){
		$retval = array('numPerPage' => (isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]) && isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]['list_num_per_page']) ? $this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]['list_num_per_page'] : 10), 'filters' => array(), 'startIndex' => 0, 'orderBy' => 'id', 'orderDirection' => 'ASC');
		foreach($this->request->variables() as $key => $value) {
			if (strpos('filter_', $key) === 0) {
				$keyName = substr($key, 7);
				if ($keyName == 'numPerPage') {
					$retval['numPerPage'] = $value;
				} else if ($keyName == 'orderBy') {
					$retval['orderBy'] = $value;
				} else if ($keyName == 'orderDirection') {
					$retval['orderDirection'] = $value;
				} else if ($keyName == 'startIndex') {
					$retval['startIndex'] = $value;
				} else {
					$retval['filters'][$key] = $value;
				}
			}
		}
		return $retval;
	}

	protected function buildFilter($filterVars) {
		$retval = array('filters' => array(), 'buttonLabel' => (isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]) && isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]['list_filter_label']) ? $this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]['list_filter_label'] : 'Update') );
		if (isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]) && isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]['list_filters'])) {
			// grab it from the models file
			foreach ($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]['list_filters'] as $filter) {
				if (isset($filter['type']) && $filter['type'] == 'search') {
					$retval['filters'][] = array('label' => 'Search', 'id'=> 'search', 'input' => 'text', 'default' => (isset($filterVars['filters']['search']) ? $filterVars['filters']['search'] : ''));
				} else if (isset($filter['type']) && $filter['type'] == 'num_per_page') {
					$retval['filters'][] = array('label' => '# Per Page', 'id'=> 'numPerPage', 'input' => 'select', 'options' => array('10' => '10', '20' => '20', '50' => '50', '100' => '100'), 'default' => $filterVars['numPerPage']);
				} else if (isset($filter['field']) && $filter['field'] == 'status' && $this->model instanceof Sodapop_Database_Form_Abstract) {
					$options = array();
					if ($filter['include_all']) {
						$options[''] = 'All';
					}
					foreach ($this->model->getFormStatuses() as $statusId => $status) {
						$options[$statusId] = $status['display_adjective'];
					}
					$retval['filters'][] = array('label' => 'Status', 'id' => 'statusId', 'input' => 'select', 'options' => $options, 'default' => (isset($filterVars['filters']['statusId']) ? $filterVars['filters']['statusId'] : ''));
				} else {
					if (isset($filter['field'])) {
						$fieldDef = $this->model->getFieldDefinition(Sodapop_Inflector::underscoresToCamelCaps($filter['field']));
						if ($fieldDef) {
							if ($fieldDef['type_name'] == 'REFERENCE' && (!$filter['type'] || $filter['type'] == 'select') && $filter['select_field'] && ($this->user->hasTablePermission($fieldDef['ref_table_name'], 'SELECT') || $this->user->hasFormPermission($fieldDef['ref_table_name'], false, 'VIEW'))) {
								$options = array();
								if ($filter['include_all']) {
									$options[''] = 'All';
								}
								$result = $this->user->connection->runQuery("SELECT id, ".$filter['select_field']." FROM ".$fieldDef['ref_table_name']." ORDER BY ".$filter['select_field']." ASC");
								foreach ($result['data'] as $row) {
									$options[$row[0]] = $row[1];
								}
								$retval['filters'][] = array('label' => $fieldDef['display_name'], 'id' => Sodapop_Inflector::underscoresToCamelCaps($filter['field']), 'input' => 'select', 'options' => $options, 'default' => ($filterVars['filters'][Sodapop_Inflector::underscoresToCamelCaps($filter['field'])] ? $filterVars['filters'][Sodapop_Inflector::underscoresToCamelCaps($filter['field'])] : ''));
							} else {
								$retval['filters'][] = array('label' => $fieldDef['display_name'], 'id' => Sodapop_Inflector::underscoresToCamelCaps($filter['field']), 'input' => 'text', 'default' => ($filterVars['filters'][Sodapop_Inflector::underscoresToCamelCaps($filter['field'])] ? $filterVars['filters'][Sodapop_Inflector::underscoresToCamelCaps($filter['field'])] : ''));
							}
						}
					}
				}
			}
		} else {
			$fieldDefs = $this->model->getFieldDefinitions();
			foreach($fieldDefs as $fieldDef) {
				if ($fieldDef['type_name'] == 'REFERENCE' && ($this->user->hasTablePermission($fieldDef['ref_table_name'], 'SELECT') || $this->user->hasFormPermission($fieldDef['ref_table_name'], false, 'VIEW'))) {
					$options = array();
					$options[''] = 'All';
					$result = $this->user->connection->runQuery("SELECT * FROM ".$fieldDef['ref_table_name']." ORDER BY id ASC");
					for ($i = 0; $i < count($result['columns']); $i++) {
						if ($result['columns'][$i]['columnname'] == 'id') {
							break;
						}
					}
					foreach ($result['data'] as $row) {
						$options[$row[$i]] = $row[($i == 0 ? 1 : 0)];
					}
					$retval['filters'][] = array('label' => $fieldDef['display_name'], 'id' => Sodapop_Inflector::underscoresToCamelCaps($fieldDef['field_name'] ? $fieldDef['field_name'] : $fieldDef['column_name']), 'input' => 'select', 'options' => $options, 'default' => ($filterVars['filters'][Sodapop_Inflector::underscoresToCamelCaps($fieldDef['field_name'] ? $fieldDef['field_name'] : $fieldDef['column_name'])] ? $filterVars['filters'][Sodapop_Inflector::underscoresToCamelCaps($fieldDef['field_name'] ? $fieldDef['field_name'] : $fieldDef['column_name'])] : ''));
				}
			}
			$retval['filters'][] = array('label' => 'Search', 'id'=> 'search', 'input' => 'text', 'default' => (isset($filterVars['filters']['search']) ? $filterVars['filters']['search'] : ''));
			$retval['filters'][] = array('label' => '# Per Page', 'id'=> 'numPerPage', 'input' => 'select', 'options' => array('10' => '10', '20' => '20', '50' => '50', '100' => '100'), 'default' => $filterVars['numPerPage']);
		}
		return $retval;
	}

	protected function buildGrid($filterVars) {
		$retval = array('headings' =>array());
		if (isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]) && isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]['list_fields'])) {
			$retval['headings'] = $this->processModelList($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]['list_fields'], $filterVars);
		} else {
			$modelList = array(array('name' => 'id', 'link' => '/'.$this->modelClass.'/view/:id'));
			$fields = $this->model->getFieldDefinitions();
			foreach ($fields as $field) {
				if (strtolower($field['column_name']) != 'id' && strtolower($field['field_name']) != 'id' && strtolower($field['type_name']) != 'reference') {
					$modelList[] = array('name' => strtolower($field['column_name']));
				}
			}
			if ($this->model instanceof Sodapop_Database_Form_Abstract) {
				$modelList[] = array('name' => 'status');
			}
			$retval['headings'] = $this->processModelList($modelList, $filterVars);
		}
		return $retval;
	}

	protected function processModelList($modelList, $filterVars) {
		$retval = array();
		foreach ($modelList as $listField) {
			$heading = array('id' => $listField['name']);
			if ($this->model instanceof Sodapop_Database_Form_Abstract && strtolower($listField['name']) == 'status') {
				$heading['label'] = (isset($listField['label']) ? $listField['label'] : 'Status');
				if (!in_array('model_statuses.adj_display_name', $this->selectedColumns)) {
					$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').'model_statuses.adj_display_name AS '.Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'_status';
					$this->selectedColumns[] = 'model_statuses.adj_display_name';
				}
			} else {
				$fieldDefinition = $this->model->getFieldDefinition(Sodapop_Inflector::underscoresToCamelCaps($listField['name'], true, false));
				if (!is_null($fieldDefinition)) {
					$heading['label'] = (isset($listField['label']) ? $listField['label'] : $fieldDefinition['display_name']);
					$heading['printExpression'] = array(array('type' => 'field', 'field' => Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'_'.$listField['name']));
					$heading['sortField'] = Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'_'.$listField['name'];
					if (!in_array(Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$listField['name'], $this->selectedColumns)) {
						$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$listField['name'].' AS '.Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'_'.$listField['name'];
						$this->selectedColumns[] = Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$listField['name'];
					}
				} else {
					$heading['label'] = (isset($listField['label']) ? $listField['label'] : 'Column '.(count($retval) + 1));
					if (isset($listField['concat'])) {
						$heading['printExpression'] = array();
						$concatParts = explode('+', $listField['concat']);
						foreach ($concatParts as $concatPart) {
							$concatPart = trim($concatPart);
							if (substr($concatPart, 0, 1) == "'" && substr($concatPart, strlen($concatPart) - 2, 1) == "'") {
								$heading['printExpression'][] = array('type' => 'text', 'text' => substr($concatPart,1, strlen($concatPart) - 2));
							} else {
								$moreParts = explode('.', $concatPart);
								$partDefinition = $this->model->getFieldDefinition(Sodapop_Inflector::underscoresToCamelCaps($moreParts[0], true, false));
								if (!is_null($partDefinition)) {
									if (!isset($moreParts[1]) || $partDefinition['type_name'] != 'REFERENCE') {
										$columnName = trim($moreParts[0]);
										if (!in_array(Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$columnName, $this->selectedColumns)) {
											$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$columnName.' AS '.Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'_'.$columnName;
											$this->selectedColumns[] = Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$columnName;
										}
										$heading['printExpression'][] = array('type' => 'field', 'field' => Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'_'.$columnName);
										if (!isset($heading['sortField'])) {
											$heading['sortField'] = Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'_'.$columnName;
										}
									} else {
										$childTableClassname = Sodapop_Inflector::underscoresToCamelCaps(strtolower($partDefinition['ref_table_name']), false);
										$childObject = new $childTableClassname();
										$childPartsDefinition = $childObject->getFieldDefinition(Sodapop_Inflector::underscoresToCamelCaps($moreParts[1], true, false));
										if (!is_null($childPartsDefinition)) {
											if (!in_array($moreParts[0].'.'.$moreParts[1], $this->selectedColumns)) {
												$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').$moreParts[0].'.'.$moreParts[1].' AS '.$moreParts[0].'_'.$moreParts[1];
												$this->selectedColumns[] = $moreParts[0].'.'.$moreParts[1];
											}
											if (!in_array($moreParts[0], $this->joinedTables)) {
												$this->listFromClause .= ' LEFT OUTER JOIN '.strtolower($partDefinition['ref_table_name']).' AS '.$moreParts[0].' ON '.$moreParts[0].'.id = '.Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$moreParts[0].' ';
												$this->joinedTables[] = $moreParts[0];
											}
											$heading['printExpression'][] = array('type' => 'field', 'field' => $moreParts[0].'_'.$moreParts[1]);
											if (!isset($heading['sortField'])) {
												$heading['sortField'] = $moreParts[0].'_'.$moreParts[1];
											}
										}
									}
								}

							}
						}
					}
					if (isset($listField['sort'])) {
						$heading['sortField'] = str_replace('.', '_', $listField['sort']);
					}
				}
			}
			if (isset($listField['link']) && $listField['link']) {
				$heading['link'] = $this->processLink($listField['link']);
			}
			$heading['orderBy'] = $filterVars['orderBy'] == $listField['name'];
			$heading['orderDirection'] = $filterVars['orderDirection'];
			$retval[] = $heading;
		}
		return $retval;
	}

	protected function processLink($link) {
		$retval = array();
		$currentTextNode = '';
		$linkParts = explode('/', $link);
		foreach($linkParts as $part) {
			$part = trim($part);
			if (substr($part, 0, 1) == ':') {
				$retval[] = array('type' => 'text', 'text' => $currentTextNode.'/');
				$currentTextNode = '';
				$fieldParts = explode('.', substr($part, 1));
				$partDefinition = $this->model->getFieldDefinition(Sodapop_Inflector::underscoresToCamelCaps($fieldParts[0], true, false));
				if (!is_null($partDefinition)) {
					if (!isset($fieldParts[1]) || $partDefinition['type_name'] != 'REFERENCE') {
						$columnName = trim($fieldParts[0]);
						if (!in_array(Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$columnName, $this->selectedColumns)) {
							$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$columnName.' AS '.Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'_'.$columnName;
							$this->selectedColumns[] = Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$columnName;
						}
						$retval[] = array('type' => 'field', 'field' => Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'_'.$columnName);
					} else {
						$childTableClassname = Sodapop_Inflector::underscoresToCamelCaps(strtolower($partDefinition['ref_table_name']), false);
						$childObject = new $childTableClassname();
						$childPartsDefinition = $childObject->getFieldDefinition(Sodapop_Inflector::underscoresToCamelCaps($fieldParts[1], true, false));
						if (!is_null($childPartsDefinition)) {
							if (!in_array($fieldParts[0].'.'.$fieldParts[1], $this->selectedColumns)) {
								$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').$fieldParts[0].'.'.$fieldParts[1].' AS '.$fieldParts[0].'_'.$fieldParts[1];
								$this->selectedColumns[] = $fieldParts[0].'.'.$fieldParts[1];
							}
							if (!in_array($fieldParts[0], $this->joinedTables)) {
								$this->listFromClause .= ' LEFT OUTER JOIN '.strtolower($partDefinition['ref_table_name']).' AS '.$fieldParts[0].' ON '.$fieldParts[0].'.id = '.Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$fieldParts[0].' ';
								$this->joinedTables[] = $fieldParts[0];
							}
							$retval[] = array('type' => 'field', 'field' => $fieldParts[0].'_'.$fieldParts[1]);
						}
					}
				}
			} else {
				$currentTextNode .= '/'.$part;
			}
		}
		if ($currentTextNode != '') {
			$retval[] = array('type' => 'text', 'text' => $currentTextNode);
		}
		return $retval;
	}

	protected function getData($grid, $filterVars) {
		$whereClause = '';
		if (count($filterVars['filters']) > 0) {
			foreach($filterVars['filters'] as $key => $filter){
				if ($whereClause == '') {
					$whereClause .= ' WHERE ';
				} else {
					$whereClause .= ' AND ';
				}
				if ($key == 'search') {
					// add each field in the select clause, then add each field from this model
					$search = '(';
					foreach ($this->selectedColumns as $column) {
						if ($search != '(') {
							$search .= ' OR ';
						}
						$search .= $column ." LIKE '%".str_replace("'", "''", $filter)."%' ";
					}
					foreach ($this->model->getFieldDefinitions() as $fieldName => $fieldDefinition) {
						if (!in_array(Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname).'.'.Sodapop_Inflector::camelCapsToUnderscores($fieldName), $this->selectedColumns)) {
							if ($search != '(') {
								$search .= ' OR ';
							}
							$search .= Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname).'.'.Sodapop_Inflector::camelCapsToUnderscores($fieldName) ." LIKE '%".str_replace("'", "''", $filter)."%' ";
						}
					}
					$whereClause .= $search . ') ';
				} else if (trim($filter) != '') {
					$whereClause .= Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname)." LIKE '%".str_replace("'", "''", $filter)."%' ";
				}
			}
		}

		$retval = array('totalRows' => 0, 'numPerPage' => $filterVars['numPerPage'], 'startIndex' => $filterVars['startIndex'], 'data' => array());
		$countSelectStatement = 'SELECT count('.Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname).'.id) FROM '.$this->listFromClause.' '.$whereClause;
		$countResult = $this->user->connection->runQuery($countSelectStatement);
		foreach ($countResult['data'] as $row) {
			$retval['totalRows'] = $row[0];
		}

		$orderBy = ' ORDER BY '.Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname).'.id '.$filterVars['orderDirection'];
		foreach ($grid['headings'] as $gridValue) {
			if ($gridValue['id'] == $filterVars['orderBy']) {
				$orderBy = ' ORDER BY '.$gridValue['sortField'].' '.$filterVars['orderDirection'];
				break;
			}
		}

		$selectStatement = 'SELECT TOP '.$filterVars['numPerPage'].' STARTING AT '.$filterVars['startIndex'].' '.$this->listSelectClause.' FROM '.$this->listFromClause .' '.$whereClause.' '.$orderBy;
		echo $selectStatement;
	}
}
