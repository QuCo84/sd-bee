<?php
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