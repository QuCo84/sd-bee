<?php
/**
 * sdbee-config.php - config helper
 * 
 * Requires composer require adhocore/json-comment
 */
require_once __DIR__.'/../vendor/autoload.php';
use Ahc\Json\Comment;

 function SDBEE_getConfig() {
    $configJSONc = file_get_contents( __DIR__.'/../.config/sdbee-config.jsonc');
    if ( !$configJSONc) die( "Please configure before deploying ... see readme.md in the root directory<br>\n");
    $config = (new Comment)->decode( $configJSONc, true);
    if ( !$config) die( "Please check the configuration file for syntax erreors. You can use tools/checkconfig.sh or .bat for this<br>\n");
    return $config;
 }