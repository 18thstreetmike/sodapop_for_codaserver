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
		$this->listFromClause = strtolower(Sodapop_Inflector::camelCapsToUnderscores($modelClassname, false)).' AS '.$this->createAlias($modelClassname).' ';
		if ($this->model instanceof Sodapop_Database_Form_Abstract) {
			$this->listFromClause .= ' INNER JOIN '.$this->model->getStatusTableName().' AS model_statuses ON model_statuses.id = '.$this->createAlias($modelClassname).'.status_id ';
			$this->joinedTables[] = 'model_statuses';
		}
		$this->view->stylesheets = array('/styles/style.css');
		$this->view->currentTab = strtolower(substr($modelClassname, 0, 1)).substr($modelClassname, 1);
		
	}

	public function indexAction() {
		$this->listAction();
	}

	public function listAction() {
		$this->viewPath = 'model/list';
		$navigationItem = $this->application->getNavigationItem(strtolower(substr($this->modelClassname, 0, 1)).substr($this->modelClassname, 1));
		$this->view->tabTitle = $navigationItem['label'];
		if (isset($navigationItem['description'])) {
			$this->view->tabDescription = $navigationItem['description'];
		} else {
			$this->view->tabDescription = '';
		}
		$filterVars = $this->processListFilterRequest();
		$this->view->listState = $this->buildListState($filterVars);
		$this->view->filter = $this->buildFilter($filterVars);
		$grid = $this->buildGrid($filterVars);
		$this->view->grid = $grid;
		$this->view->data = $this->getData($grid, $filterVars);
		$this->view->actions = $this->getInitialActions();
	}

	public function viewAction($action = 'View') {
		$this->viewPath = 'model/view';
		$filterVars = $this->processListFilterRequest();
		$modelClassname = $this->modelClassname;
		$model = new $modelClassname(isset($this->request->id) ? $this->request->id : $this->request->numeric[0]);
		$this->view->form = $this->getModelForm($model);

	}

	protected function processListFilterRequest(){
		$retval = array('numPerPage' => (isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]) && isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]['list_num_per_page']) ? $this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($this->model), false)]['list_num_per_page'] : 10), 'filters' => array(), 'startIndex' => 0, 'orderBy' => 'id', 'orderDirection' => 'ASC');
		foreach($this->request->variables() as $key => $value) {
			if (substr($key, 0, 7) == 'filter_') {
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
					$retval['filters'][$keyName] = $value;
				}
			}
		}
		return $retval;
	}

	protected function buildListState($filterVars) {
		  $retval = array();
		  $retval['sort'] = '&filter_startIndex=0&filter_numPerPage='.$filterVars['numPerPage'];
		  $retval['nextPage'] = '&filter_startIndex='.($filterVars['startIndex'] + $filterVars['numPerPage']).'&filter_numPerPage='.$filterVars['numPerPage'].'&filter_orderBy='.$filterVars['orderBy'].'&filter_orderDirection='.$filterVars['orderDirection'];
		  $retval['prevPage'] = '&filter_startIndex='.($filterVars['startIndex'] - $filterVars['numPerPage']).'&filter_numPerPage='.$filterVars['numPerPage'].'&filter_orderBy='.$filterVars['orderBy'].'&filter_orderDirection='.$filterVars['orderDirection'];
		  $retval['viewItem'] = '?filter_startIndex='.($filterVars['startIndex'] - $filterVars['numPerPage']).'&filter_numPerPage='.$filterVars['numPerPage'].'&filter_orderBy='.$filterVars['orderBy'].'&filter_orderDirection='.$filterVars['orderDirection'];
		  foreach($filterVars['filters'] as $key => $filter) {
		  	$retval['sort'] .= '&filter_'.$key.'='.urlencode($filter);
			$retval['nextPage'] .= '&filter_'.$key.'='.urlencode($filter);
			$retval['prevPage'] .= '&filter_'.$key.'='.urlencode($filter);
			$retval['viewItem'] .= '&filter_'.$key.'='.urlencode($filter);
		  }
		  $retval['filter'] = '';
		  foreach($filterVars as $key => $filter) {
		  	if ($key != 'filters') {
			   $retval['filter'] .= '<input type="hidden" name="filter_'.$key.'" value="'.htmlentities($filter).'" />';
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
					$result = $this->user->connection->runQuery("SELECT * FROM ".strtolower($fieldDef['ref_table_name'])." ORDER BY id ASC");
					for ($i = 0; $i < count($result['columns']); $i++) {
						if ($result['columns'][$i]['columnname'] == 'id') {
							break;
						}
					}
					foreach ($result['data'] as $row) {
						$options[$row[$i]] = $row[($i == 0 ? 1 : 0)];
					}
					$retval['filters'][] = array('label' => $fieldDef['display_name'], 'id' => Sodapop_Inflector::underscoresToCamelCaps(strtolower(isset($fieldDef['field_name']) ? $fieldDef['field_name'] : $fieldDef['column_name']), true, false), 'input' => 'select', 'options' => $options, 'default' => (isset($filterVars['filters'][Sodapop_Inflector::underscoresToCamelCaps(strtolower(isset($fieldDef['field_name']) ? $fieldDef['field_name'] : $fieldDef['column_name']), true, false)]) ? $filterVars['filters'][Sodapop_Inflector::underscoresToCamelCaps(strtolower(isset($fieldDef['field_name']) ? $fieldDef['field_name'] : $fieldDef['column_name']), true, false)] : ''));
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
			$modelList = array(array('name' => 'id', 'link' => '/'.$this->modelClassname.'/view/:id'));
			$fields = $this->model->getFieldDefinitions();
			foreach ($fields as $field) {
				if (((isset($field['column_name']) && strtolower($field['column_name']) != 'id') || (isset($field['field_name']) && strtolower($field['field_name']) != 'id')) && strtolower($field['type_name']) != 'reference') {
					$modelList[] = array('name' => isset($field['column_name']) ? strtolower($field['column_name']) : strtolower($field['field_name']));
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
				if (!array_key_exists('model_statuses.adj_display_name', $this->selectedColumns)) {
					$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').'model_statuses.adj_display_name AS '.$this->createAlias($this->modelClassname).'_status';
					$this->selectedColumns['model_statuses.adj_display_name'] = 'STRING';
				}
				$heading['sortField'] = $this->createAlias($this->modelClassname).'_status';
			} else {
				$fieldDefinition = $this->model->getFieldDefinition(Sodapop_Inflector::underscoresToCamelCaps($listField['name'], true, false));
				if (!is_null($fieldDefinition)) {
					$heading['label'] = (isset($listField['label']) ? $listField['label'] : $fieldDefinition['display_name']);
					$heading['printExpression'] = array(array('type' => 'field', 'field' => $this->createAlias($this->modelClassname).'_'.$listField['name']));
					$heading['sortField'] = $this->createAlias($this->modelClassname).'_'.$listField['name'];
					if (!array_key_exists($this->createAlias($this->modelClassname).'.'.$listField['name'], $this->selectedColumns)) {
						$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').$this->createAlias($this->modelClassname).'.'.$listField['name'].' AS '.$this->createAlias($this->modelClassname).'_'.$listField['name'];
						$this->selectedColumns[$this->createAlias($this->modelClassname).'.'.$listField['name']] = $fieldDefinition['type_name'];
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
										if (!array_key_exists($this->createAlias($this->modelClassname).'.'.$columnName, $this->selectedColumns)) {
											$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').$this->createAlias($this->modelClassname).'.'.$columnName.' AS '.$this->createAlias($this->modelClassname).'_'.$columnName;
											$this->selectedColumns[$this->createAlias($this->modelClassname).'.'.$columnName] = $partDefinition['type_name'];
										}
										$heading['printExpression'][] = array('type' => 'field', 'field' => Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'_'.$columnName);
										if (!isset($heading['sortField'])) {
											$heading['sortField'] = $this->createAlias($this->modelClassname).'_'.$columnName;
										}
									} else {
										$childTableClassname = Sodapop_Inflector::underscoresToCamelCaps(strtolower($partDefinition['ref_table_name']), false);
										$childObject = new $childTableClassname();
										$childPartsDefinition = $childObject->getFieldDefinition(Sodapop_Inflector::underscoresToCamelCaps($moreParts[1], true, false));
										if (!is_null($childPartsDefinition)) {
											if (!array_key_exists($moreParts[0].'.'.$moreParts[1], $this->selectedColumns)) {
												$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').$moreParts[0].'.'.$moreParts[1].' AS '.$moreParts[0].'_'.$moreParts[1];
												$this->selectedColumns[$moreParts[0].'.'.$moreParts[1]] = $childPartsDefinition['type_name'];
											}
											if (!in_array($moreParts[0], $this->joinedTables)) {
												$this->listFromClause .= ' LEFT OUTER JOIN '.strtolower($partDefinition['ref_table_name']).' AS '.$moreParts[0].' ON '.$moreParts[0].'.id = '.$this->createAlias($this->modelClassname).'.'.$moreParts[0].' ';
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
			$heading['orderBy'] = $filterVars['orderBy'] == $heading['sortField'];
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
						if (!array_key_exists($this->createAlias($this->modelClassname).'.'.$columnName, $this->selectedColumns)) {
							$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').$this->createAlias($this->modelClassname).'.'.$columnName.' AS '.$this->createAlias($this->modelClassname).'_'.$columnName;
							$this->selectedColumns[Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, true).'.'.$columnName] = $partDefinition['type_name'];
						}
						$retval[] = array('type' => 'field', 'field' => $this->createAlias($this->modelClassname).'_'.$columnName);
					} else {
						$childTableClassname = Sodapop_Inflector::underscoresToCamelCaps(strtolower($partDefinition['ref_table_name']), false);
						$childObject = new $childTableClassname();
						$childPartsDefinition = $childObject->getFieldDefinition(Sodapop_Inflector::underscoresToCamelCaps($fieldParts[1], true, false));
						if (!is_null($childPartsDefinition)) {
							if (!array_key_exists($fieldParts[0].'.'.$fieldParts[1], $this->selectedColumns)) {
								$this->listSelectClause .= ($this->listSelectClause == '' ? ' ' : ', ').$fieldParts[0].'.'.$fieldParts[1].' AS '.$fieldParts[0].'_'.$fieldParts[1];
								$this->selectedColumns[$fieldParts[0].'.'.$fieldParts[1]] = $childPartsDefinition['type_name'];
							}
							if (!in_array($fieldParts[0], $this->joinedTables)) {
								$this->listFromClause .= ' LEFT OUTER JOIN '.strtolower($partDefinition['ref_table_name']).' AS '.$fieldParts[0].' ON '.$fieldParts[0].'.id = '.$this->createAlias($this->modelClassname).'.'.$fieldParts[0].' ';
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
				if (trim($filter) != '') {
					if ($whereClause == '') {
						$whereClause .= ' WHERE ';
					} else {
						$whereClause .= ' AND ';
					}
					if ($key == 'search') {
						// add each field in the select clause, then add each field from this model
						$search = '( ';
						foreach ($this->selectedColumns as $column => $typeName) {
							if ($typeName != 'INTEGER' && $typeName != 'FLOAT' && $typeName != 'REFERENCE' && $typeName != 'TIMESTAMP') {
								if ($search != '( ') {
									$search .= ' OR ';
								}
								$search .= $column ." LIKE '%".str_replace("'", "''", $filter)."%' ";
							} else if (is_numeric($filter) && ($typeName == 'INTEGER' || $typeName == 'FLOAT' || $typeName == 'REFERENCE')) {
								if ($search != '( ') {
									$search .= ' OR ';
								}
								$search .= $column ." = '".$filter."' ";
							}
						}
						foreach ($this->model->getFieldDefinitions() as $fieldName => $fieldDefinition) {
							$typeName = $fieldDefinition['type_name'];
							if (!in_array(Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname).'.'.Sodapop_Inflector::camelCapsToUnderscores($fieldName), $this->selectedColumns)) {
								if ($typeName != 'INTEGER' && $typeName != 'FLOAT' && $typeName != 'REFERENCE' && $typeName != 'TIMESTAMP') {
									if ($search != '( ') {
										$search .= ' OR ';
									}
									$search .= Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname).'.'.Sodapop_Inflector::camelCapsToUnderscores($fieldName) ." LIKE '%".str_replace("'", "''", $filter)."%' ";
								} else if (is_numeric($filter) && ($typeName == 'INTEGER' || $typeName == 'FLOAT' || $typeName == 'REFERENCE')) {
									if ($search != '( ') {
										$search .= ' OR ';
									}
									$search .= $column ." = '".$filter."' ";
								}
							}
						}
						$whereClause .= $search . ') ';
					} else {
						$whereClause .= Sodapop_Inflector::camelCapsToUnderscores($key)." = '".str_replace("'", "''", $filter)."' ";
					}
				}
				// potentially reset
				if ($whereClause == ' WHERE ') {
					$whereClause = '';
				}
			}
		}

		$retval = array('totalRows' => 0, 'numPerPage' => $filterVars['numPerPage'], 'startIndex' => $filterVars['startIndex'], 'data' => array());
		$countSelectStatement = 'SELECT count('.$this->createAlias($this->modelClassname).'.id) FROM '.$this->listFromClause.' '.$whereClause;
		$countResult = $this->user->connection->runQuery($countSelectStatement);
		foreach ($countResult['data'] as $row) {
			$retval['totalRows'] = $row[0];
		}

		$orderBy = ' ORDER BY '.$this->createAlias($this->modelClassname).'.id '.$filterVars['orderDirection'];
		foreach ($grid['headings'] as $gridValue) {
			if ($gridValue['id'] == $filterVars['orderBy']) {
				$orderBy = ' ORDER BY '.$gridValue['sortField'].' '.$filterVars['orderDirection'];
				break;
			}
		}

		$selectStatement = 'SELECT TOP '.$filterVars['numPerPage'].' STARTING AT '.$filterVars['startIndex'].' '.$this->listSelectClause.' FROM '.$this->listFromClause .' '.$whereClause.' '.$orderBy;
		$result = $this->user->connection->runQUery($selectStatement);
		$data = array();
		foreach ($result['data'] as $row) {
			$dataRow = array();
			for($i = 0; $i < count($result['columns']); $i++) {
				$dataRow[$result['columns'][$i]['columnname']] = $row[$i];
			}
			$data[] = $dataRow;
		}
		$retval['data'] = $data;
		$retval['emptyRows'] = $retval['numPerPage'] - count($data);
		return $retval;
	}

	protected function getModelForm($model) {
		$retval = array('groups' => array(), 'tabs' => array());
		if (isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($model), false)]) && isset($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($model), false)]['fields'])) {
			foreach ($this->application->models[Sodapop_Inflector::camelCapsToUnderscores(get_class($model), false)]['fields'] as $groupTabItem) {
				if (isset($groupTabItem['group'])) {
					$retval['groups'][$groupTabItem['group']] = $this->parseGroupInfo(isset($groupTabItem['fields']) ? $groupTabItem['fields'] : array(), $model);
				} else if (isset($groupTabItem['tab'])) {
					$retval['tabs'][$groupTabItem['tab']] = array();
					foreach ($groupTabItem['groups'] as $groupItem) {
						$retval['tabs'][$groupTabItem['tab']][$groupItem['group']] = $this->parseGroupInfo(isset($groupItem['fields']) ? $groupItem['fields'] : array(), $model);
					}
				}
			}
		} else {
			$retval['groups']['Information'] = array();
			$fieldDefinitions = $model->getFieldDefinitions();
			foreach ($fieldDefinitions as $fieldName => $fieldDefinition) {
				$fieldArray['label'] = $fieldDefinition['display_name'];
				$fieldArray['default'] = isset($model->$fieldName) ? $model->$fieldName : $fieldDefinition['default_value'];
				$fieldArray['arrayFlag'] = $fieldDefinition['array_flag'] == '1';
				if ($fieldDefinition['type_name'] == 'REFERENCE') {
					$referenceModelClassname = Sodapop_Inflector::underscoresToCamelCaps(strtolower($fieldDefinition['ref_table_name']), false);
					$referenceModel = new $referenceModeClassName();
					$referenceFieldDefinitions = $referenceModel->getFieldDefinitions();
					$fieldArray['type'] = 'select';
					$optionQuery = (isset($field['query']) ? $field['query'] : 'SELECT '.(isset($field['select']) ? $field['select'] : 'id, '.strtolower($referenceFieldDefinitions['column_name'])).' FROM '.strtolower($fieldDefinition['ref_table_name']).' '.(isset($field['where']) ? 'WHERE '.$field['where'] : '').' '.(isset($field['order']) ? 'ORDER BY '.$field['order'] : ''));
					$optionsResult = $this->user->connection->runQuery($optionQuery);
					$fieldArray['options'] = array();
					foreach ($optionsResult['data'] as $optionRow) {
						$fieldArray['options'][$optionRow[0]] = $optionRow[1];
					}
				} else {
					$fieldArray['type'] = $fieldDefinition['type_name'];
				}
			}
		}
		//echo 'Main :'.var_export($retval);
		return $retval;
	}

	protected function parseGroupInfo($fields, $model) {
		$fieldArray = array();
		if ($fields) {
			foreach ($fields as $field) {
				$fieldName = $field['name'];
				$fieldDefinition = $model->getFieldDefinition(Sodapop_Inflector::underscoresToCamelCaps($fieldName, true, false));
				$fieldArray['label'] = isset($field['label']) ? $field['label'] : $fieldDefinition['display_name'];
				$fieldArray['default'] = isset($model->$fieldName) ? $model->$fieldName : $fieldDefinition['default_value'];
				$fieldArray['arrayFlag'] = $fieldDefinition['array_flag'] == '1';
				if ($fieldDefinition['type_name'] == 'REFERENCE') {
					$referenceModelClassname = Sodapop_Inflector::underscoresToCamelCaps(strtolower($fieldDefinition['ref_table_name']), false);
					$referenceModel = new $referenceModelClassname();
					if ($field['visualization'] == 'editor') {
						$fieldArray['type'] = 'form';
						$fieldArray['form'] = $this->getModelForm($referenceModel);
					} else {
						$referenceFieldDefinitions = $referenceModel->getFieldDefinitions();
						$fieldArray['type'] = 'select';
						$optionQuery = (isset($field['query']) ? $field['query'] : 'SELECT '.(isset($field['select']) ? $field['select'] : 'id, '.strtolower($referenceFieldDefinitions['column_name'])).' FROM '.strtolower($fieldDefinition['ref_table_name']).' '.(isset($field['where']) ? 'WHERE '.$field['where'] : '').' '.(isset($field['order']) ? 'ORDER BY '.$field['order'] : ''));
						$optionsResult = $this->user->connection->runQuery($optionQuery);
						$fieldArray['options'] = array();
						foreach ($optionsResult['data'] as $optionRow) {
							$fieldArray['options'][$optionRow[0]] = $optionRow[1];
						}
					}
				} else {
					$fieldArray['type'] = $fieldDefinition['type_name'];
				}			
			}
		}
		return $fieldArray;
	}

	protected function getInitialActions() {
		$retval = array();
		if ($this->model instanceof Sodapop_Database_Form_Abstract) {
			foreach ($this->model->getFormStatuses() as $statusId => $status) {
				if ($status['initial_flag'] == 1 && $this->user->hasFormPermission(strtolower(Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, false)), $status['adjective'], 'CALL' )) {
					$retval[] = array(strtolower(substr($this->modelClassname, 0, 1)).substr($this->modelClassname, 1).'/'.strtolower($status['verb']) => $status['display_verb']);
				}
			}
		} else {
			if ($this->user->hasTablePermission(strtolower(Sodapop_Inflector::camelCapsToUnderscores($this->modelClassname, false)), 'INSERT')) {
				$retval[] = array(strtolower(substr($this->modelClassname, 0, 1)).substr($this->modelClassname, 1).'/insert' => 'Insert');
			}
		}
		return $retval;
	}

	protected function createAlias($modelName) {
		$keywords = array('order', 'table' , 'form', 'column', 'field');
		$retval = Sodapop_Inflector::camelCapsToUnderscores($modelName, true);
		if (in_array($retval, $keywords)) {
			return $retval.'z';
		} else {
			return $retval;
		}
	}
}
