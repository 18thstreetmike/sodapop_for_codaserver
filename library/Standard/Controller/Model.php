<?php
/**
 * Description of indexController
 *
 * @author michaelarace
 */
class Standard_Controller_Model extends Sodapop_Controller {
    protected $model;
    
    public function preDispatch() {
    	$modelClassname = str_replace('Controller','', get_class($this));
    	$this->model = new $modelClassname();
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
		$this->view->grid = $this->buildGrid($filterVars);
		$this->view->data = $this->getData($filterVars);
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
}
