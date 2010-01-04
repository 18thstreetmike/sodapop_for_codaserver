<?php
/**
 * This class is the Toasty implementation of the Sodapop_View
 *
 * @author michaelarace
 */
require_once 'Toasty.class.php';

class Sodapop_View_Toasty extends Sodapop_View_Abstract {
    protected $toasty = null;

    public function  __construct($config, $application) {
	parent::__construct($config, $application);
    }

    public function init() {
	$config = array();
	foreach ($this->config as $key => $variable) {
	    if ($key == 'widget_class') {
		$config['widget_class'] = $variable;
	    } else if ($key == 'aggregate_blocks') {
		$config['aggregate_blocks'] = $variable;
	    } else if ($key == 'root_directory') {
		$config['root_directory'] = $variable;
	    } else if ($key == 'working_directory') {
		$config['working_directory'] = $variable;
	    } else if ($key == 'extension') {
		$config['extension'] = $variable;
	    }
	}
	$this->toasty = new Toasty($config);
    }

    public function render() {
	// add the view's variables to the Toasty
	foreach ($this->fields as $key => $value) {
	    $this->toasty->$key = $value;
	}

	// render the view portion of the template to a string
	$viewContent = $this->toasty->render($this->viewFile, null, true, false, false, true);

	// if a layout is to be used, save the view content to the appropriate viewContent variable and render the view.
	if ($this->layoutFile) {
	    // strip XML declaration
	    $viewContent = str_replace('<?xml version="1.0"?>', '', $viewContent);
	    $this->toasty->viewContent = $viewContent;
	    $viewContent = $this->toasty->render($this->layoutFile, null, true, false, false, true);
	}

	$this->toasty->cleanup();

	return $viewContent;
    }
}
