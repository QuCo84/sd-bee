<?php
/**
 * sdbee-storage.php -- Abstract class representing file-like storage providing full access to SDbee documents (tasks, models ...)
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

//error_reporting( E_ERROR);
/**
 * SDBEE_storage abstract class
 */
abstract class SDBEE_storage {
    
    function isPublic( $dir, $name) {
        return ( $dir == "models");
    }
    
    abstract function exists( $dir, $name);
    abstract function read( $dir, $name);
    abstract function write( $dir, $name, $data);
    abstract function getList( $dir);

}

$STORAGE_CLASSES = [];
function SDBEE_getStorage( $params) {
    $storageService = val( $params, 'storageService');
    global $JUST_CREATED_CLASS, $STORAGE_CLASSES;
    if ( val(  $STORAGE_CLASSES, $storageService)) $storageClass = val( $STORAGE_CLASSES, $storageService);
    elseif ( $storageService) {
        include_once( __DIR__."/storage-connectors/{$storageService}.php");
        $storageClass = $JUST_CREATED_CLASS;
        $STORAGE_CLASSES[ $storageService] = $storageClass;
    }
    else echo "Configuration error - no storageService<br>\n"; //var_dump( $params); debug_print_backtrace(); die();
    if ( !class_exists( $storageClass)) die( "Configuration error - no connector for storage {$storageService}<br>\n");
    $storage = new $storageClass( $params);
    return $storage;
}
    
function SDBEE_getResourceFile( $category, $filename) {
    global $USER, $CONFIG;
    $localFile = "";
    $resources = $USER[ 'resource-storage'];
    if ( $resources) {
        if ( !strpos( $service, ':')) {
            // A name is used, get info from CONFIG
            $params = val( $CONFIG, $resources);
            $service = val( $params, 'storageService'); 
        } else {
            // Syntax used with <service>:<parameter string>
            $parts = explode( ':', $privateResources);
            $service = $parts[0];
            $pathParts = explode( '/', $parts[1]);        
            if ( $service == "gs") {
                $bucket = array_shift( $pathParts);
                $home = implode( '/', $pathParts);
                $params = [ 'storageService' =>'gs', 'keyFile' => $USER[ 'keyFile'], 'source'=>$bucket, 'home'=>$home, 'prefix'=>''];
                $storage = SDBEE_getStorage( $params);
                $externalContents = $storage->read( $category, $filename);
                if ( $externalContents) {
                    $localFile = "/tmp/{$filename}";
                    file_put_contents( $localFile, $externalContents);
                }
            } elseif ( $service == "sftp") {
                // sftp:<username>:<password>@domain[/<home>]
                $parts2 = explode( '@', $parts[2]);            
                $username = $parts[1];
                $password = $parts2[0];
                $domain = $parts2[1];
                //$dir = implode( '/', $pathParts);
                $params = [ 'storageService' => 'sftp', 'domain' => $domain, 'user'=>$username, 'password'=>$password, 'home'=>'', 'prefix'=>''];
                $storage = SDBEE_getStorage( $params);
                $externalContents = $storage->read( $category, $filename);
                if ( $externalContents) {
                    $localFile = "/tmp/{$filename}";
                    file_put_contents( $localFile, $externalContents);
                }
            }
        }
        if ( $service == "gs") {
            $storage = SDBEE_getStorage( $params);
            $externalContents = $storage->read( $category, $filename);
            if ( $externalContents) {
                $localFile = "/tmp/{$filename}";
                file_put_contents( $localFile, $externalContents);
            }
        } elseif ( $service == "sftp") {
            $storage = SDBEE_getStorage( $params);
            $externalContents = $storage->read( $category, $filename);
            if ( $externalContents) {
                $localFile = "/tmp/{$filename}";
                file_put_contents( $localFile, $externalContents);
            }
        }
    }
    return $localFile;
}