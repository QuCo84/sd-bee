<?php
/**
 * sdbee-modify-element.php - Endpoint to create, update or delete an element inan SD bee doc
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

 function SDBEE_modifyElement( $request) {
    global $USER, $DATA, $STORAGE;
    $oid = val( $request, 'input_oid');
    $w = explode( '-', explode( '--', $oid)[0]);
    $taskName = val( $w, 1);
    $elementId = $w[ count( $w) - 1];
    $depth = (int) count( $w)/2 - 1;
    $doc = new SDBEE_doc( $taskName);
    if ( val( $request, 'iaccess') == "0" && $depth == 0) {
        // Delete or recycle document
        include( 'sdbee-delete-doc.php');
        exit();
    } elseif ( strpos( $oid, '--SP')) {
        // Delete an element
        $rep = $doc->deleteElement( $elementId);
    } elseif ( !$doc->existsElement( $elementId)) {
        // Creation
        //var_dump( $data);
        $rep = $doc->createElement( $elementId, $request, $depth);
    } else {
        // Update
        $rep = $doc->updateElement( $elementId, $request);
    }
    echo $rep;
 }
 global $request;
 SDBEE_modifyElement( $request);