<?php
/**
 * Description of indexController
 *
 * @author michaelarace
 */
class Standard_Controller_Model extends Sodapop_Controller {
    protected $model;
    
    public function preDispatch() {
    	$modelClassname = Sodapop_Inflector::singularize(str_replace('Controller','', get_class($this)));
    	$this->model = new $modelClassname();
        $this->view->stylesheets = array('/styles/style.css');
        $this->view->currentTab = 'index';
    }

    public function indexAction() {
       $this->forward(strtolower(str_replace('Controller','', get_class($this))), 'list');
    }
    
    public function listAction() {
		$this->request->
	}
}
