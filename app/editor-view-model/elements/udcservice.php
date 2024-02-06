<?php
/**
 * Universal Document element that fetches data from a service and presents it as a table
 *
 */
 require_once( __DIR__."/udconnector.php");
/**
 * Universal Document element that fetches data from a service and presents it as a table.
 *
 * <p> Use FlocalService() method in udcommands.php to invoke web service. The OID of the element 
 * is sent to the web service which can therefore retrive all JSON paramaters set by the user. The
 * "env" set of parameters is avaliable for setting values that the service retrieves from ENViromental 
 * variables.</p>
 */ 
 class UDconnector_service extends UDconnector {
    
    function __construct( $datarow) {
        $this->subType = "service";
        $defaultParameters = [
            'ready' => "no",
            'site' => "http...",
            'service' => "service",
            'action' => "action",
            'env' => [
                'env1' => "...",
                'env2' => "..."
            ],
            'fields' => "ntime,...",          
        ];
        $this->defaultParameters = $defaultParameters;
        parent::__construct( $datarow);
        $params = $this->JSONparameters;
        if ( $params && $params[ 'ready'] == "yes" ) {
            $env = [];
            foreach( $params[ $env] as $key=>$value) { if ( $value != "...") { $env[ $key] = $value;}}        
            $env_json = JSON_encode( $env);
            $service = val( $params, 'service');
            $action = val( $params, 'action');
            $this->initCommand = "localService( '{$service}', '{$this->oid}', '{$action}', '{$env_json}');"; 
        }            
    }
} // PHP class UDconnector_document

if ( isset( $argv) && strpos( $argv[0], "udcservice.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe OK\n";
    echo "Test completed\n";
} // end of auto-test