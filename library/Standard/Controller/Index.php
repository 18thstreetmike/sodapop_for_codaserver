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
	try {
	    $orders = $this->user->connection->runQuery('SELECT * FROM products');
	} catch(Sodapop_Database_Exception $e) {
	    var_dump($e->errors);
	}
	$this->view->orders = $orders;
    }
}
