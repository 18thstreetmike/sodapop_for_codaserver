<?php
/**
 * Description of indexController
 *
 * @author michaelarace
 */
class Standard_Controller_Index extends Sodapop_Controller {
    public function preDispatch() {
        $this->view->stylesheets = array('/styles/style.css');
        $this->view->currentTab = 'index';
    }

    public function indexAction() {
       
    }
}
