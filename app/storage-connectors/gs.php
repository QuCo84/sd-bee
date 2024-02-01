<?php
/**
 * gs.php - SDBEE_storage implementation for Google Cloud Storage
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
//require 'vendor/autoload.php';
use Google\Cloud\Storage\StorageClient;

$JUST_CREATED_CLASS = "GoogleCloudStorage";

class GoogleCloudStorage extends SDBEE_storage {
    
    private $storage=null;
    private $keyFile="";
    private $bucket="";
    private $home="";
    private $prefix = "";
    private $prefixes = [];
    private $generations =[];


    function __construct( $userData) {
        $this->keyFile = $userData[ 'keyFile'];
        $this->bucket = ($userData[ 'source']) ? $userData[ 'source'] : $userData[ 'bucket'];
        $this->home = $userData[ 'top-dir'];
        $this->prefix = $userData[ 'prefix'];
        $this->storage = new StorageClient([ 'keyFilePath' => $this->keyFile]);
        $this->storage->registerStreamWrapper();
    }

    function exists( $dir, $filename) {
        if ( !$this->storage) return false;
        $bucket = $this->storage->bucket( $this->bucket);
        $this->_prefix( $dir, $filename);
        if ( $dir && substr( $dir, -1) != '/' ) $dir .= '/';
        $found = false;
        foreach ($bucket->objects([ 'prefix'=>$dir.$filename]) as $object) {
            if ( $object->name() == $dir.$filename) {
                $found = true;
                break;
            }
        }            
        return $found;              
    }

    function _getURL( $dir, $filename) {
        $this->_prefix( $dir, $filename);
        if ( $dir && substr( $dir, -1) != '/' ) $dir .= '/';
        $full = "gs://{$this->bucket}/".rawurlencode( $dir.$filename);
        return $full;
    }

    function _prefix( $dir, &$filename) {
        if ( !$this->isPublic( $dir, $filename)) $filename = $this->prefix.'_'.$filename;
    }

    function isPublic( $dir, $filename) {
        return ( $dir == "models");
    }

    function read( $dir, $filename) {
        global $TEST;
        if ( !$this->storage) return false;
        // Get contents if file exists
        $contents = "";
        if ( $this->exists( $dir, $filename)) {
            $full = $this->_getURL( $dir, $filename);
            $contents = file_get_contents( $full);
        }
        if ( !$contents) {
            if ( $TEST) echo "No contents with $full<br>\n";
            return "";
        }
        // Save generation no
        $this->generations[ $filename] = $this->_getGeneration( $dir, $filename);
        // Return contents
        return $contents;
    }

    function _getGeneration( $dir, $filename) {
        global $TEST;
        if ( !$this->storage) return false;
        $bucket = $this->storage->bucket( $this->bucket);
        $this->_prefix( $dir, $filename);
        $full = ( $dir) ?  "{$dir}/{$filename}" : $filename;
        $object = $bucket->object( $full);
        if ( $TEST && !$object) { echo "$full not found in {$this->bucket}<br>\n"; return "NOT FOUND";}
        $info = $object->info();
        return $info[ 'generation'];
    }

    function write( $dir, $filename, $data) {
        global $TEST;
        if ( !$this->storage) return false;
        // Check generation hasn't changed
        $gen = ( isset( $this->generations[ $filename])) ? $this->generations[ $filename] : "";
        if ( $gen) {
            // Object exists in storage
            if ( $this->generations[ $filename] != $this->_getGeneration ( $dir, $filename)) {
                // File has been updated since being read
                var_dump( $this->generations, $this->_getGeneration ( $dir, $filename));
                echo "File has changed $dir $filename<br>\n";
                return "ERR:file has been changed";

            }
            // Update file
            $full = $this->_getURL( $dir, $filename);
            $contents = file_put_contents( $full, $data);
            if ( $TEST) echo "$contents bytes written to $full<br>\n";
        } else {
            // Create object
            try {
                $this->_prefix( $dir, $filename);
                if ( $dir && substr( $dir, -1) != '/' ) $dir .= '/';
                $bucket = $this->storage->bucket( $this->bucket);
                $newObj = $bucket->object( $dir.$filename);
                $type = "text/plain";
                if ( strpos( $filename, '.json')) $type = "application/json";
                elseif ( strpos( $filename, '.json')) $type = "application/octet-stream";
                $url = $newObj->beginSignedUploadSession( [ 'contentType'=>$type]);
                $opts = array('http' =>
                    array(
                        'method'  => 'PUT',
                        'header'  => 'Content-type:'.$type,
                        'content' => $data
                    )
                );
                $context = stream_context_create($opts);
                $result = file_get_contents( $url, false, $context);
                if ( $TEST) echo "$contents bytes written to $full<br>\n";
            } catch (Exception $ex) {
                echo $ex->getMessage() . "\n<pre>";
                print_r($ex->getTraceAsString());
                echo '</pre>';
                die();
            }
        }
        return $contents;
    }

    function delete( $dir, $filename) {    
        if ( !$this->storage) return false;    
        try {
            $bucket = $this->storage->bucket( $this->bucket);
            $this->_prefix( $dir, $filename);
            $object = $bucket->object( $filename);
            $object->delete();
            return true;
        } catch ( \EXCEPTION $e) {
            echo $e->getMessage();
            return false;
        }
    }

    function search( ) {

    }

    function getFullName( $dir, $filename) {
        if ( $dir != "models") $filename = $this->prefix.'_'.$filename;
        if ( $dir && substr( $dir, -1) != '/' ) $dir .= '/';
        $full = "gs://{$this->bucket}/".rawurlencode( $dir.$filename);
        return $full;
    }

    function getList( $dir) {
        $list = [];
        $bucket = $this->storage->bucket( $this->bucket);
        if ( $dir && substr( $dir, -1) != '/' ) $dir .= '/';
       // echo $dir." "; var_dump( $bucket->objects([ 'prefix'=>$dir]));die();
        foreach ($bucket->objects([ 'prefix'=>$dir]) as $object) {
            $model = str_replace( [$dir], [ ''], $object->name());
            if ( $model) $list[] = $model;
        }
        return $list;
    }
}