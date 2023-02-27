<?php
/**
 * sdbee-collection.php - Endpoint to retrieve an HTML block displaying documents in a collection
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
error_reporting( E_ERROR);

function SDBEE_endpoint_collection( $collectionName, $action) {
    global $ACCESS, $DM;
    if ( !$ACCESS) return SDBEE_endpoint_collection_test();
    if ( !$collectionName || $collectionName == 'USER') {
        // Users top directory
        $DOM->out( 'USER TOP<br>');
        $DM->out( UDUTILITY_breadcrumbs( $pathWithLinks));
        $data = $ACCESS->getCollectionContents( $collectionName);
        $DM->load( $data);
        $DM->out( UDUTILITY_listContainersAsThumbnails( $DM, [ 'maxNb'=>0, 'offset'=>0, 'wrEnable' => 1]));
        $DM->flush( 'ajax');
    } else {
        // Display a collection
        $info = $ACCESS->getCollectionInfo( $collectionName);        
        if ( !$info[ 'access'] && RD) {
            // Error page
        } elseif ( $action == "list") {
            // Display collection's contents as HTML zone
            $path = explode( '/', $info[ 'path']);
            $pathWithLinks = [ 'Top' => "$$$.reload( false);"];
            for ($pathi=0; $pathi < LF_count( $path); $pathi++) { 
                $nodeName = $path[ $pathi];    
                $nodeInfo =  $ACCESS->getCollectionInfo( $collectionName);
                $nodeLabel = $pathInfo[ 'label'];            
                $pathWithLinks[ $nodeLabel] = $nodeName;                
            }
            $DM->out( UDUTILITY_breadcrumbs( $pathWithLinks));
            $data = $ACCESS->getCollectionContents( $collectionName);
            $DM->load( $data);
            $DM->out( UDUTILITY_listContainersAsThumbnails( $DM, [ 'maxNb'=>0, 'offset'=>0, 'wrEnable' => 1]));
            $DM->flush( 'ajax');
        } else {
            // Display collection listing model NOT OK
            $model = $info[ 'model'];
            if ( !$model) $model = "Basic";
            //$doc = new SDBEE_doc(  'Models'', $name)
            SDBEE_showTask( $model, $info);
        }
    }
}
global $request;
SDBEE_endpoint_collection( $request[ 'collection'],$request[ 'act']);

function SDBEE_endpoint_collection_test() {
    // TEST Data
    $data = [ 
        [ 'nname', 'tlabel', 'stype', 'nstyle', 'tcontent', 'tparams'],
        [ 'nname'=>'A0000002NHSEB0000M_Repageaf', 'tlabel'=>'Trial doc', 'stype'=>2, 'nstyle'=>'A000012121444444000M_model', 
        'tcontent'=>'Trial document', 'tparams'=>'{"system":{"state":"initialised", "progress":"10"}}', '_link'=>"?task=A0000002NHSEB0000M_Repageaf"]
    ];
    $DM->load( $data);
    $DM->out( UDUTILITY_breadcrumbs( [ 
        'Top'=>"$$$.updateZone( 'USER/AJAX_listContainers/', 'BE00000000000000M_dirListing');", 
        'Tasks'=>"$$$.updateZone('A0000121212120000M_trialdoc/AJAX_listContainers/', 'BE00000000000000M_dirListing');"
    ]));
    $DM->out( UDUTILITY_listContainersAsThumbnails( $DM, [ 'maxNb'=>0, 'offset'=>0, 'wrEnable' => 1]));
    $DM->flush( 'ajax');
    exit();      
}