<?php
/**
 * sdbee-fetch-element.php - Fetch an element endpoint
 */

global $request;
$doc = new SDBEE_doc( $request[ 'task']);
echo $doc->readElement( $request[ 'element']);