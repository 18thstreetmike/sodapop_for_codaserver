<?php
/**
 * Description of indexController
 *
 * @author michaelarace
 */
class Standard_Controller_Index extends Sodapop_Controller {
    public function preDispatch() {
        $this->view->stylesheets = array('/styles/style.css');
        $this->view->javascripts = array('/scripts/standard.js', '/scripts/jquery-1.4.1.min.js');
		$this->view->currentTab = 'index';
    }

    public function indexAction() {
       
    }
}
