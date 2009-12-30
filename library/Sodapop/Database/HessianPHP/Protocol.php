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

include_once 'Hessian.php';

/**
 * This is a helper class designed to work with byte conversion and representation of numbers
 * 
 * @package HessianPHP.Protocol
 * @author Manolo Gómez
 * @copyright Copyright (c) 2004
 * @version 1.0
 * @access public
 **/
class ByteUtils{

	/**
	 * Generates big endian byte representation of a number with a defined
	 * precision, 16 or 64 bits for example, default 32 bits.<BR>
	 *  
	 * This function is equivalent to do the transformation by hand with a fixed
	 * bit precision, as in (for a 32 bit representation):<BR>
	 *
	 * <code><BR>
	 * $b32 = $value >> 24;<BR>
	 * $b24 = ($value >> 16) & 0x000000FF;<BR>
	 * $b16 = ($value >> 8) & 0x000000FF;<BR>
	 * $b8 = $value & 0x000000FF;<BR>
	 * $bytes .= pack('c',$b32);<BR>
	 * $bytes .= pack('c',$b24);<BR>
	 * $bytes .= pack('c',$b16);<BR>
	 * $bytes .= pack('c',$b8);<BR>
	 * </code>
	 *
	 * @param long number number to be transformed
	 * @param int precision precision
	 * @return string byte representation of long number
	 * @access public 
	 **/
	function getIntBytes($number,$precision=32){
		switch($precision){
			case 16: $fill = 0x00FF; break;
			case 32: $fill = 0x000000FF; break;
			case 64: $fill = 0x00000000000000FF; break;
		}
		$start = $precision - 8;
		// $sh = bits to shift right
		$bytes = '';
		for($sh = $start ; $sh >= 8 ; $sh = $sh - 8){
			$value = ($number >> $sh) & $fill;
			$bytes .= pack('c',$value);	
		}
		// final byte
		$value = $number & $fill;
		$bytes .= pack('c',$value);
		return $bytes;
	}

	/**
	 * Returns a string with the byte representation of a IEEE 754 double in
	 * 64 bit precision. Works fine between PHP clients and servers but it uses
	 * a machine dependent byte packing representation (pack format "d").
	 *
	 * <B>WARNING:</B> Due to incompatible double formats among different machines, this function
	 * is not guaranteed to return the number with extreme accuracy, specially with periodic fractions
	 * such as 1.3333... Take this in account.<BR>
	 *  
	 * @param double number number to be transformed 
	 * @return string byte representation
	 * @access public 
	 **/
	function getFloatBytes($number) {
		$bin = ByteUtils::orderedByteString( pack("d", $number) );  // Machine-dependent size
		// check is deactivated
		/*if(strlen($bin) != 8) {
			echo "Sorry, your machine uses an unsupported double-precision floating point size.";
		}*/
		return $bin;
	}

	/**
	 * Test if this machine is a little endian architecture<BR>
	 * 
	 * Based in code from Open Sound Control (OSC) Client Library for PHP<BR>
	 * Author: Andy W Schmeder &lt;andy@a2hd.com&gt;<BR>
	 * Copyright 2003
	 *
	 * @return boolean is little endian?
	 * @access public 
	 **/
	function isLittleEndian() {
		$machineLong = pack("L", 1);  // Machine dependent
		$indepLong  = pack("N", 1);  // Machine independent
		
		if($machineLong[0] == $indepLong[0])
			return FALSE;
		return TRUE;
	}

	/**
	 * Returns a sequence of bytes in big endian order, it orders the string depending
	 * on machine architecture (big endian or little endian).<BR>
	 * 
	 * Based in code from Open Sound Control (OSC) Client Library for PHP<BR>
	 * Author: Andy W Schmeder &lt;andy@a2hd.com&gt;<BR>
	 * Copyright 2003
	 *
	 * @param string string sequence of bytes to order
	 * @return string big endian ordered sequence of bytes
	 * @access public 
	 **/
	function orderedByteString($string) {
		if(ByteUtils::isLittleEndian()) {
			$orderStr = '';
			for($i = 0; $i < strlen($string); $i++) {
				$index = (strlen($string)-1)-$i;
				$orderStr .= $string[$index];
			}
			return $orderStr;
		} 
		// No conversion necessary for big-endian architecture
		return $string;
	}	

}

/**
 * Base class for Hessian protocol handling objects. Contains methods to handle streams, references, mapping of classes 
 * and datetimes
 * 
 * @package HessianPHP.Protocol
 * @author Manolo Gómez
 * @copyright Copyright (c) 2005
 * @version 1.0
 * @access public
 **/
class HessianProtocolHandler{
	var $stream;
	var $pos;
	var $len;
	var $refs = array();
	var $error;
	var $dateProvider;
	var $typemap;

	function HessianProtocolHandler($stream=null){
		if($stream)
			$this->setStream($stream);
		// Recover default configuration data
		$config = &HessianConfig::globalConfig();
		$this->setTypeMap($config->typeMap);
		$this->setDateProvider($config->dateProvider);
	}
	
	function clearRefs(){
		$this->refs = array();
	}
	
	/**
	 * Sets the stream of bytes to parse
	 *  
	 * @param string stream Incoming stream
	 **/
	function setStream($stream){
		$this->stream = $stream;
		$this->len = strlen($stream);
		$this->pos =0;
	}

	function setDateProvider(&$provider){
		$this->dateProvider = $provider;
	}

	function setTypeMap(&$map){
		$this->typeMap = &$map;
	}

}

/**
 * <BLOCKQUOTE>
 * Hessian protocol parser, inspired and partially based on hessianlib.py by Caucho.
 * <BR>
 * TODO:
 * <UL>
 *	<LI>Handling of 'headers'</LI>
 * </UL>
 * </BLOCKQUOTE>
 * @package HessianPHP.Protocol
 * @author Manolo Gómez
 * @copyright Copyright (c) 2004
 * @version 1.0
 * @access public
 **/
class HessianParser extends HessianProtocolHandler{
	var $lastCode = false;
	//var $refs = array();

	/**
	 * Reads n bytes of the stream and increases internal pointer by that number
	 *  
	 * @param int num Number of bytes to read
	 * @return string Bytes read
	 * @access private 
	 **/
	function read($num){
		$byte = substr($this->stream,$this->pos,$num);
		$this->pos += $num;
		return $byte;
	}

	/**
	 * Main parsing function that reads the head code from the stream and returns the appropriate PHP value
	 * Thanks to Radu-Adrian Popescu for his patch to 'long' and 'date' deserializing.
	 *  
	 * @param string code Hessian object code 
	 * @return mixed parsed value
	 **/
	function &parseObject($code=''){
		if($code=='')
			$code = $this->read(1);
		$this->lastCode = $code;
		switch($code):
			case 'N':
				return null;
			case 'F':
				return false;
			case 'T':
				return true;
			case 'I': 
				$data = unpack('N', $this->read(4));
				return $data[1];
			case 'L':
				return $this->readLong(true);
			case 'd':
				$ts = $this->readLong();
				return $this->dateProvider->readDate($ts);
			case 'D':
				/*
				2005-09-14:
				Changed due to the "Fatal error: Only variables can be passed by reference" bug in PHP 5.1 
				
				old code:

				$bytes = ByteUtils::orderedByteString($this->read(8));
				$value = each(unpack("d",$bytes));
				return $value[1];
				
				*/
				$bytes = ByteUtils::orderedByteString($this->read(8));
				$val = unpack("d",$bytes);
				$value = array_pop($val);
				return $value;
			case 'B':
			case 'b':
				return $this->readBinary();
			case 'S':
			case 's':
			case 'X':
			case 'x':
				return $this->readString();
			case 'M': 
				return $this->parseMap();
			case 'V': 
				return $this->parseList();
			case 'R':
				$refStruct = unpack('N', $this->read(4));
				$numRef = $refStruct[1];
				if(isset($this->refs[$numRef]))
					return $this->refs[$numRef];
				else
					return new HessianError("Unresolved referenced object number $numRef",HESSIAN_PARSER_ERROR,0,$this->stream);
				break;
			case 'z':
				$this->end = true;
				return;
			case 'f':
				return $this->parseFault();
			default:
				return new HessianError("Unrecognized response type code '$code' or not implemented",HESSIAN_PARSER_ERROR,0,$this->stream);
		endswitch;
	}

	// Series of parsing method for the different elements in the Hessian spec

	function parseCall(){
		if($this->read(1) != 'c') {
			return new HessianError('Hessian Parser, Malformed call: Expected: c',HESSIAN_PARSER_ERROR,0,$this->stream);
		}
		$minor = $this->read(1);
		$major = $this->read(1);

		if($this->read(1) != 'm') {
			return new HessianError('Hessian Parser, Malformed call: Expected m',HESSIAN_PARSER_ERROR,0,$this->stream); 
		}
		return $this->parseObject('S');
	}

	function endStream(){
		if($this->pos == $this->len){
			$this->end = true;
			return true;
		}
		return false;
	}

	function parseReply(){
		if($this->read(1) != 'r') {
			return new HessianError('Hessian Parser, Malformed reply: expected r',HESSIAN_PARSER_ERROR,0,$this->stream);
		}
		$minor = $this->read(1);
		$major = $this->read(1);
		$value = $this->parseObject($this->read(1));
		if($this->read(1) == 'z')
			return $value;
	}

	function &parseFault(){
		$code = $this->read(1);
		$fault = array();
		// OJO: que quise hacer aqui?
		$map = array();
		$this->refs[] = &$map;
		while($code != 'z'){
			$key = &$this->parseObject($code);
			$value = &$this->parseObject();
			$map[$key] = $value;
			$code = $this->read(1);
		}
		$faultMessage = 'Service fault';
		if(isset($map['code']) && isset($map['message'])) {
			$faultMessage .= ': '.$map['message'];
			unset($map['message']);
		} 
		return new HessianError("Hessian Fault: $faultMessage",HESSIAN_FAULT,$map,$this->stream);

	}

	function &parseMap(){
		if($this->read(1)!='t') {
			return new HessianError('Malformed map format: expected t',HESSIAN_PARSER_ERROR,0,$this->stream);
		}
		$type = $this->readString();
		$code = $this->read(1);
		//$localType = TypeMap::getLocalType($type);
		$localType = $this->typeMap->getLocalType($type);
		if(!$localType)
			$map = array();	
		else {
			$map = &new $localType;
		}
		$this->refs[] = &$map;
		while($code != 'z'){
			$key = &$this->parseObject($code);
			$value = &$this->parseObject();
			if(!$localType)
				$map[$key] = $value;
			else
				$map->$key = $value;
			$code = $this->read(1);
		}
		return $map;
	}

	function &parseList(){
		$code = $this->read(1);
		// read type if exists
		if($code == 't'){ 
			$type = $this->readString();
			$code = $this->read(1);
		}
		// read list length if exists
		if($code == 'l') {
			$lenStruct = unpack('N', $this->read(4));
			$len = $lenStruct[1];
			$code = $this->read(1);
		}
		$list = array();		
		$this->refs[] = &$list;
		while($code != 'z'){
			$list[] = &$this->parseObject($code); 
			$code = $this->read(1);
		}
		return $list;
	}

	function readLong(){
		// Thanks Radu-Adrian Popescu
		$data = unpack('N2', $this->read(8));
		$value = $data[1]*256*256*256*256 + $data[2]; // +0.0; 
		return $value;
	}

	function readString(){
		$end = false;
		$string = '';
		while(!$end) {
			$tempLen = unpack('n',$this->read(2));
			$len = $tempLen[1];
		
			if($this->lastCode == 's' || $this->lastCode == 'x') {
				$this->lastCode = $this->read(1);
			} else
				$end = true;
			
			// Some UTF8 characters are represented with more than one byte to we need
			// to read every character to find out if we need to read in advance.
			for($i=0;$i<$len;$i++){
				$ch = $this->read(1);
				$charCode = ord($ch);
				if($charCode < 0x80)
					$string .= $ch;
				elseif(($charCode & 0xe0) == 0xc0){
					$string .= $ch.$this->read(1);
				} elseif (($charCode & 0xf0) == 0xe0) {
					$string .= $ch.$this->read(2);
				} else {
					return new HessianError("Bad utf-8 encoding",HESSIAN_PARSER_ERROR,0,$this->stream);
				}
			}
			//$end = true;
		}
		return utf8_decode($string);
	}

	function readBinary(){
		$end = false;
		$data = '';
		while(!$end) {
			$bytes = $this->read(2);
			$tempLen = unpack('n',$bytes);
			$len = $tempLen[1];
			$data .= $this->read($len);
			if($this->lastCode == 'b') {
				$this->lastCode = $this->read(1);
			} else
				$end = true;
		}
		return $data;
	}

}

/**
 * <BLOCKQUOTE>
 * Hessian protocol writer, inspired and partially based on hessianlib.py by Caucho.
 * <BR>
 * TODO:
 * <UL>
 *	<LI>Handling of _hessian_write function</LI>
 * </UL>
 * </BLOCKQUOTE>
 * @package HessianPHP.Protocol
 * @author Manolo Gómez
 * @copyright Copyright (c) 2004
 * @version 1.0
 * @access public
 **/
class HessianWriter extends HessianProtocolHandler{
	//var $stream;
	var $fault = false;
	//var $refs = array();

	/**
	 * Sets the reply as a fault, following Hessian spec
	 *  
	 * @param string code Code number of the fault
	 * @param string message Descriptive message of the fault
	 * @param mixed detail Optional argument with detail of the fault, usually a stack trace
	 * @access public
	 **/
	function setFault($code,$message,$detail=null){
		$this->fault = array('code' => $code, 'message' => $message, 'detail' => $detail);
	}

	/**
	 * Serializes a PHP value into a Hessian stream using reflection. Depending on the type
	 * it calls one of the writing functions of this class.
	 *  
	 * @param mixed value Value to be serialized
	 **/
	function writeObject(&$value){
		$type = gettype($value);
		switch($type){
			case 'integer': $dispatch = 'writeInt' ;break;
			case 'boolean': $dispatch = 'writeBool' ;break;
			case 'string': $dispatch = 'writeString' ; break;
			case 'double': $dispatch = 'writeDouble' ; break;
			case 'array': 
				if($this->isArrayAssoc($value)) {
					$dispatch = 'writeMap';
				} else {
					$dispatch = 'writeList';
				}	
				break;
			case 'object': $dispatch = 'writeMap' ;break;
			case 'NULL': $this->stream .= 'N' ;return;
			case 'resource': $dispatch = 'writeResource' ; break;
			default: die("$type not implemented");
		}
		$this->$dispatch($value);
	}

	/**
	 * Writes a Hessian reply with a return object. If a fault has been set, it writes the fault instead
	 *  
	 * @param mixed object Object to be returned in the reply
	 * @return string Hessian reply
	 **/
	function writeReply($object){
		$stream = &$this->stream;
		$stream = "r\x01\x00";
		if(!$this->fault) {
			$this->writeObject($object);
		} else {
			$this->writeFault($this->fault['code'],
				$this->fault['message'],
				$this->fault['detail']);

		}
		$stream .= "z";
		return $stream;
	}

	/**
	 * Writes a Hessian method call and serializes arguments.
	 *  
	 * @param string method Method to be called
	 * @param array params Arguments of the method
	 * @return string Hessian call
	 **/
	function writeCall($method,&$params){
		$stream = &$this->stream;
		$stream = "c\x01\x00m";
		$this->writeStringData($method);
		foreach($params as $param){
			$this->writeObject($param);
		}
		$stream .= "z";
		return $stream;
	}

	// Series of Hessian object serializing functions

	function writeBool($value){
		if($value) $this->stream .= 'T';
		else $this->stream .= 'F';
	}

	function writeString($value){
		$this->stream .= 'S';
		$this->writeStringData($value);
	}

	function writeHeader($value){
		$this->stream .= 'H';
		$this->writeStringData($value);
	}

	function writeBytes($value){
		$this->stream .= 'B';
		// OJO tal vez no haga falta escribir como string
		$this->writeStringData($value);
	}

	function writeFault($code,$message,$detail){
		$this->stream .= 'f';
		$this->writeString('code');
		$this->writeString($code);
		$this->writeString('message');
		$this->writeString($message);
		// OJO puede ser false o null o lo que sea, por lo pronto no es null
		if(!is_null($detail)){
			$this->writeString('detail');
			$this->writeObject($detail);
		}
		$this->stream .= 'z';
	}

	function writeInt($value){
		$this->stream .= 'I';
		//$this->stream .= pack('N',$value);
		$this->stream .= ByteUtils::getIntBytes($value,32);
	}

	function writeLong($value){
		$this->stream .= 'L';
		$less = $value>>32;
		$res = $value / pow(2,32);
		$this->stream .= pack('N2',$res,$less);
		//$this->stream .= ByteUtils::getIntBytes($value,64);
	}

	function writeDate($value){
		$this->stream .= 'd';
		$less = $value >> 32;
		$res = $value / pow(2,32); // 256/256/256/256; 
		/*
		printf("%X<br>",$less);
		printf("%X<br>",$res);
		$st = pack('N',$res);
		$st .= pack('N',$less);
		$this->stream .= $st;*/
		$this->stream .= pack('N2',$res,$less);
		
	}

	// OJO que no se sabe si la representacion interna de PHP sea 64 bit IEEE 754
	function writeDouble($value){
		$this->stream .= 'D';
		$this->stream .= ByteUtils::getFloatBytes($value);
	}

	function writeStringData($value){
		$this->stream .= pack('n',strlen($value));
		$this->stream .= utf8_encode($value);
	}


	/**
	 * Checks internal reference map to see if an object has already been written to output stream.
	 * If it has, it only writes a reference to it and returns true, otherwise returns false
	 *  
	 * WARNING: in PHP4, don't use circular references or this function will crash!
	 *
	 * @param mixed value object
	 * @return boolean is reference writen?
	 **/
	function writeReference(&$value){
		// really ugly way to find if an object reference exists, should be better in PHP 5
		$i=0;
		$total = count($this->refs);
		while($i<$total){
			if($value === $this->refs[$i]){
				$this->stream .= 'R';
				$this->stream .= ByteUtils::getIntBytes($i,32);
				return true;
			} 
			$i++;
		}
		// if not found insert in reference array;
		$this->refs[] = $value;
		return false;
	}

	function writeList(&$value){
		if($this->writeReference($value)) 
			return;
		$this->stream .= 'V';
		// type, maybe we don't need type info since this is PHP
		$this->stream .= 't';
		$this->writeStringData('');
		// end type info
		if(!empty($value)){
			$this->stream .= 'l';
			$this->stream .= ByteUtils::getIntBytes(count($value),32);
			foreach($value as $val){
				$this->writeObject($val);
			}
		}
		$this->stream .= 'z';
	}

	function writeMap(&$value){

		// Datetime Object resolution
		/*$dateProvider = &Hessian::getDateProvider();
		if($dateProvider->isDateObject($value)){
			$ts = $dateProvider->writeDate($value);
			return $this->writeDate($ts);
		}*/

		if($this->dateProvider->isDateObject($value)){
			$ts = $this->dateProvider->writeDate($value);
			return $this->writeDate($ts);
		}

		if($this->writeReference($value)) 
			return;
		$this->stream .= "M";
		// type handling for local classes
		$this->stream .= 't';
		if(is_object($value)) {
			$localType = get_class($value);
			//$type = TypeMap::getRemoteType($localType);
			$type = $this->typeMap->getRemoteType($localType);
			if(!$type) $type = $localType;
			$this->writeStringData($type);
		}
		else
			$this->writeStringData('');
		if(!empty($value)){
			if(is_array($value)) {
				// arrays
				foreach($value as $key => $val){
					$this->writeObject($key);
					$this->writeObject($val);
				}
			}
			if(is_object($value)) {
				// classes
				$vars = get_object_vars($value);
				foreach($vars as $varName => $varValue){
					$this->writeObject($varName);
					$this->writeObject($value->$varName);
				}
			}
		}
		$this->stream .= 'z';
	}

	/**
	 * Very simple way to check if an array is associative. PHP doesn't have a way to tell
	 * an associative array from one that only has numbers as keys.
	 * Never mind the foreach, it's *faster* than other ways.
	 * Stops when a key is of string type or the key is negative, yes, you are read it well,
	 * array keys can be negative (and also null, and false, and...)
	 *  
	 * @param array array Array to check
	 * @return boolean is associative?
	 **/
	function isArrayAssoc(&$array){
		if(empty($array))
			return false;
		foreach($array as $key => $val) {
			if (is_string($key) || $key<0) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * EXPERIMENTAL: Algorithm to check if a php associative array is *exactly* an
	 * ordered list. It uses a property of ordered lists numeric keys, they correspond
	 * to the 0,1,2...n series of continuous integers, therefore you can check if
	 * an array is an ordered list by calculating the sum of its keys by hand and then
	 * using a formula. If both values match, it is an ordered list.
	 *   
	 * Just Slightly slower than isArrayAssoc but safer. (currently not being used)
	 *
	 * @param array array Array to check
	 * @return boolean is an ordered list?
	 **/

	function isList(&$array){
		if(empty($array))
			return false;
		$phpSum = 0;
		foreach($array as $key => $val){ // foreach is faster
		//while(list($key) = each($array)){
			if (!is_int($key) || $key<0) return false;
			$phpSum += $key;
		}
		$n = count($array);
		// formula para calcular la sumatoria de una serie
		$sum = (0*$n) + ( ($n*($n-1)*1)/2 );
		if($sum == $phpSum)
			return true;
		return false;
	}

	function writeResource($handle){
		$type = get_resource_type($handle);
		if($type == 'file' || $type == 'stream'){
			while (!feof($handle)) {
				$content = fread($handle, 32768);
				$tag = 'b';
				if(feof($handle))
					$tag = 'B';
				//echo strlen($content).'<br>';
				$this->stream .= $tag . pack('n',strlen($content));
				$this->stream .= $content;
			}
			fclose($handle);
		} else {
			return new HessianError("Cannot handle resource of type '$type'",HESSIAN_WRITER_ERROR);	
		}
	}

}


?>