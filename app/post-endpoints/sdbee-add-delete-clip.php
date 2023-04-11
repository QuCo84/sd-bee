<?php
/**
 * sdbee-add-doc.php - Endpoint on SD bee server to create a new task or SD bee document
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

include_once __DIR__."/../editor-view-model/helpers/html.php";

function SDBEE_endpoint_addOrDeleteClip( $request) {
    global $ACCESS, $DM;
    $name = $request[ 'nname'];
    $content = urldecode( $request['ttext']);
    if ( $content) {
        // Add clip
        $type = "text";
        if ( HTML_getContentsByTag( $content, "img") == 1 ) {
            $content = '<img class="CLIPBOARD" src="'.HTML_getFirstImage( $content).'" width="100%" height="auto" />';
            $content .= '<div class="cb_tags"></div>';
            $type = "image";
        } 
        return $ACCESS->addClip( $name, $type, $content);
    } else {
        // Empty content = delete clip
        $oid = $request[ 'input_oid'];
        $ids = explode('-', explode( '--', $oid));
        $id = $ids[ count( $ids) - 1];        
        return $ACCESS->deleteClip( 'id');
    }
}

SDBEE_endpoint_addOrDeleteClip( $request);