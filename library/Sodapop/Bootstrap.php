<?php
/**
 * The base class for the Sodapop Bootstrapper.
 *
 * @author michaelarace
 */
class Sodapop_Bootstrap {
    protected $application = null;

    public function __construct($application) {
	$this->application = $application;
    }
}
