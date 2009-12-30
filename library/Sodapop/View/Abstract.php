<?php

/**
 * This class is the abstract class for views in the Sodapop Framework.
 *
 * @author michaelarace
 */
abstract class Sodapop_View_Abstract {
    protected $fields = array();

    protected $application = null;

    protected $config = array();

    protected $layoutFile = null;

    protected $viewFile = null;

    public function  __construct($config, $application) {
	$this->config = $config;
	$this->fields['application'] = $application;
    }

    public function __get($name) {
	return $this->fields[$name];
    }

    public function __set($name, $value) {
	$this->fields[$name] = $value;
    }

    /*
     * This function initializes the Sodapop_View and is called immedately after the view is constructed.
     */
    public abstract function init();

    public function prerender($layoutPath, $viewPath) {
	if (file_exists($layoutPath)) {
	    $this->layoutFile = $layoutPath;
	}

	if (file_exists($viewPath)) {
	    $this->viewFile = $viewPath;
	}
    }

    /**
     * This function is called after the controller has returned and the layout and view are assigned to the view.
     * It should return a string that represents the rendered output so that the application can echo it appropriately.
     */
    public abstract function render();
}
