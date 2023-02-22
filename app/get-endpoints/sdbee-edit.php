<?php
/**
 * Endpoint on SD bee server to open an SD bee document for edition
 */
function SDBEE_endpoint_edit( $task, $mode="") {
        // Display a task
        $taskName = $request[ 'task'];
        $doc = new SDBEE_doc( $taskName);
        if ( !$doc->model) {
                // No model so display market place
                $model = 'A000000003B2D90000M_ModelMarke';
                $doc2 = new SDBEE_doc( $model, 'models');
                $doc2->sendToClient();
        } else {        
                if ( $doc->state == "new") $doc->initialiseFromModel();
                $doc->sendToClient();
        }
}
SDBEE_endpoint_edit( $request[ 'task']);