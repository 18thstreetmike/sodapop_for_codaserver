<?php
/*
-------------------------------------------------------------
HessianPHP - Binary Web Services for PHP

Copyright (C) 2004-2005  by Manolo Gómez
http://www.hessianphp.org

Hessian Binary Web Service Protocol by Caucho(www.caucho.com) 

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License as published by the Free Software Foundation; either
version 2.1 of the License, or (at your option) any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public
License along with this library; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

You can find the GNU General Public License here
http://www.gnu.org/licenses/lgpl.html
or in the license.txt file in your source directory.

If you have any questions or comments, please email:
vegeta.ec@gmail.com

*/

include_once 'Protocol.php';
include_once 'Hessian.php';
include_once 'Http.php';

/**
 * Represents a remote Hessian service endpoint with things such as
 * url, remote methods, security and several connection options
 * 
 * @package HessianPHP.Client
 * @author Vegeta
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/
class HessianProxy extends FilterContainer{
	var $url;
	var $remoteMethods = array();
	var $options;
	var $error = false;
	var $parser;
	var $writer;
	var $http;
	var $callingContext = array();

	function HessianProxy($url,$options=false){
		$this->url = $url;
		$this->writer = &new HessianWriter();
		$this->parser = &new HessianParser();
		$this->http = &new SocketHttpConnection($url,$options);
		$this->http->addHeader('Content-type','application/binary');
		$this->options = $options;
		$config = &HessianConfig::globalConfig();
		$this->errorLog = &$config->errorLog;
		
		// general options
		if(!empty($config->remoteMethods[$url])){
			foreach($config->remoteMethods[$url] as $method){
				$this->remoteMethod(trim($method));
			}
		}
		// local methods
		if(isset($options['methods'])){
			$methods = split(',',$options['methods']);
			foreach($methods as $method){
				$this->remoteMethod(trim($method));
			}
		}
		// global filter initialization
		if(!empty($config->filters)){
			foreach($config->filters as $key=>$value){
				// <<<<<< EXPERIMENTAL >>>>>>
				// This is a way to use the global filters in a per proxy fashion
				// although this isn't required since we can use filters defined in the options, see below
				/*
				if(phpversion() < 5)
					$fil = $value; // copy
				else
					eval('$fil = clone $value;');

				$fil->init($this);
				$this->filters[$key] = $fil;
				*/
				$filter = &$config->filters[$key];
				$filter->init($this);
				//$this->filters[$key] = $value;
				$this->addFilter($filter,$key);
			}
		}
		// per proxy filter configuration
		if(isset($options['filters']) && is_array($options['filters'])){
			foreach($options['filters'] as $key=>$value){
				if(!is_object($value)) {
					$this->notifyError(new HessianError('Incorrect filter definition format'));
					break;
				}
				$filter = &$options['filters'][$key];
				$filter->init($this);
				$this->addFilter($filter,$key);
			}
		}
	}

	/**
	 * Registers a remote method name. Useful for store description of services
	 * and resolve naming conflicts due to case sensitivity
	 *  
	 * @param string name Name of the remote method
	 * @access public
	 **/
	function remoteMethod($name){
		$phpmethod = strtolower($name);
		$this->remoteMethods[$phpmethod] = $name;
	}

	/**
	 * Returns the exact case sesitive name of a registered remote method
	 *  
	 * @param string method case insensitive name of the method
	 * @return string case sensitive name of the method
	 * @access public
	 **/
	function resolveMethod($method){
		$checkMethod = strtolower($method);
		// ugly
		$config = &HessianConfig::globalConfig();
		if(isset($config->remoteMethods[$this->url][$checkMethod])) {
			return $config->remoteMethods[$this->url][$checkMethod];
		}
		
		if(isset($this->remoteMethods[$checkMethod]))
			return $this->remoteMethods[$checkMethod];
		return $method;
	}
	
	/**
	 * Sets a connection option that will be passed to the Hessian proxy
	 * when called. Format is a pair key/value
	 *  
	 * @param string name Key
	 * @param string value Value
	 * @access public
	 **/
	function setOption($name,$value){
		$this->options[$name] = $value;
	}

	function getOption($name){
		if(isset($this->options[$name]))
			return $this->options[$name];
		return false;
	}	

	/**
	 * Performs a remote call taking in account whatever filters have been defined for this proxy
	 *  
	 * @param string method name of the remote method
	 * @param array params Array containing the values to send
	 * @access public
	 **/
	function call($method,$params){
		$this->error = null;
		$this->callingContext = array('method'=>$method,'params'=>$params,'result'=>null);
		if(empty($this->filters))
			return $this->executeCall($method,$params);

		$wrapper = &new ProxyFilter($method,$params);
		$this->filters['__default__'] = &$wrapper;
		$chain = &new FilterChain($this->filters);
		$chain->doFilter($this);
		return $wrapper->result;
	}

	/**
	 * Performs the actual remote call
	 *  
	 * @param string method name of the remote method
	 * @param array params Array containing the values to send
	 * @access public
	 **/
	function executeCall($method,$params){
		if($this->http->hasError()) {
			return $this->notifyError($this->http->error);
		}

		$this->writer->clearRefs();

		$method = $this->resolveMethod($method);
		$data = &$this->writer->writeCall($method,$params);
		
		if(HessianError::isError($data)){
			return $this->notifyError($data);
		}

		$reply = $this->http->POST($data);
		if($this->http->hasError()) {
			return $this->notifyError($this->http->error);
		}

		$this->parser->setStream($reply);
		$this->parser->clearRefs();
		$result = &$this->parser->parseReply();
		if(HessianError::isError($result)){
			return $this->notifyError($result);
		}
		return $result;
	}

		/**
	 * Notifies the HessianErrorLog object that handles the error
	 *  
	 * @param Object error An error object
	 * @return boolean always false, as it denotes an error
	 * @access public
	 **/

	function notifyError($error){
		$this->error = $error;
		$this->errorLog->notifyError($error);	
		return false;
	}
}

if(phpversion()<5)
	include_once 'HessianClient4.php';
else
	include_once 'HessianClient5.php';

?>