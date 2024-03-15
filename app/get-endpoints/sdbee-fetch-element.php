<?php
/**
 * sdbee-fetch-element.php - Endpoint to fetch the latest version of an element 
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

global $request, $ACCESS;
/*
$task = val( $request, 'task');
$collection = val( $request, 'collection');
$info = $ACCESS->getDocInfo( $task);
if ( !$info) $task = "";
elseif( val( $info, 'type') == 1) {
    $collection =$task;
    $task = "";
}
if ( $task) {
elseif ( $collection)     {
    echo UDUTILITY_getContainerThumbnail( $element); !!!2DO
}
*/
$doc = new SDBEE_doc( val( $request, 'task'));
echo $doc->readElement( val( $request, 'element'));