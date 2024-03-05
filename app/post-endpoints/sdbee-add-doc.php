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

function SDBEE_endpoint_addDoc( $request) {
    global $ACCESS, $USER;
    $name = UD_utilities::getContainerName();
    $dir = ""; // TODO get this from oid
    $type = 2;
    if ( $ACCESS) {
        // Create new entry in access database
        // With empty model, marketplace will automatically be inserted when doc is opened
        $doc = [ 'label'=>"Nouvelle tache", 'type'=>$type, 'model'=>"", 'description'=>"", 'params'=>"", 'prefix'=> "", 'state'=>"", 'progress'=>0];
        if ( !$dir) $dir = val( $USER, 'home');
        $url = UD_getParameter( 'url');
        if ( $dir) {
            $id = $ACCESS->addDocToCollection( $name, $dir, $doc, $access=7);       
            // echo "coll $id {$ACCESS->lastError}\n";
            $url .= "?task={$name}";
        } else {
            $id = $ACCESS->addToUser( $name, $USER[ 'id'], $doc, $access=7); 
           //  echo "usr $id {$ACCESS->lastError}\n";
            //$url = "_FILE_UniversalDocElement-{$name}--21-{$id}";
            $url .= "?task={$name}";
        }
    } else {
        $url .= "?task={$name}&add=yes";
    }
    if ( true || $request[ 'e'] == "createAndOpen") {
        global $DM;
        $mode = "";
        if ( $mode == 'samepage') {
            $DM->onload( "document.location='{$url}';");
        } else {
            $DM->onload( "window.open('{$url}', 'fileedit{$name}');\n");
            $DM->onload( "leftColumn.closeAll( true);\n");
            // Redo dir update$LF->onload( "window.ud.fetchElement( window.ud.topElement, 'AJAX_modelShow');\n");
        }
        $DM->out( "Created New doc as $name");
        $DM->flush( 'ajax');
    } else echo 'Add doc form'; // '{ "result"=>"OK"}';
}
SDBEE_endpoint_addDoc( $request);