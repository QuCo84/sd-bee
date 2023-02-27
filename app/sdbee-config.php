<?php
/**
 * sdbee-config.php - config helper
 * Copyright (C) 2023  Quentin CORNWELL
 *  
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
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