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

    public $view;

    public $controller;

    public $action;

    protected $viewPath = 'index/index';

    protected $layoutPath = 'layout';

    public function __construct($application, $request, $view) {
	$this->session = $_SESSION;
	$this->application = $application;
	$this->user = &$application->user;
	$this->request = $request;
	$this->view = $view;

	// check if the user is the public user or not, let the view know.
	if (strtoupper($this->user->username) == strtoupper($this->application->config['model.database.public_user'])) {
	    $this->view->loggedIn = false;
	} else {
	    $this->view->loggedIn = true;
	}

	// this is the code that automatically determines the tabs from the sitemap file, if desired
	if (isset($this->application->config['sitemap.automatic_tabs']) && $this->application->config['sitemap.automatic_tabs'] == '1') {
	    $this->view->tabs = $this->buildTabsFromPermissions($this->application->navigation);
	}
    }

    public function setViewPath($viewPath) {
	$this->viewPath = $viewPath;
    }

    public function setLayoutPath($layoutPath) {
	$this->layoutPath = $layoutPath;
    }

    public function preDispatch() {

    }

    public function postDispatch() {
	
    }

    public function render () {
	$this->view->controller = $this->controller;
	$this->view->action = $this->action;
	$this->view->request = $this->request;
	$this->view->viewFile = '/'.$this->viewPath;
	$this->view->layoutFile = '/../layouts/'.$this->layoutPath;
	return $this->view->render();
    }

    public function cleanup() {
	
    }

    public function forward($controller, $action) {
	$this->application->loadControllerAction($controller, $action, $this->request, $this->view, $this->view->baseUrl);
    }

    protected function buildTabsFromPermissions($tabGroup) {
	$tabs = array();
	foreach ($tabGroup as $navigationTab) {
	    if (isset($navigationTab['model'])) {
		if ($this->application->user->hasModelViewPermission($navigationTab['model'])) {
		    $tab = new stdClass();
		    $tab->id = $navigationTab['id'];
		    $tab->label = $navigationTab['label'];
		    $tab->url = $navigationTab['url'];
		    $tabs[] = $tab;
		}
	    } else if (isset($navigationTab['permission'])) {
		$permissions = explode(',', $navigationTab['permission']);
		foreach ($permissions as $permission) {
		    if ($this->application->user->hasPermission($permission)) {
			$tab = new stdClass();
			$tab->id = $navigationTab['id'];
			$tab->label = $navigationTab['label'];
			$tab->url = $navigationTab['url'];
			$tabs[] = $tab;
		    }
		}
	    } else if (isset($navigationTab['application_permission'])) {
		$permissions = explode(',', $navigationTab['application_permission']);
		foreach ($permissions as $permission) {
		    if ($this->application->user->hasApplicationPermission($permission)) {
			$tab = new stdClass();
			$tab->id = $navigationTab['id'];
			$tab->label = $navigationTab['label'];
			$tab->url = $navigationTab['url'];
			$tabs[] = $tab;
		    }
		}
	    } else if (isset($navigationTab['server_permission'])) {
		$permissions = explode(',', $navigationTab['server_permission']);
		foreach ($permissions as $permission) {
		    if ($this->application->user->hasServerPermission($permission)) {
			$tab = new stdClass();
			$tab->id = $navigationTab['id'];
			$tab->label = $navigationTab['label'];
			$tab->url = $navigationTab['url'];
			$tabs[] = $tab;
		    }
		}
	    } else {
		$tab = new stdClass();
		$tab->id = $navigationTab['id'];
		$tab->label = $navigationTab['label'];
		$tab->url = $navigationTab['url'];
		$tabs[] = $tab;
	    }
	}
	return $tabs;
    }
}
