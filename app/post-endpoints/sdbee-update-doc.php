<?php
/**
 * Endpoint on SD bee server to create or update a new task or SD bee document
 */
function SDBEE_endpoint_updateDoc( $request) {

    // Get doc
    $task = $request[ 'task'];
    if ( !$task) {
        // Create a new task from dummy model
    } else {
        // Read & update doc's info
        // Model set and not initialised
        // Copy model
    }


}