<?php
/**
 * sdbee-modify-element.php
 */

 function SDBEE_modifyElement( $request) {
    global $USER, $DATA, $STORAGE;
    $oid = $request[ 'input_oid'];
    $w = explode( '-', explode( '--', $oid)[0]);
    $taskName = $w[ 1];
    $elementId = $w[ count( $w) - 1];
    $depth = (int) count( $w)/2 - 1;
    $doc = new SDBEE_doc( $taskName);
    if ( strpos( $oid, '--SP')) {
        // Delete
        $rep = $doc->deleteElement( $elementId);
    } else  if ( !$element) {
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