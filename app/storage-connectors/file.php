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
        $this->topDir = $userData[ 'top-dir'];
        $this->prefix = $userData[ 'prefix'];
    }

    function exists( $dir, $filename) {
        if ( strpos( $this->topDir, 'http') === 0) return true;
        return file_exists( $this->_getURL( $dir, $filename));
    }

    function read( $dir, $filename) {
        return file_get_contents( $this->_getURL( $dir, $filename));
    }

    function write( $dir, $filename, $data) { 
        if ( strpos( $this->topDr, 'http') === 0) return "ERR:can't write to remote files";
        return file_put_contents( $this->_getURL( $dir, $filename), $data);
    }

    function _prefix( $dir, &$filename) {
        $filename = urlencode( $filename);
        if ( !$this->isPublic( $dir, $filename)) $filename = $this->prefix.'_'.$filename;
    }

    function isPublic( $dir, $filename) {
        return ( $dir == "models");
    }
    function _getURL( $dir, $filename) {
        $this->_prefix( $dir, $filename);
        if ( $dir && substr( $dir, -1) != '/' ) $dir .= '/';
        $full = "{$this->topDir}{$dir}{$filename}";
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