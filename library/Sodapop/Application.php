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

    public $environment = null;

    protected $view = null;

    public function __construct($environment, $config) {
	$this->environment = $environment;

	$this->config = Sodapop_Application::parseIniFile($environment, $config, str_replace('/public/index.php', '', $_SERVER['SCRIPT_FILENAME']));

	
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
	$this->view->init();

	// initialize the user
	$databaseClass = 'Sodapop_Database_Codaserver';
	if (array_key_exists('model.database.driver', $this->config)) {
	    $databaseClass = $this->config['model.database.driver'];
	}
	if (isset($_SESSION['user'])) {
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
	$requestVariablesNumeric = array();

	// figure out the root path
	$indexPath = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
	$routePath = str_replace($indexPath.'/', '', $_SERVER['REQUEST_URI']);

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

		    $this->loadControllerAction($controller, $action, $request, $this->view, $indexPath);
		}

		
	    }

	}
	
	// resolve the controller and action, set up the request.
	$routePathParts = explode('/', $routePath);
	if (count($routePathParts) == 0 || trim($routePathParts[0]) == '') {
	    $controller = 'index';
	    $action = 'index';
	} else if (count($routePathParts) == 1) {
	    $controller = $routePathParts[0];
	    $action = 'index';
	} else if (count($routePathParts) == 2) {
	    $controller = $routePathParts[0];
	    $action = $routePathParts[1];
	} else {
	    $controller = $routePathParts[0];
	    $action = $routePathParts[1];
	    for ($i = 2; $i < count($routePathParts); $i++) {
		$requestVariablesNumeric[$i - 2] = $routePathParts[$i];
		if ($i % 2 == 0 && isset($routePathParts[$i + 1])) {
		    $requestVariables[$routePathParts[$i]] = $routePathParts[$i + 1];
		}
	    }
	}
	foreach($_REQUEST as $name => $value) {
	    $requestVariables[$name] = $value;
	}


	$request = new Sodapop_Request();
	$request->setRequestVariables($requestVariables);
	$request->setRequestVariablesNumeric($requestVariablesNumeric);

	$this->loadControllerAction($controller, $action, $request, $this->view, $indexPath);

    }

    public function loadControllerAction($controller, $action, $request, $view, $baseUrl) {
	$actionMethod = $action.'Action';
	@include_once($this->config['controller.directory'].'/'.ucfirst($controller).'Controller.php');
	$controllerName = ucfirst($controller).'Controller';
	$controllerObj = new $controllerName (&$this, $request, $view);
	$controllerObj->controller = $controller;
	$controllerObj->action = $action;
	$controllerObj->setViewPath($controller.'/'.$action);
	$controllerObj->view->baseUrl = $baseUrl;
	$controllerObj->preDispatch();
	$controllerObj->$actionMethod();
	$controllerObj->preDispatch();
	$output = $controllerObj->render();
	$controllerObj->cleanup();
	echo $output;
	exit();
    }

    public static function parseIniFile($environment, $config, $applicationRoot) {
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

	$newConfig = array();
	for ($i = count($loadOrder) - 1; $i >= 0; $i--) {
	    foreach($config[$loadOrder[$i]] as $attribute => $value) {
		$value = str_replace('APPLICATION_ROOT', $applicationRoot, $value);
		$newConfig[$attribute] = $value;
	    }
	}
	if (!isset($newConfig['view.themes.root_directory'])) {
	    $newConfig['view.themes.root_directory'] = $applicationRoot.'/../library/Themes';
	}
	if (!isset($newConfig['view.themes.current'])) {
	    $newConfig['view.themes.current'] = 'Monochrome';
	}
	if (!isset($newConfig['bootstrap.file_path'])) {
	    $newConfig['bootstrap.file_path'] = $applicationRoot.'/Bootstrap.php';
	}
	if (!isset($newConfig['controller.directory'])) {
	    $newConfig['controller.directory'] = $applicationRoot.'/application/controllers';
	}

	return $newConfig;
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
	    $regex .= '/';
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

/**
 * Most of Sodapop's magic goes through this function.
 *
 * @param string $className
 */
function __autoload($className) {
    $classNameParts = explode('_', $className);
    @include_once(implode('/', $classNameParts).'.php');
    if (!class_exists($className)) {
		// test standard controllers
		switch ($className) {
			case 'IndexController':
				createClass('IndexController', 'Standard_Controller_Index');
				break;
			case 'AuthenticationController':
				createClass('AuthenticationController', 'Standard_Controller_Authentication');
				break;
		}
		if (!class_exists($className)) {
		   // start looking for models in the user's table list, then their form list
		   if ($_SESSION['user']) {
			  if ($_SESSION['user']->hasTablePermission(Sodapop_Inflector::camelCapsToUnderscores($className, false), 'SELECT')) {
				 $_SESSION['user']->connection->defineTableClass(Sodapop_Inflector::camelCapsToUnderscores($className, false));
			  } else if ($_SESSION['user']->hasFormPermission(Sodapop_Inflector::camelCapsToUnderscores($className, false), null, 'VIEW')) {
				 $_SESSION['user']->connection->defineFormClass(Sodapop_Inflector::camelCapsToUnderscores($className, false));
			  }
		   }
		}
		if (!class_exists($className)) {
			
		}
    }
}

function __unserialize($className) {
    __autoload($className);
}

function createClass($className, $extends, $fields = array()) {
    $classDef = 'class '.$className.' extends '.$extends.' { ';
    foreach ($fields as $name => $value) {
	$classDef .' $'.$name.' = "'.$value.'"; ';
    }
    $classDef .= '}';
    eval ($classDef);
}

function determine_mime_type($filePath, $mimeFile = 'mime.ini') {
    if (function_exists('finfo_open')) {
	$finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
	$retval = finfo_file($finfo, $path);
	finfo_close($finfo);
	return $retval;
    } else {
	$types = parse_ini_file($mimeFile);
	$extension = substr($filePath, strrpos($filePath, '.') + 1);
	if (isset($types[$extension])) {
	    return $types[$extension];
	} else {
	    return 'application/octet-stream';
	}
    }
}
