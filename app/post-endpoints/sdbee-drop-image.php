<?php
/**
 * sdbee-drop-image.php - Endpoint on SD bee server to receive dropped images and save temn in user's 
 * storage
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

 require_once( __DIR__."/../local-services/udservices.php");

 function SDBEE_endpoint_drop_image( $request) {
    $domain = $request[ 'domainAndPath'];
    $fieldName = 'gimage'; // 'ffile'
    $tempFilename = $_FILES[ $fieldName][ 'tmp_name'];
    $name = $_FILES[$fieldName]["name"];
    $request = [
        'service' => 'images',
        'provider' => 'FTPimages',
        'action' => 'save',
        'source' => $domain,
        'source-file' => $tempFilename, 
        'target' => $name
    ];
    $params = [ 'service-root-dir' => __DIR__.'/../../.config/added-local-services', 'throttle' => 'off'];
    $services = new UD_services( $params);
    $r = $services->do( $request);
    if ($r) echo "OK {$name} transferred";
    else echo "ERR not saved";
    /*
        $c = file_get_content( $tempFilename)
        $storage->write( $filename, $c);
        pr
        localservices/ftplib
    */ 

 }
 global $request;
 SDBEE_endpoint_drop_image( $request);