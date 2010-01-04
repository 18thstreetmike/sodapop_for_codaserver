<?php
/**
 * Description of authenticationController
 *
 * @author michaelarace
 */
class Standard_Controller_Authentication extends Sodapop_Controller {
    public function loginAction() {
	if ($this->request->isPost()) {
	    if ($this->request->username && $this->request->password) {
		try {
		    // initialize the user
		    $databaseClass = 'Sodapop_Database_Codaserver';
		    if (array_key_exists('model.database.driver', $this->application->config)) {
			$databaseClass = $this->application->config['model.database.driver'];
		    }
		    $_SESSION['user'] = call_user_func(array($databaseClass, 'getUser'), $this->application->config['model.database.hostname'], $this->application->config['model.database.port'], $this->request->username, $this->request->password, $this->application->config['model.database.schema'], $this->application->environment, null);
		    $this->application->user = $_SESSION['user'];
		    

		} catch (Exception $e) {
		    $this->view->authenticationErrorMessage = $e->getMessage();
		}
	    }
	}
	$controller = $this->request->controller;
	$action = $this->request->action;

	$request = new Sodapop_Request();
	$requestVariables = array();
	foreach ($this->request->variables() as $name => $value) {
	    if (substr($name, 0, 8) == 'forward_') {
		$requestVariables[substr($name, 8)] = $value;
	    }
	}
	$request->setRequestVariables($requestVariables);
	$this->request = $request;
	
	$this->forward($controller, $action);
    }

    public function logoutAction () {
	try {
	    // initialize the user
	    $databaseClass = 'Sodapop_Database_Codaserver';
	    if (array_key_exists('model.database.driver', $this->application->config)) {
		$databaseClass = $this->application->config['model.database.driver'];
	    }

	    $_SESSION['user'] = call_user_func(array($databaseClass, 'getUser'), $this->application->config['model.database.hostname'], $this->application->config['model.database.port'], $this->application->config['model.database.public_user'], $this->application->config['model.database.public_password'], $this->application->config['model.database.schema'], $this->application->environment, null);
	    $this->application->user = $_SESSION['user'];
	} catch (Exception $e) {
	    $this->view->authenticationErrorMessage = $e->getMessage();
	}

	$this->forward('index', 'index');
    }
}
