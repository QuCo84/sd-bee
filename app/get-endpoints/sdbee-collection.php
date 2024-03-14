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

function SDBEE_endpoint_collection( $collectionName, $action, $updateOid = "on") {
    global $ACCESS, $DM;
    if ( !$ACCESS) return SDBEE_endpoint_collection_test();
    $thumbDocClass = ( val( $_REQUEST, 'dcl')) ? (int) $_REQUEST[ 'dcl'] : 0;
    $displayBreadcrumbs = ( val( $_REQUEST, 'bc')) ? (bool) $_REQUEST[ 'bc'] : true;
    $link = "$$$.updateZone('USER--21/AJAX_listContainers/', 'BE00000000000000M_dirListing');";
    $pathWithLinks = [ 'Top' => $link];
    $view = "'BE00000000000000M_dirListing'";
    if ( !$collectionName || $collectionName == 'USER') {
        // Users top directory
        $DM->out( UDUTILITY_breadcrumbs( $pathWithLinks));
        $data = $ACCESS->getUserContents();        
        $DM->load( $data);        
        $DM->out( UDUTILITY_listContainersAsThumbnails( $DM, [ 'maxNb'=>0, 'offset'=>0, 'wrEnable' => 1]));
        $DM->flush( 'ajax');
    } else {
        // Display a collection
        $info = $ACCESS->getCollectionInfo( $collectionName);        
        if ( !val( $info, 'access') && RD) {
            // Error page
        } elseif ( $action == "list") {
            // Build path with links for breadcrumbs
            if ( val( $info, 'path')) $path = explode( '/', $info[ 'path'] . '/' . $collectionName);
            else $path = [ $collectionName];            
            for ($pathi=0; $pathi < LF_count( $path); $pathi++) { 
                $nodeName = val( $path, $pathi);    
                $nodeInfo =  $ACCESS->getCollectionInfo( $collectionName);
                $nodeLabel = val( $nodeInfo, 'label');            
                $link = "$$$.updateZone('_FILE_UniversalDocElement-{$collectionName}--21-{$info[ 'id']}";
                $link .= "/AJAX_listContainers/', {$view});";
                $pathWithLinks[ $nodeLabel] = $link;                
            }
            // Display breadcrumbs
            $DM->out( UDUTILITY_breadcrumbs( $pathWithLinks));
            // Get and display directory contents
            $data = $ACCESS->getCollectionContents( $collectionName);
            $DM->load( $data);
            $forceModel = val( $info, 'force-model', '');
            $params = [ 'maxNb'=>0, 'offset'=>0, 'wrEnable' => 1, 'forceModel' => $forceModel];
            if ( $thumbDocClass) $params[ 'doc-type'] = $thumbDocClass;
            $DM->out( UDUTILITY_listContainersAsThumbnails( $DM, $params));
            // Add JS code to update resources client side            
            $js = "";
            $title = val( $info, 'label');
            $subtitle =  val( $info, 'description');
            $system = val( $info, 'params');
            $docOID = "__FILE__UniversalDocElement-".val( $info, 'name').'--21-1';
            $oidNew = $docOID."-0";
            $oidChildren = $docOID."-21";
            // 2DO make a fct in udutilitiesfct complete = docModel = Basic Dir, docFull
            if ( $updateOid != "off") {
                // 2DO Only do this if call from Dir Model, not files inside doc
                $js .= "$$$.dom.element( 'UD_docTitle').textContent = '{$title}';\n";
                $js .= "$$$.dom.element( 'UD_docSubtitle').textContent = '{$subtitle}';\n";
                $js .= "$$$.dom.element( 'UD_oidNew').textContent = '{$oidNew}';\n";
                $js .= "$$$.dom.attr( 'document', 'ud_oid','{$docOID}');\n";
                $js .= "$$$.dom.attr( 'document', 'ud_oidchildren','{$oidChildren}');\n";
                $js .= "$$$.dom.element( 'UD_system').textContent = '" . JSON_encode( $system)."';\n";
            }
            //$js .= "ud.ude.calc.redoDependencies()\n";
            $DM->onload( $js);            
            $DM->flush( 'ajax');
        } else {
            // Display collection listing model NOT OK
            $model = val( $info, 'model');
            if ( !$model) $model = "Basic";
            //$doc = new SDBEE_doc(  'Models'', $name)
            SDBEE_showTask( $model, $info);
        }
    }
}
global $request;
SDBEE_endpoint_collection( $request[ 'collection'],val( $request, 'act'));

function SDBEE_endpoint_collection_test() {
    global $DM;
    // TEST Data
    $data = [ 
        [ 'nname', 'tlabel', 'stype', 'nstyle', 'tcontent', 'tparams'],
        [ 'nname'=>'A0000002NHSEB0000M_Repageaf', 'tlabel'=>'Trial doc', 'stype'=>2, 'nstyle'=>'A000012121444444000M_model', 
        'tcontent'=>'Trial document', 'tparams'=>'{"system":{"state":"initialised", "progress":"10"}}', '_link'=>"?task=A0000002NHSEB0000M_Repageaf"],
        [ 'nname'=>'A00000032TU6K00001_GettingStarted', 'tlabel'=>'Trial doc', 'stype'=>2, 'nstyle'=>'A000012121444444000M_model', 
        'tcontent'=>'Trial document', 'tparams'=>'{"system":{"state":"initialised", "progress":"10"}}', '_link'=>"?task=A00000032TU6K00001_GettingStarted"]
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