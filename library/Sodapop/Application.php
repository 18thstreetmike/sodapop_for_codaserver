<?php
/**
 * Description of Application
 *
 * @author marace
 */

class Sodapop_Application {
    public $config = null;

    public $routes = null;

    public $user = null;

    public $title = null;

    public $description = null;

    public $navigation = null;

    public $availableModels = null;

    public $models = null;

    protected $view = null;

    public function __construct($environment, $config) {
	// load the config
	$loadOrder = array();
	switch ($environment){
	    case 'development':
		$loadOrder[] = 'development';
	    case 'testing':
		$loadOrder[] = 'testing';
	    case 'production':
		$loadOrder[] = 'production';
		break;
	}

	$this->config = array();
	for ($i = count($loadOrder) - 1; $i >= 0; $i--) {
	    foreach($config[$loadOrder[$i]] as $attribute => $value) {
		$value = str_replace('APPLICATION_ROOT', str_replace('/public/index.php', '', $_SERVER['SCRIPT_FILENAME']), $value);
		$this->config[$attribute] = $value;
	    }
	}
	if (!isset($this->config['bootstrap.file_path'])) {
	    $this->config['bootstrap.file_path'] = str_replace('/public/index.php', '', $_SERVER['SCRIPT_FILENAME']).'/Bootstrap.php';
	}
	if (!isset($this->config['controller.directory'])) {
	    $this->config['controller.directory'] = str_replace('/public/index.php', '', $_SERVER['SCRIPT_FILENAME']).'/application/controllers';
	}



	// load the routes
	$routesFilePath = '../configuration/routes.yaml';
	if (array_key_exists('routes.file_path', $this->config)) {
	    $routesFilePath = $this->config['routes.file_path'];
	}
	if(file_exists($routesFilePath)) {
	    $tempRoutes = Sodapop_Yaml::loadFile($routesFilePath);
	    if ($tempRoutes) {
		$this->routes = $this->processRoutesFile($tempRoutes['routes']);
	    }
	}

	// load the sitemap
	$sitemapFilePath = '../configuration/sitemap.yaml';
	if (array_key_exists('sitemap.file_path', $this->config)) {
	    $sitemapFilePath = $this->config['sitemap.file_path'];
	}
	if(file_exists($sitemapFilePath)) {
	    $tempSitemap = Sodapop_Yaml::loadFile($sitemapFilePath);
	    if ($tempSitemap) {
		$this->title = $tempSitemap['title'];
		$this->description = $tempSitemap['description'];
		$this->navigation = $tempSitemap['navigation'];
		$this->availableModels = $tempSitemap['available_models'];
	    }
	}

	// load the models
	$modelsFilePath = '../configuration/models.yaml';
	if (array_key_exists('models.file_path', $this->config)) {
	    $modelsFilePath = $this->config['models.file_path'];
	}
	if(file_exists($modelsFilePath)) {
	    $tempModels = Sodapop_Yaml::loadFile($modelsFilePath);
	    if ($tempModels) {
		$this->models = $tempModels;
	    }
	}

	// instantiate the View
	$viewClass = 'Sodapop_View_Toasty';
	if (array_key_exists('view.renderer', $this->config)) {
	    $viewClass = $this->config['view.renderer'];
	}
	// grab the view configuration from the config variable.
	$viewConfig = array();
	foreach ($this->config as $configKey => $configValue) {
	    if (strpos($configKey, 'view.config.') !== false) {
		$viewConfig[substr($configKey, 12)] = $configValue;
	    }
	}

	$this->view = new $viewClass($viewConfig, $this);

	// initialize the user
	$databaseClass = 'Sodapop_Database_Codaserver';
	if (array_key_exists('model.database.driver', $this->config)) {
	    $databaseClass = $this->config['model.database.driver'];
	}
	
	if (isset($_SESSION['user']) && $_SESSION['user'] instanceof Sodapop_User) {
	    $this->user = $_SESSION['user'];
	} else {
	    $_SESSION['user'] = call_user_func(array($databaseClass, 'getUser'), $this->config['model.database.hostname'], $this->config['model.database.port'], $this->config['model.database.public_user'], $this->config['model.database.public_password'], $this->config['model.database.schema'], $environment, null);
	    $this->user = $_SESSION['user'];
	}

    }

    public function bootstrap () {
	require_once($this->config['bootstrap.file_path']);
	$bootstrap = new Bootstrap(&$this);
	if (method_exists($bootstrap, '_initAutoload')) {
	    $bootstrap->_initAutoload();
	}
	if (method_exists($bootstrap, '_initRoutes')) {
	    $bootstrap->_initRoutes();
	}
	if (method_exists($bootstrap, '_initSitemap')) {
	    $bootstrap->_initSitemap();
	}
	if (method_exists($bootstrap, '_initModel')) {
	    $bootstrap->_initModel();
	}
	if (method_exists($bootstrap, '_initView')) {
	    $bootstrap->_initView();
	}
	if (method_exists($bootstrap, '_initUser')) {
	    $bootstrap->_initUser();
	}
	return $this;
    }

    public function run() {
	$controller = null;
	$action = null;
	$requestVariables = array();

	// figure out the root path
	$indexPath = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);
	$routePath = str_replace($indexPath, '', $_SERVER['REQUEST_URI']);

	// see if it's in the routes
	foreach ($this->routes as $key => $route) {
	    if (preg_match($key, $routePath) > 0) {
		// figure out all of the variables
		$matches = null;
		preg_match($key, $routePath, $matches);
		foreach ($matches as $handle => $value) {
		    if (!is_numeric($handle)) {
			$requestVariables[$handle] = $value;
		    }
		}

		if (isset($route['url'])) {
		    $url = $route['url'];
		    $url = str_replace('APPLICATION_ROOT',  str_replace('/public/index.php', '', $_SERVER['SCRIPT_FILENAME']), $url);
		    foreach ($requestVariables as $variableName => $variableValue) {
			$url = str_replace(':'.$variableName, $variableValue, $url);
		    }
		    header('Location: '.$url);
		    exit;
		} else {
		    if (isset($route['controller'])) {
			$controller = $route['controller'];
		    }
		    if (isset($route['action'])) {
			$action = $route['action'];
		    }
		    if (isset($requestVariables['controller'])) {
			$controller = $requestVariables['controller'];
		    }
		    if (isset($requestVariables['action'])) {
			$action = $requestVariables['action'];
		    }
		    if ($controller == null) {
			$controller = 'index';
		    }
		    if ($action == null) {
			$action = 'index';
		    }

		    $request = new Sodapop_Request();
		    $request->setRequestVariables($requestVariables);

		    $this->loadControllerAction($controller, $action, $request, $this->view);
		}

		
	    }

	}
	
	// resolve the controller and action, set up the request.


    }

    public function loadControllerAction($controller, $action, $request, $view) {
	require_once($this->config['controller.directory'].'/'.$controller.'Controller.php');
	$controllerName = $controller.'Controller';
	$controllerObj = new $controllerName (&$this, $request, $view);
	$controllerObj->preDispatch();
	$controllerObj->$action();
	$controllerObj->preDispatch();
	$controllerObj->cleanup();
	exit();
    }

    private function processRoutesFile($routesArray) {
	$routes = array();
	foreach ($routesArray as $route) {
	    $urlParts = explode('/', $route['from']);
	    // figure out the key
	    $regex = '/';
	    foreach($urlParts as $part) {
		if ($regex != '/') {
		    $regex .= '\/';
		}
		if (substr($part, 0, 1) == ':') {
		    $regex .= '(?P<'.substr($part, 1).'>[^\/]*)';
		} else {
		    $regex .= $part;
		}
	    }
	    // add it to the routes array
	    if (isset($route['url'])) {
		$routes[$regex] = array('url' => $route['url']);
	    } else if ($route['controller']) {
		$routes[$regex] = array('controller' => $route['controller']);
		if ($route['action']) {
		    $routes[$regex]['action'] = $route['action'];
		}
	    }
	}
	return $routes;
    }
}

function __autoload($className) {
    $classNameParts = explode('_', $className);
    @include_once(implode('/', $classNameParts).'.php');
    if (!class_exists($className)) {
	// now it gets interesting.
    }
}
