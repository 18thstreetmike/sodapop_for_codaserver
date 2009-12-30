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
 * <BLOCKQUOTE>
 * Proxy that allows RPC method calls to a remote Hessian compatible web-service
 * It requires the url of the Hessian service. Tested against Java and PHP services.
 * 
 * It uses the overload extension of PHP 4 to execute the remote calls. PHP 5 supports overloading
 * natively. If your PHP installation doesn't support overload, you can use direct calls to the __call() 
 * method using the arguments as detailed in <A HREF="http://us3.php.net/manual/en/ref.overload.php">http://us3.php.net/manual/en/ref.overload.php</A> 
 * or in the documentation of this class
 * <BR>
 * Sample usage:<BR>
 * <BR>
 * <code>
 * $testurl = 'http://www.caucho.com/hessian/test/basic';<BR>
 * $proxy = &new HessianClient($testurl);<BR>
 * echo $proxy->hello();<BR>
 * </code>
 * <BR>
 * <B>WARNING:</B> As in PHP 4.3.x, all method name metadata (get_class_methods(), overload extension __call()) is internally 
 * lower-cased so if you call a camel case remote method name, like 'getUsers', the call will result into a no such
 * method fault because PHP will interpret the method name as 'getusers'. This is especially bad when calling Java
 * services where many method names are camel cased. Sad but true.<BR>
 * <BR>
 * To counterfeit this, use {@link HessianPHP.Hessian#remoteMethod remoteMethod()}  in the proxy object to tell Hessian how to call the method with the 
 * right name before executing the call. PHP 5 doesn't seem to be affected by this behavior.
 * <BR>
 * <BR>TODO: 
 * <UL>
 *	<LI>SSL support</LI>
 *	<LI>gzip compression?</LI>
 *	<LI>Enhance error handling and test, test, test...</LI>
 * </UL>
 * </BLOCKQUOTE>
 * 
 * @package HessianPHP.Client
 * @author Manolo Gómez
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/

class HessianClient{
	/**
	 * Constructor, requires the url of the remote Hessian service
	 *  
	 * @param string url Url of the remote service
	 **/
	function HessianClient($url,$options=false){
		$this->__hessian__proxy__ = &Hessian::getHessianProxy($url,$options);
	}
	
	/**
	 * PHP magic function used to execute a remote call to a remote Hessian service.
	 *  
	 * @param string method Method name
	 * @param array params Arguments
	 * @param mixed return Returned value
	 * @return mixed True if PHP 4, return value of the function otherwise
 	 * @access public 
	 **/
	function __call($method,$params,&$return){
		$return = $this->__hessian__proxy__->call($method,$params);
		return true;
	}

}

// Call the overload() function when appropriate
if (function_exists('overload')) {
   overload('HessianClient');
}

?>