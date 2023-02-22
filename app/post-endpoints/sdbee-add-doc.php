<?php
/**
 * Endpoint on SD bee server to create a new task or SD bee document
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
        if ( !$dir) $dir = $USER[ 'home'];
        if ( $dir) {
            $id = $ACCESS->addDocToCollection( $name, $dir, $doc, $access=7);       
            echo "coll $id {$ACCESS->lastError}\n";
            $url ="/?task={$name}";
        } else {
            $id = $ACCESS->addToUser( $name, $USER[ 'id'], $doc, $access=7); 
            echo "usr $id {$ACCESS->lastError}\n";
            //$url = "_FILE_UniversalDocElement-{$name}--21-{$id}";
            $url ="/?task={$name}";
        }
    } else {
        $url ="?task={$name}&add=yes";
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