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

if( !class_exists('Exception')){
	class Exception{}
}

/**
 * Represents an error state from HTTP procotol communication
 * 
 * @package HessianPHP.Http
 * @author Manolo Gómez
 * @copyright Copyright (c) 2004
 * @version 1.0
 * @access public
 * @see HessianPHP.HttpCall
 **/
class HttpError extends Exception{
	var $headers;
	var $body;
	var $message;
	var $code;
	var $time;

	function HttpError($message='', $code=0, $headers=null,$body=null) {
		$this->message = $message;
		$this->code = $code;
		$this->headers = $headers;
		$this->body = $body;
		$this->time = date("Y-m-d H:i:s");
	}

	function getError(){
		return $this->error;
	}

	function getHeaders(){
		return $this->headers;
	}
	function getBody(){
		return $this->body;
	}

	function __toString(){
		if(phpversion() >= 5)
			return parent::__toString();
		$msg = "Message: ".$this->message."\n";
		$msg = "Code: ".$this->code."\n";
		$msg .= "Time: ".$this->time."\n";
		$msg .= "Headers: ".print_r($this->headers,true)."\n";
		$msg .= "Body: ".$this->body."\n";
		return $msg;
	}
}


/**
 * Abstract class that represents an Http connection to a Url
 * @package HessianPHP.Http
 * @author Manolo Gómez
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/
class HttpConnection{
	var $url;
	var $urlInfo;
	var $options;
	var $headers = array();
	var $responseHeaders = array();

	var $code;
	var $message;
	var $httpVersion;
	var $error = false;

	var $length = -1;


	function HttpConnection($url,$options=false){
		$this->url = $url;
		$this->options = $options;
		$this->initUrl();
	}

    /**
    * validate url data passed to constructor
    *
    * @return boolean
    * @access private
    */
    function initUrl()
    {
		$this->urlInfo = parse_url($this->url);
        if (!is_array($this->urlInfo) ) {
			$this->error = &new HttpError("Unable to parse URL $url");
			return FALSE;
        }
        if (!isset($this->urlInfo['host'])) {
			$this->error = &new HttpError("No host in URL {$this->url}");
            return FALSE;
        }
        if (!isset($this->urlInfo['port'])) {
            
            if (strcasecmp($this->urlInfo['scheme'], 'HTTP') == 0)
                $this->urlInfo['port'] = 80;
            elseif (strcasecmp($this->urlInfo['scheme'], 'HTTPS') == 0) 
                $this->urlInfo['port'] = 443;
                
        }
		$this->headers['Host'] = $this->urlInfo['host'];
		$this->headers['Connection'] = 'close';
		
		if (isset($this->urlInfo['user'])) {
            $this->headers['Authorization'] = 'Basic ' . base64_encode($this->urlInfo['user'] . ':' . $this->urlInfo['pass']);
        }

		if(isset($this->options['proxy_user']) && isset($this->options['proxy_pass']))	
			$this->headers['Proxy-Authorization'] = 'Basic ' . base64_encode(
				$this->options['proxy_user'] .':'. $this->options['proxy_pass']);	

		// if there is an option for credentials, this takes precedence over url info
		if(isset($this->options['username']) && isset($this->options['password'])) {
			$this->headers['Authorization'] = 'Basic ' . base64_encode($this->options['username'].':'.$this->options['password']);		
		}
        return TRUE;
    }

	/** @access public */
	function addHeader($name,$value){
		$this->headers[$name] = $value;
	}

	function hasError(){
		return is_object($this->error);
	}

	function POST($data){
		if(!$this->hasError())
			return;
		// add custom behavior in descendents
	}
}


/**
 * This class stablishes communication to a remote Http URL using sockets and raw
 * data transmission
 * @package HessianPHP.Http
 * @author Manolo Gómez
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/
class SocketHttpConnection extends HttpConnection{
	var $__socket;
	
	/**
	 * Sends an HTTP request using the POST method
	 *  
	 * @param string data Content to be sent
	 * @return string Response from remote server
	 **/
	function POST($data){
		parent::POST($data);
		$this->error = false;
		$this->headers['Content-length'] = strlen($data);
		$path = $this->urlInfo['path'];
		// NOTE: the entire URL is required for proxy connections
		$out = "POST $this->url HTTP/1.1\r\n";
		foreach($this->headers as $header => $value){
			$out .= "$header: $value\r\n";
		}
		$out .= "\r\n".$data;
		if($this->open()){
			$this->write($out);
			$response = $this->read();
			return $response;
		}
		return false;
	}

	/**
	 * Opens a socket connection to a remote host
	 *  
	 * @param string host Remote host
	 * @param int port Remote port
	 **/
	function open(){
		$timeout = 5;
		if(isset($this->options['timeout']) && is_int($this->options['timeout'])){
			$timeout = $this->options['timeout'];
		}
		if(isset($this->options['proxy_host']) && isset($this->options['proxy_port'])) {
			$this->__socket = @fsockopen($this->options['proxy_host'], $this->options['proxy_port'], $errno, $errstr, $timeout);
		} else {
			$this->__socket = @fsockopen($this->urlInfo['host'], $this->urlInfo['port'], $errno, $errstr, $timeout);
		}

		if (!$this->__socket) {
			$this->error = &new HttpError("HttpError: Error opening socket communication: $errstr ($errno)");
			return false;
		}
		return true;
	}

	/** @access protected */
	function close(){
		fclose($this->__socket);
	}
	
	/** @access protected */
	function write($data){
		fwrite($this->__socket,$data);
	}

	/**
	 * Parses incoming header information and checks for repeated headers
	 *  
	 * @param string head first line of HTTP headers
	 * @access protected 
	 **/
	function parseHeaders($head=''){
		if($head == '')
			$head = trim(fgets($this->__socket, 4096));
		
		//parse header
		if(preg_match("/HTTP\/(1.[01]) ([\d]{3})[ ]*(.*)/i",$head,$parts)){
			$this->httpVersion = $parts[1];
			$this->code = $parts[2];
			$this->message = $parts[3];
		} else {
			$this->error = &new HttpError("HttpError: Malformed HTTP header",0,$this->headers);
			return false;
		}
		$this->responseHeaders[] = trim($head);
		while ($str = trim(fgets($this->__socket, 4096))) {
			$this->responseHeaders[] = trim($str);
			if(preg_match("/Content-length:[ ]+([\d]+)/i",$str,$headParts)){
				$this->length = $headParts[1];			
			}
		}

		// check for HTTP 100 Continue state and reparse headers, this happens in IIS with PHP5 as CGI
		switch($this->code){
			case '100': $this->parseHeaders();
		}
		return true;
	}

	/**
	 * Read the reply from the socket, parses incoming headers and returns the content
	 *  
	 * @return string body content of the response
	 **/
	function read(){
		if(!$this->parseHeaders()) return;
		$line = '';
		$body = '';

		while (!feof($this->__socket)){
			$line = fgets($this->__socket, 32768);
			$body .= $line;
		}
		$this->body = $body;
		
		// 
		if($this->code[0] == '3'){
			$this->error = &new HttpError("HttpError: Redirection is not supported: $this->message,$this->code",0,$this->headers,$this->body);
			return false;
		}

		if($this->code > 400){
			$this->error = &new HttpError("HttpError: $this->message,$this->code",0,$this->headers,$this->body);
			return false;
		}
		return $body;
	}
}

?>