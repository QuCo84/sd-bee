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
    $throttleId = $this->throttle->isAvailable( $serviceName);
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
    // Look for a grant defined by model
    if ( $status[ 'grant']) {
        // Use a grant record
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
        // Use map
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
            $response = [ "result"=>"KO", "msg"=>"Not configured"];
        } else {
          
            // Get parameters and add to request
            $services = new UD_services( [ 'throttle'=>'off']);
            $services->_getParamsFromUserConfig( $serviceRequest);
            // Process according to protocol
            if ( $protocol == 'gcf') $responseJSON = SDBEE_service_endpoint_gcf( $gateway, $functionPath, $serviceAccount, $serviceRequest);
            elseif ( $protocol == 'soil') $responseJSON = SDBEE_service_endpoint_token( $gateway, $functionPath, $serviceAccount, $serviceRequest);
            elseif ( $protocol == 'bearer') $responseJSON = SDBEE_service_endpoint_bearer( $gateway, $functionPath, $serviceAccount, $serviceRequest);
            
 /*
            // CHEMIN Vers le fichier JSON défini plus haut sur votre poste
            $servieAccountCredentials = __DIR__."/../../.config/sd-bee-{$serviceAccount}.json"; //gcs.json";
            putenv('GOOGLE_APPLICATION_CREDENTIALS='.$serviceAccountCredentials);

            //Définition des urls à utiliser
            $targetAudience = $gateway.$functionPath;
            
            //Création du client Guzzle avec le middleware qui va gérer l'authentification
            $middleware = ApplicationDefaultCredentials::getIdTokenMiddleware($targetAudience);
            $stack = HandlerStack::create();
            $stack->push($middleware);
            $client = new Client([
                'handler' => $stack,
                'auth' => 'google_auth',
                'base_uri' => $gateway,
            ]);

            // Ensuite on peut envoyer la requête, par ex du JSON
            try{
                $response = $client->post(
                    $functionPath,
                    [ 'body' => "nServiceRequest=".urlencode($reqRaw)]
                );
                // For debuging $response = $client->get( $functionPath);
                echo $response;
            } catch( Exception $e) {
                $msg = $e->getMessage();
                // if ( strpos( $msg, "403")) echo "Unauthroized access";
                echo $e->getMessage();
            }
*/            
            $response = JSON_decode( $responseJSON, true);
            if ( $response->success && $throttleId &&  $service->creditsConsumed) {
                // Update service log
                $throttle->consume( $throttleId, $service->creditsConsumed, $service->creditComment);
            }            
            echo $responseJSON;
        }
    }
}

function SDBEE_service_endpoint_account( $gateway, $function, $account, $request) {
    $url = $gateway.'/'.$function.'/';
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
    return $response;
}

function SDBEE_service_endpoint_token( $gateway, $function, $token, $request) {
    $url = $gateway.$token.'/'.$function.'/';
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
    return $response;
}

function SDBEE_service_endpoint_gcf( $gateway, $function, $serviceAccount, $request) {
    // Set up credentials file
    $servieAccountCredentials = __DIR__."/../../.config/sd-bee-{$serviceAccount}.json"; //gcs.json";
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

    // Ensuite on peut envoyer la requête, par ex du JSON
    try{
        $response = $client->post(
            $functionPath,
            [ 'body' => "nServiceRequest=".urlencode($request)]
        );
        // For debuging $response = $client->get( $functionPath);
        echo $response;
    } catch( Exception $e) {
        $response = $e->getMessage();
        // if ( strpos( $msg, "403")) echo "Unauthorized access";
        // echo $e->getMessage();
    }
    return $reponse;
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
            $fileContents = @file_get_contents( $file);
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

