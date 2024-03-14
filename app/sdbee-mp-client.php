<?php


/**
 * Marketplace Request 
 */
function SDBEE_marketplace( $request) {
    // Get marketplace url & user
    $url = UD_getParameter( 'marketpace-url');  
    $token = UD_getParameter( 'markeplace-user-private-key');
    $json = JSON_encode( $request);
    $opts = array('http' =>
        array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/json',
            'header'  => 'SDBEE_SIGNATURE: ' . $token,
            'content' => "nServiceRequest={$json}"
        )
    );
    $context = stream_context_create($opts);
    $response = @file_get_contents( $url, false, $context);
    $r = JSON_decode( $response, true);
    return $r;
    /*
    if ( val( $r, 'success')) {
        return true;
    }
    return false;
    */
}

function SDBEE_MP_isMarketPlace( $process) {
    return true;
}

function SDBEE_MP_checkServiceAvailable( $service) {

}