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
        $orders = array();
        try {
            $customers = $this->user->connection->runQuery('SELECT * FROM customers');
        } catch(Sodapop_Database_Exception $e) {
            var_dump($e);
        }
        $this->view->orders = $customers;
    }
}
