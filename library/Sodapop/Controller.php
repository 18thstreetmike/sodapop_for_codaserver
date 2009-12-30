<?php
/**
 * This is the class that is extended to make application controllers.
 *
 * @author michaelarace
 */
class Sodapop_Controller {

    protected $user;

    protected $application;

    protected $session;

    protected $request;

    protected $view;

    public function __construct($application, $request, $view) {
	$this->session = $_SESSION;
	$this->application = $application;
	$this->user = $application->user;
	$this->request = $request;
	$this->view = $view;
    }

    public function preDispatch() {
	
    }

    public function postDispatch() {
	
    }

    public function cleanup() {
	
    }

    public function forward($controller, $action) {
	
    }
}
