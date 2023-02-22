<?php
/**
 * Endpoint to get list of changes in a SD bee document
 */
//error_reporting( E_ERROR);
function SDBEE_endpoint_changes( $task) {
    global $USER, $ACCESS, $STORAGE;
    $lastTime = (int) $_REQUEST['lastTime'] - 1;
    $doc = new SDBEE_doc( $task);
    $parents = [];
    $changed = ['USER' => [ 'content'=>$USER[ 'id']]];
    while ( !$doc->eof())  {
        $element = $doc->next();
        if ( $element[ 'modified'] > $lastTime) {
            $name = $element[ 'name'];
            $changed[ $element[ 'name']] = [ 
                'oid' => $element[ 'oid'],
                'ticks' =>$_REQUEST[ 'ticks'],
                'before' => $doc->nameAtOffset(-1),
                'after' => $doc->nameAtOffset(0),
                'debug' => ""
            ];
            // Add parent and keep track of parents
            $level = $element[ 'level'];
            if ( $level > 2) $changedElements[ $name]['parent'] =  $parents[ $level-1];
            else $parents[ $level] = $name;
        }
    }
    echo JSON_encode( $changed);
}

global $request;
SDBEE_endpoint_changes( $request[ 'collection'], $request[ 'act']);