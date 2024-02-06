<?php
/**
 * file.php -SDBEE_storage implementation for direct access to files
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

/**
 * Example
 * "public-storage" : {
 *       "storageService" : "file", 
 *       "top-dir" : "https://www.sd-bee.com/upload/sd-bee-cdn", 
 *       "prefix" : ""
 *   },
 */

$JUST_CREATED_CLASS = "FileStorage";


class FileStorage extends SDBEE_storage {

    function __construct( $userData) {
        //$this->topDir = $userData[ 'top-dir'];
        $this->topDir = ( strpos( $userData[ 'top-dir'], 'http') === 0) ? $userData[ 'top-dir'] : __DIR__."/../../".$userData[ 'top-dir'];
        $this->prefix = val( $userData, 'prefix');
    }

    function exists( $dir, $filename) {
        if ( strpos( $this->topDir, 'http') === 0) return true;
        return file_exists( $this->_getURL( $dir, $filename));
    }

    function read( $dir, $filename) {
        $contents = "";
        if ( $this->exists( $dir, $filename)) {
            try {
                $contents = @file_get_contents( $this->_getURL( $dir, $filename));
            }
            catch( \Exception $e) { $contents = "";}
        }
        return $contents;
    }

    function write( $dir, $filename, $data) { 
        if ( strpos( $this->topDr, 'http') === 0) return "ERR:can't write to remote files";
        //echo $this->_getURL( $dir, $filename);
        $r = file_put_contents( $this->_getURL( $dir, $filename), $data);
       // echo "wrote $r to $filename ".$this->_getURL( $dir, $filename);
        return $r;
    }

    function _prefix( $dir, &$filename) {
        if ( !$this->isPublic( $dir, $filename) && $this->prefix) $filename = $this->prefix.'_'.$filename;
    }

    function isPublic( $dir, $filename) {
        return ( $dir == "models");
    }
    function _getURL( $dir, $filename) {
        $this->_prefix( $dir, $filename);
	    if ( strpos( $this->topDir, 'http') === 0) {
            // Reading from Internet
            // 2DO check dir can contain / (ie multiple levels)
            //      if not process multiple levels in dir with explode, encode each step and implode
            $full = $this->topDir.rawurlencode( $dir).'/'.rawurlencode($filename);
        } else {
            // Reading from local file system
            if ( $dir && substr( $dir, -1) != '/' ) $dir .= '/';
            $full = "{$this->topDir}{$dir}{$filename}";
        }
        return $full;
    }

    function getList( $dir) {
        if ( $dir && substr( $dir, -1) != '/' ) $dir .= '/';
        $full = "{$this->topDir}{$dir}__list.json";
        $listJSON = file_get_contents( $full);
        $list = JSON_decode( $listJSON, true);
        return $list;
    }
}