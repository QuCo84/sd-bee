<?php
/**
 * sdbee-service-gateway.php - Endpoint on SD bee server for access to local and remote services
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

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

 function SDBEE_endpoint_service( $request) {
    global $USER;
    // Build service map
    $map = SDBEE_service_endpoint_getServiceMap();
    // Parameters
    $localServices = [ 'doc'];
    // Get request and service
    $reqRaw = $request['nServiceRequest'];
    $serviceRequest = JSON_decode( urldecode( $reqRaw), true);
    $serviceName = $serviceRequest['service'];
    if ( !isset( $map[ $serviceName])) return [ "result"=>"KO", "msg"=>"No service $serviceName"];    
    // Throttle control
    $throttle = new UD_serviceThrottle();
    $throttleId = $throttle->isAvailable( $serviceName);
    /* Dev to activate
    $status = $this->throttle->status( $serviceName, $taskId, $progress);
    if ( !$status) return [ "success"=>false, "message"=>$this->throttle->lastMessage, "data"=>$this->throttle->lastError];
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
    /*
    // Look up how to access the requested service
    if ( $status[ 'grant']) {
        // Using a grant record for the service log
        $serviceInfo = $status[ 'grant'][ $serviceName];
        $protocol = $serviceInfo[ 'protocol'];
        $gateway = $serviceInfo[ 'baseURL'];
        $functionPath = $serviceInfo[ 'fctPath'];
        $serviceAccount = $serviceInfo[ 'account'];
        // $serviceAccountCredentials = $serviceInfo[ 'account'];  
        // 2DO Transfert variables to serviceRequest
        $serviceRequestParams = ( isset($serviceInfo[ 'serviceRequest'])) ? $serviceInfo[ 'serviceRequest'] : [];
        foreach ( $serviceRequestParams as $key=>$value) {
            $serviceRequest[ $key] = $value;
        }
    } else {
        // Use the service map
        $serviceInfo = explode( ' ', $map[ $serviceName]);
        $protocol = $serviceInfo[0];
        $gateway = $serviceInfo[ 1];
        $functionPath = $serviceInfo[ 2];
        $serviceAccount = $serviceInfo[ 3];
    }
    if ( $gateway == 'local') {
    */
    if ( $map[ $serviceName] == "local") {
        // Handle local services
        // Get parameters
        $params = null;
        // Call service via local gateway
        include_once __DIR__.'/../local-services/udservices.php';
        $services = new UD_services( $params);
	    $response = $services->do( $serviceRequest);
        return $response;
    } else {
        // Transmit request to a gateway for external services
        // Get parameters fro map entry protocol gateway/ functionName accountOrToken
        $serviceInfo = explode( ' ', $map[ $serviceName]);
        $protocol = $serviceInfo[0];
        $gateway = $serviceInfo[ 1];
        $functionPath = $serviceInfo[ 2];
        $serviceAccount = $serviceInfo[ 3];
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
            if ( $response[ 'success'] && $throttleId &&  isset( $response[ 'creditsConsumed'])) {
                // Update service log
                $throttle->consume( $throttleId, $response[ 'creditsConsumed'], $response[ 'creditsComment']);
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
    $url = $gateway.$token.'/'.$functionPath.'/';
    $json = JSON_encode( $request);
    $opts = array('http' =>
        array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/json',
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
            [ 'form_params' => [ "nServiceRequest" => JSON_encode( $request)]]
        );        
        // $client->get( $functionPath); //for debugging
        $response = JSON_decode( (string) $response->getBody(), true);        
    } catch( Exception $e) {
        $response =  [ 'success'=> false, 'message'=> $e->getMessage(), 'data' => ""];    
    }
    return $response;
}
        

function SDBEE_service_endpoint_getServiceMap() {
    $map = [];
    // Build map with JSON files from app/local-services and .config/external-services
    $dirs = [ __DIR__.'/../local-services', __DIR__.'/../../.config/external-services'];
    for ( $diri=0; $diri < count( $dirs); $diri++) {
        $dir = $dirs[ $diri];
        if ( !file_exists( $dir)) continue;
        $files = scandir( $dir);
        for ( $filei=0; $filei < count( $files); $filei++) {
            $file =$files[ $filei];
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

define ( 'VENDOR_AUTOLOAD', 'vendor/autoload.php');
if ( isset( $request)) echo JSON_encode( SDBEE_endpoint_service( $request));

