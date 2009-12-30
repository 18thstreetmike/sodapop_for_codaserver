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

/**
 * Simple chain of responsibility implementation that executes a series of filters in order
 * 
 * @package HessianPHP.Filters
 * @author Vegeta
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/
class FilterChain{
	var $filters;
	var $current;

	function FilterChain(&$filters){
		foreach($filters as $key=>$value){
			$this->filters[] = &$filters[$key];
		}
		//$this->filters = &$filters;
		$this->current = -1;
	}

	function isChainDone(){
		return $this->current >= count($this->filters);
	}

	/**
	 * Recursive method that continues the execution of the next filter in the chain
	 * Includes two chain finalization checks before and after the filter execution
	 * because an explicit call to doFilter() inside the filter object can end the
	 * execution
	 *  
	 * @param mixed context context the filter works with
	 **/
	function doFilter(&$context){
		$this->current++;
		if($this->current >= count($this->filters)) {
			return;
		}
		$next = &$this->filters[$this->current];
		$next->execute($context,$this);
		if($this->current >= count($this->filters)) {
			return;
		}
		$this->doFilter($context);
	}
}

/**
 * Filter base class
 * 
 * @package HessianPHP.Filters
 * @author Vegeta
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/
class InterceptingFilter{
	/**
	 * Filter implementation goes here. Depending of the position of the call to $chain->doFilter($context);
	 * this can be a before, after or around type of filter.
	 * If the call is never executed, the default behaviour is a before filter.
	 *  
	 * @param object context The context the filter read and writes to
	 * @param FilterChain chain The filter chain to be followed
	 * @access public
	 **/
	function execute(&$context,&$chain){}
	/**
	 * Performs optional initialization tasks in the filter whenever is assigned to a HessianClient
	 *  
	 * @param object context The context the filter read and writes to
	 * @access public
	 **/
	function init(&$context){}
}

/**
 * Simple class that represents a container for InterceptingFilter objects
 * 
 * @package HessianPHP.Filters
 * @author Vegeta
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/
class FilterContainer{
	var $filters = array();

	/**
	 * Adds a filter to the container with an optional name. If no name is declared, the name of the class of the filter
	 * will be used instead
	 *  
	 * @param object context The context the filter read and writes to
	 * @access public
	 **/
	function addFilter(&$filter,$name=''){
		if(empty($name))
			$name = get_class($filter);
		$this->filters[$name] = &$filter;
	}

	function removeFilter($name){
		unset($this->filters[$name]);
	}

}

/**
 * Filter that wraps the HessianClient actual call. It is required by the framework to operate correctly
 * 
 * @package HessianPHP.Filters
 * @author Vegeta
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/
class ProxyFilter extends InterceptingFilter{
	var $method,$params,$result;
	
	function ProxyFilter($method,&$params){
		$this->method = $method;
		$this->params = &$params;
	}

	function execute(&$context,&$chain){
		$this->result = $context->executeCall($this->method,$this->params);
		$context->callingContext['result'] = $this->result;
		$chain->doFilter($context);
	}
}

// Default filters

/**
 * Filter that configures php's error reporting mechanism to all but notices.
 * This filter is necessary to work with PHP 4.4.x and newer PHP 5 versions 
 * until Protocol.php is refactored to work with references the way
 * these platforms require or they remove the notice :).
 * 
 * This filter is enabled by default
 * 
 * @package HessianPHP.Filters
 * @author Vegeta
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/
class PHPErrorReportingFilter extends InterceptingFilter{
	var $error;
	var $prevError;

	function PHPErrorReportingFilter(){
		$this->error = E_ALL ^ E_NOTICE;
	}

	function execute(&$context,&$chain){
		$this->prevError = error_reporting($this->error);
		$chain->doFilter($context);
		error_reporting($this->prevError);
	}
}

/**
 * Debugging filter that saves the incoming and outgoing binary streams to files defined in proxy configuration options.
 * It accepts the following parameters:
 * - in_stream_file : name of the file for the incoming (reply) stream
 * - out_stream_file : name of the file for the outgoing (call) stream
 * - dump_detail : detail of the output, can be 'simple' or 'advanced'
 * - dump_mode : if this is set to 'save' every call will generate a new file, if set to 'append' the output will be
 * appended to the file
 * 
 * @package HessianPHP.Filters
 * @author Vegeta
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/
class StreamDumpFilter extends InterceptingFilter{
	function execute(&$context,&$chain){
		$chain->doFilter($context);
		$options = $context->options;
		$inFile = @$options['in_stream_file'];
		$outFile = @$options['out_stream_file'];

		if($inFile) {
			$this->saveFile($inFile,$context,'in');
		}
		if($outFile)
			$this->saveFile($outFile,$context,'out');
	}

	function saveFile($file,&$context,$type){
		$stream = '';
		if($type=='in')
			$stream = $context->parser->stream;
		else
			$stream = $context->writer->stream;
		
		$detail = @$context->options['dump_detail'];
		$mode = @$context->options['dump_mode'];
		if(!in_array($mode,array('save','append') ) )
			$mode = 'save';
		if(!in_array($detail,array('simple','advanced') ) )
			$detail = 'simple';
		
		$data = '';
		if($detail == 'advanced'){
			if($type=='in')
				$data = "INCOMING payload for URL: ".$context->url."\n";
			else
				$data = "\nOUTGOING payload for URL: ".$context->url."\n";
			$data .= "Method: ".$context->callingContext['method']."\n";
			$data .= "Time: ".date("Y-m-d H:i:s")."\nData:\n";
			$data .= $stream;
			$data .= "\n\n";
		} else
			$data .= $stream;
		if($mode == 'save')
			$handle = fopen($file, 'w+');
		else
			$handle = fopen($file, 'a+');

		fwrite($handle, $data);
		fclose($handle);
	}

}

/**
 * Logs proxy activity, including method calling and errors in a defined format.
 * Note that all filter's log messages is static data (global)
 * 
 * @package HessianPHP.Filters
 * @author Vegeta
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/
class LogFilter extends InterceptingFilter{

	function &getMessages(){
		static $messages = array();
		return $messages;
	}

	function logMessage($msg){
		$messages = &LogFilter::getMessages();
		$messages[] = date("Y-m-d H:i:s - ").$msg;
	}

	function init(&$context){
		$msg = &LogFilter::getMessages();
		LogFilter::logMessage('Initializing Hessian Client for: '.$context->url);
	}

	function execute(&$context,&$chain){
		LogFilter::logMessage('Calling method '.$context->callingContext['method']);
		$chain->doFilter($context);
		if($context->error){
			$msg = 'Error in method '.$context->callingContext['method'].' Message is: '.$context->error->message;
			LogFilter::logMessage($msg);
		} 
	}
}

?>