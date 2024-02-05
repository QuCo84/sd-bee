<?php


/**
 * Marketplace Request 
 */
function SDBEE_marketplace( $request) {
    if ( !SDBEE_isMarketplace( val( $request, 'processus')))
        return [ 'success'=>false, 'response'=>"{$request[ 'processus']} not in marketplace"];
    // Get marketplace url & user
    $url = UD_getParameter( 'marketpace-url');  
    $token = ( val( $request, 'token')) ? $request[ 'token'] : UD_getParameter( 'markeplace-user-private-key');
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

function SDBEE_MP_isMarketplace( $process) {
    return ( $process[0] == 'M');
}

function SDBEE_MP_checkServiceAvailable( $service) {

}