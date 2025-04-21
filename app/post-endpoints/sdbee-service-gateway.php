<?php
/**
 * sdbee-service-gateway.php - Endpoint on SD bee server for access to all services wether provided locally or remotely
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

require_once( __DIR__."/../local-services/udservices.php");
require_once( __DIR__."/../local-services/udservicethrottle.php");
require_once( __DIR__."/../sdbee-mp-client.php");

if ( LF_env( "UD_extended_local_services")) {
    define ( 'SDBEE_extended_local_services', __DIR__ . '/../..' . LF_env( "UD_extended_local_services"));
    define ( 'SDBEE_external_services', __DIR__ . '/../..' . LF_env( "UD_external-services"));
} else {
    define ( 'SDBEE_extended_local_services', __DIR__.'/../../.config/added-local-services');
    define ( 'SDBEE_external_services', __DIR__.'/../../.config/external-services');
}


use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

 function SDBEE_service_endpoint( $request) { // 2DO $serviceRequest
    global $USER_CONFIG;
    // Error if no user
    if ( !$USER_CONFIG || LF_env( 'is_Anonymous')) {
        // Prepare error response
        $jsonResponse = [
            'success'=>False, 
            'message'=> "Unidentified",
            'data' => "" 
        ];
        return $jsonResponse;
    }
    // Build service map
    $map = SDBEE_service_endpoint_getServiceMap();
    // Get request and inof on service gateway to use
    $reqRaw = val( $request, 'nServiceRequest');  // 2DO comment 2 lines
    $serviceRequest = JSON_decode( urldecode( $reqRaw), true);    
    $serviceName = val( $serviceRequest, 'service');
    if ( !val( $map, $serviceName)) 
       return SDBEE_service_endpoint_error(  "Bad configuration for $serviceName" . print_r( $map, true));
    $serviceInfo = explode( ' ', val( $map, $serviceName));
    $protocol = val( $serviceInfo, 0);
    $gateway = val( $serviceInfo, 1);
    $functionPath = val( $serviceInfo, 2);
    $serviceAccount = val( $serviceInfo, 3);    
    // Check access rights
    if ( $gateway != 'local' && $gateway != 'local+') {
        // Not a free service so check access
        $process = val( $serviceRequest, 'process'); 
        if ( SDBEE_MP_isMarketplace( $process)) {
            // Check if grants available via Markeplace
            $mpRequest = $serviceRequest;
            $mpRequest[ 'action'] = 'check-grant';
            $mpResponse = SDBEE_marketplace( $mpRequest);
            if ( val( $mpResponse, 'success') && val( $mpResponse, 'response')) {
                $mpData = val( $mpResponse, 'data');
                $protocol ='soil';
                $gateway = val( $mpData, 'baseURL');
                $functionPath = val( $mpData, 'fctPath');
                $serviceAccount = val( $mpData, 'account');
                if ( $serviceAccount == 'USER') {
                    global $CONFIG;
                    $serviceAccount = $CONFIG[ 'marketplace-account-token'];
                }
            }
        } else {
            // Check with local service throttle
            if ( !val(  $map, $serviceName)) return [ "result"=>"KO", "msg"=>"No service $serviceName"];    
            // Throttle control
            $throttle = new UD_serviceThrottle();
            $throttleId = $throttle->isAvailable( $serviceName);
            /* Dev to activate
            $status = $this->throttle->status( $serviceName, $taskId, $progress);
            if ( !$status) return [ "success"=>false, "message"=>$this->throttle->lastResponse, "data"=>$this->throttle->lastError];
            delete block below
            */
            if ( !$throttleId) {
                $result = "{$this->throttle->lastError}: {$this->throttle->lastResponse}";
                $jsonResponse = [ 
                    'success'=>false, 
                    'message'=> "No credits for $serviceName $action",
                    'data'=>$result
                ];
                $response = "<span class=\"error\">No credits for $serviceName $action <a>more</a></span><span>$result</span>"; 
                return $jsonResponse;
            }
            // Use the service map
            $serviceInfo = explode( ' ', val( $map, $serviceName));
            $protocol = val( $serviceInfo, 0);
            $gateway = val( $serviceInfo, 1);
            $functionPath = val( $serviceInfo, 2);
            $serviceAccount = val( $serviceInfo, 3);
        }
    }
    // Handle service request
    if ( $gateway == 'local' || $gateway == 'local+') {
        // Handle local services
        // Get parameters
        $params = null;        
        if ( $gateway == 'local+') {
            $params = [ 'service-root-dir' => SDBEE_extended_local_services, 'throttle' => 'off'];
        }
        // Call service via local gateway and load helpers
        include_once __DIR__.'/../editor-view-model/helpers/udutilities.php';
        include_once __DIR__.'/../local-services/udservices.php';
        $services = new UD_services( $params);
	    $response = $services->do( $serviceRequest);
        return $response;
    } else {
        // Transmit request to a gateway for external services
        // Get parameters from map entry protocol gateway/ functionName accountOrToken
        $serviceInfo = explode( ' ', val( $map, $serviceName));
        $protocol = val( $serviceInfo, 0);
        $gateway = val( $serviceInfo, 1);
        $functionPath = val( $serviceInfo, 2);
        $serviceAccount = val( $serviceInfo, 3);
        if ( $gateway == "user") $gateway = $USER[ 'service-gateway'];
        // 2DO JSON_decode as map service:url, get service from $serviceRequest
        if ( !$gateway) {
            $response = [ "success"=>false, "message"=>"No gateway", "data" => ""];
        } else {          
            // Get parameters and add to request
            $services = new UD_services( [ 'throttle'=>'off']);
            $services->_getParamsFromUserConfig( $serviceRequest);
            // Process according to protocol
            if ( $protocol == 'gcf') $response = SDBEE_service_endpoint_gcf( $gateway, $functionPath, $serviceAccount, $serviceRequest);
            elseif ( $protocol == 'soil') $response = SDBEE_service_endpoint_token( $gateway, $functionPath, $serviceAccount, $serviceRequest);
            elseif ( $protocol == 'bearer') $response = SDBEE_service_endpoint_bearer( $gateway, $functionPath, $serviceAccount, $serviceRequest);
            else $response =  [ "success"=>false, "message"=>"Unknown protocol", "data" => ""];        
            if ( val( $response, 'success') && $throttleId &&  val( $response, 'creditsConsumed')) {
                // Update service log
                $throttle->consume( $throttleId, $response[ 'creditsConsumed'], val( $response, 'creditsComment'));
            }            
            return $response;
        }
    }
}

function SDBEE_service_endpoint_account( $gateway, $functionPath, $account, $request) {
    $url = $gateway.'/'.$functionPath.'/';
    $json = JSON_encode( $request);
    $user = $USER[ 'service-user'];
    $pass = $USER[ 'service-password'];
    $opts = array('http' =>
        array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/json',
            'content' => "tusername={$user}&tpassword={$pass}&nServiceRequest={$json}"
        )
    );
    $context = stream_context_create($opts);
    $response = @file_get_contents( $url, false, $context);
    return JSON_decode( $response, true);
}

function SDBEE_service_endpoint_token( $gateway, $functionPath, $token, $request) {
    $url = $gateway.'/'.$functionPath.'/';  // $token after gateway ?
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
    return JSON_decode( $response, true);
}

function SDBEE_service_endpoint_gcf( $gateway, $functionPath, $serviceAccount, $request) {
    // Set up credentials file    
    $serviceAccountCredentials = __DIR__."/../../.config/sd-bee-{$serviceAccount}.json"; //gcs.json";
    if ( !file_exists( $serviceAccountCredentials)) return [ 'success' => false, 'message' => "No crendentials", 'data' => "Searching for {$serviceAccount}"];
    putenv('GOOGLE_APPLICATION_CREDENTIALS='.$serviceAccountCredentials);
    // URL
    $targetAudience = $gateway.$functionPath;
    // Set up client Guzzle
    $middleware = ApplicationDefaultCredentials::getIdTokenMiddleware($targetAudience);
    $stack = HandlerStack::create();
    $stack->push($middleware);
    $client = new Client([
        'handler' => $stack,
        'auth' => 'google_auth',
        'base_uri' => $gateway,
    ]);
    // Send request and get JSON response
    // 2DO Use JSON with \GuzzleHttp\RequestOptions::JSON => $request
    try{
        $response = $client->post(
            $functionPath,
            [ \GuzzleHttp\RequestOptions::JSON => $request]
        );
        $response = JSON_decode( (string) $response->getBody(), true);        
    } catch( Exception $e) {
        $msg = $e->getMessage();
        if ( strpos( $msg, "403")) $msg = "unauthorized access";
        $response =  [ 'success'=> false, 'message'=> $msg, 'data' => ""];    
    }
    return $response;
}
        

function SDBEE_service_endpoint_getServiceMap() {
    $map = [];
    // Build map with JSON files from app/local-services, .config/added-local-service and .config/external-services
    $dirs = [ __DIR__.'/../local-services', SDBEE_extended_local_services, SDBEE_external_services];
    for ( $diri=0; $diri < count( $dirs); $diri++) {
        $dir = val( $dirs, $diri);
        if ( !file_exists( $dir)) continue;
        $files = scandir( $dir);
        for ( $filei=0; $filei < count( $files); $filei++) {
            $file =val( $files, $filei);
            if ( !strpos( $file, ".json")) continue;
            // Open each JSON file
            $fileContents = @file_get_contents( $dir.'/'.$file);
            // Extract composer instructions
            $w = JSON_decode( $fileContents, true);
            if ( $w && count($w)) {
                foreach ( $w as $service=>$endpoint) {
                    $map[ $service] = $endpoint;
                }
            }
        }
    }    
    return $map;
}

function SDBEE_service_endpoint_error( $response, $data=[]) {
    $jsonResponse = [ 
        'success'=>false, 
        'message'=> $response,
        'data'=>$data
    ];
    echo JSON_encode( $jsonResponse);
    return $jsonResponse;
}

define ( 'VENDOR_AUTOLOAD', 'vendor/autoload.php');
if ( isset( $request)) echo JSON_encode( SDBEE_service_endpoint( $request));

