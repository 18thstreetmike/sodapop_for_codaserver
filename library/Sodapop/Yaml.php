<?php
require_once('spyc.php');

class Sodapop_Yaml {
    public static function loadFile ($fileName) {
        return Spyc::YAMLLoad($fileName);
    }

    public static function yamlToArray ($string) {
        return Spyc::YAMLLoadString($fileName);
    }
    
    public static function arrayToYaml($array) {
        return Spyc::YAMLDump($array,4,0);
    }
}