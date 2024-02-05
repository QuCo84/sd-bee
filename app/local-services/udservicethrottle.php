<?php
/**
 * udservicethrottle.php -- manage consumption of payable services
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

 /* How to detect browser print
 Use background image.php in print css to detect print request. 
 or window.addEventListener('beforeprint', (event) => {
  console.log('Before print');
});
same for afterprint

{ "icredits":50, "iallowedOverdraft":0, "dvalidity":"31/10/2022"}
bug dataset eof ret

!!! IMPORTANT OS version - 2DO check compataibility with smartdoc/SOILinks
2DO
   clear() delete old entries
   use integer for dvalidty = date + days
 
 */

define ( 'THROTTLE_noService', 201);
define ( 'THROTTLE_serviceDisabled', 202);
define ( 'THROTTLE_noCredits', 203);
define ( 'THROTTLE_writeError', 204);
define( 'THROTTLE_maxEntriesAfterStatus', 20);

 
class UD_serviceThrottle {
    
   public $lastError = 0;
   public $lastResponse = "";
   public $lastResponseRaw = null;

   private $db = null;

   function __construct( $db = null) {
        global $ACCESS;
        $this->db = ( $db) ? $db : ( ($ACCESS) ? $ACCESS : null);
   }
   /**
    * A - METHODS FOR DOCUMENTS AND WEB APPS OPERATIONS
    */    

   /**
    * Return id of throttle if service available to current user else false (0)
    */    
    function isAvailable( $service, $user="") {
        // 2DO check session
        $this->lastError= "";
        if (!$this->db && !function_exists( 'LF_fetchNode')) return $this->error( THROTTLE_noService, "No database");
        // Find account user
        if ( !$user) $user = LF_env( 'user_id'); //$accountOID = $this->accountUser();
        $throttleLogId = $service.'_throttle';
        $logData = $this->getLog( $throttleLogId = $service.'_throttle');
        if ( !$logData) return $this->error( THROTTLE_noService, "No log for $service found");            
        // Get status
        $throttleStatus =  $this->getStatus( $service);
        // Check throttle data
        $credits = val( $throttleStatus, 'icredits');
        $allowedOverdraft =  ( val( $throttleStatus, 'iallowedOverdraft')) ? $throttleStatus[ 'iallowedOverdraft'] : 0;
        $currentPeriodEnds = val( $throttleStatus, 'dvalidity');
        //var_dump( $throttleLogId, $throttleStatus);
        if ( $credits > -$allowedOverdraft) {
            // Sufficient credits
            $r = $throttleLogId;
        } else {
            // Insufficient credits
            // 2DO Surcharge
            return $this->error( THROTTLE_noCredits, "Please buy credits and retry");
        }
        /*
        // 2DO Check account and sub-accounts NOT always see timing
        while ( $accounts) {
            $account = array_pop( $accounts);
            if ( !$this->isAvailable( $service, $account)) {
                // Account is blocked
            };
        }
        */
        // 2DO Store in session
        return $r;
    } // UD_serviceThrottle=>isAvailable()

    function getStatus( $service) { /* , $taskId) */
        $status = [];
        $credits = 0;
        $validityDate = -1;
        $nbProcessed = 0;
        $allowedOverdraft = 0;
        if (!$this->db && !function_exists( 'LF_fetchNode')) return $this->error( THROTTLE_noService, "No database");
        $logData = $this->getLog( $service.'_throttle');
        if ( !$logData) return $this->error( THROTTLE_noService, "No log for $service");
        $today = LF_date();
        for ( $datai=0; $datai < count( $logData); $datai++) {
            $log =  val( $logData, $datai);
            $dbName = val( $log, 'name');
            $nameParts = explode( '_', val( $log, 'name'));
            // Get entry's date   
            $entryTime = 0;    
            $date = ( count( $nameParts) >= 3) ? $date = $nameParts[2] : $date = $nameParts[0];
            $date = substr( $date, 0, 4).'-'.substr( $date, 4, 2).'-'.substr( $date, 6, 2).' '.substr( $date, 8, 2).':'.substr( $date, 10, 2);
            $entryTime = strtotime( $date);
            // Get details
            $details = JSON_decode( $log[ 'tdetails'], true);       
            /* Code to manage credits granted by processes
            if ( $taskId && val( $details, 'task')) {
                // Ignore record if not this task
                if ( $taskId != val( $details, 'task')) continue;
                // if ( $progress != val( $details, 'progress')) continue;
            }
            */
            // Check validity date
            if ( val( $details, 'dvalidity'))  {      
                if ( is_string( val( $details, 'dvalidity'))) $validityDate = LF_date( val( $details, 'dvalidity'));
                else $validityDate = $entryTime + $details[ 'dvalidity']*86400;                
                if ( $validityDate < $today) continue;
            }    
                  
            if (  val( $log, 'nevent') == "status") { 
                // Status record : add already counted credits, take latest validity and return
                $status = $details;
                $status[ 'icredits'] += $credits;
                if ( $validityDate > val( $status, 'dvalidity'))  $status[ 'dvalidity'] = $validityDate;
                $credits = 0;                
            } elseif (  val( $log, 'nevent') == "consume") {
                // Consume record
                $credits -= val( $log, 'iresult');
                /* Code to manage credits granted by processes
                if ( val( $details, 'grant')) {
                    unset( $grants[ $details[ 'grant'][ 'id']]);
                }
                */
            } elseif (  val( $log, 'nevent') == "credit") {                
                // Credit record               
                $credits += val( $log, 'iresult');
                /* Code to manage credits granted by processes
                if ( val( $details, 'grant')) {
                    // could use progress as grant key
                    $grants[ $details[ 'grant'][ 'id']] = val( $details, 'grant');
                }
                */
            }
            $nbProcessed++;
        }   
        if ( !$status) $status = [ 'icredits'=>0, 'iallowedOverDraft'=>0, 'dvalidity'=>""];
        if ( $credits) $status[ 'icredits'] += $credits;
        if ( $allowedOverdraft) $status[ 'iallowedOverdraft'] = $allowedOverdraft;
        if ( $validityDate) $status[ 'dvalidity'] = $validityDate;
        /* Code to manage credits granted by processes
        if ( count( $grants)) {
            $keys = array_keys( $grants)
            $status[ 'grant'] = $grants[ $keys[ count( $keys) -1]]; 
        } 
        */
        if ( $nbProcessed > THROTTLE_maxEntriesAfterStatus) {
            // Add a status record 2D0 use $logSet->addEntry
            $this->setStatus( $service, $status);
        }
        // Save any changes (substitutions)
        /*
        try {
            $logSet->save();
        } catch( Exception $e) {
            // Don't do anything            
        }*/
        // Return as error if no credits
        if ( ( $status[ 'icredits'] + val( $status, 'iallowedOverDraft')) <= 0) return $this->error( THROTTLE_noCredits, "No credits for $service");
        return $status;
    }
/*
    CREATE TABLE 'ServiceLog' (
        name text NOT NULL,
        userId int(11) NOT NULL,
        nevent text NOT NULL,
        iresult int( 11) DEFAULT NULL,
        tdetails text DEFAULT NULL
      );
      */
    function setStatus( $service, $status) {
        $entry = [
            'nevent' => "status",
            'iresult' => $status[ 'icredits'],
            'tdetails' => JSON_encode( $status)
        ];
        $r = $this->createLogEntry( $service.'_throttle', $entry);
    }

    /**
     * Add credits after purchase
     */
    function addCredits( $logName, $credits, $validity, $comment, $taskName="") {
        $entry = [
            'nevent' => "credit",
            'iresult' => $credits,
            'tdetails' => JSON_encode( [
                'icredits'=>$credits, 'dvalidity'=>$validity, 'comment'=>$comment, 'iallowedOverdraft'=>0, 'task'=>$taskName
            ])
        ];
        $r = $this->createLogEntry( $logName."_throttle", $entry);
    }
    
   /**
    * Record consumption of a service
    */    
    function consume( $logName, $credits, $comment, $details =[]) {
        // 2DO Check is available
        // 2DO Alert site manager
        // Record ticket
        $details[ 'icredits'] =-$credits;
        $details[ 'comment'] = $comment;
        $logEntry = ['nevent'=>"consume", 'iresult'=>$credits, 'tdetails'=>JSON_encode( $details)];   
        $r = $this->createLogEntry( $logName, $logEntry);        
        /*
        if ( $logName == 'Task_throttle' && CDN) {
            // Carry credit through to marketplace/CDN with less detailed info
        }

        */
    }    

   /**
    * B - METHODS FOR ACCOUNT PAGES AND ACCOUNTING
    */    
    
    /**
     * Receive credits for use of a resource
     */
    function receiveCredits( $credits, $comment, $details=[]) {

    }

    // 2DO copy to sd-bee   
    function taskProgressChange( $taskName, $model, $params, $newProgress) {
        // Check caller is allowed
        $caller = debug_backtrace()[1];
        if ( 
            $caller[ 'class'] != "SDBEE_doc"  // OS version
            && strpos( $caller['file'], 'linkscore.php') === false  // SOILinks
        ) {
            //var_dump( 'bad call', debug_backtrace());
            //die();
            return false;
        }
        // Check progress has changed
        if (  val( $params, 'progress') == $newProgress) return true;
        $comment = "Use of $model";
        // Lookup credits associated with progress value
        $creditsByStep = $params[ 'credits-by-step'];
        if ( !isset( $creditsByStep[ 'n'.$newProgress])) return true;
        // Check credits not already consumed for this task
        $logData = $this->getLog( 'Task_throttle');
        $add = true;
        for ( $logi=i; $logi < count( $logData); $logi++) {
            $log =  val( $logData, $logi);
            $details = JSON_decode( $log[ 'tdetails'], true);
            if ( $details[ 'task'] != $taskName) continue;
            if ( $details[ 'step'] != $newProgress) continue;
            // 2DO dvalidity and nevent=consume
            $add = false;
            break;
        }
        if ( $add) {
            // Consume credits 
            $this->consume( "Task", $creditsbyStep, $comment, [ 'task'=>$taskName, 'step'=>$newProgress]);
            // Enable services 
            $servicesByStep = $params[ 'services-by-step'];
            if ( isset( $servicesByStep[ 'n'.$newProgress])) {
                $services =  val( $servicesByStep, $newProgress);
                for ( $servi=0; $servi < count( $services); $servi++) {
                    $service =  val( $services, $servi); // { name:Name, credits:credits, comment:comment}
                    //2DO get grant from mp & store in details
                    $this->addCredits( $service[ 'name'], $service[ 'credits'], 10, $comment, $taskName);
                }
            }
        }
        return true;
    }
  /**
    * Return array with a user's service consumption between dates
    */    
    function getConsumption( $dates, $user) {
        // Get account user
        // Get tickets
        // Build total by service
        // Return        
    }
    
  /**
    * Return array with cost of service consumption between dates
    */    
    function invoice( $dates) {
        // Get consumption
        // Get account subscription & pricing
        // If none (permissions) return null
        // For each service
            // Included in subscription
            // Extra
        // Update Invoice monitoring
        // External ? Account, Service, outstanding 
        // Build invoice    
        // Return
    }

  /**
    * Receive payment
    */    
    function payment( $data) {
        // Find invoice
        // Tick line by line (% paid)
        // Update outstanding

    }
   /**
    * C - METHODS ERP INTEGRATION
    */   
    
  /**
    * Exchange with ERP
    */    
    function ERP() {
    
    }

   /**
    * D - INTERNAL METHODS
    */    

   /**
    * Return log id
    */
    function _findLog() {
        
    }

   /**
    * Return account user who owns current user
    */    
    function accountUser() {
        /*
        // Find account user
        $currentOid = "LINKS_user--2-".LF_env( 'user_id');
        $accountUserOid = "";
        $account = LF_env( 'user_id');
        // Follow account labeled links from user
        $useOid = $currentOid."-2--NO|OIDLENGTH|tlabel|account";
        $accountParentData = $this->fetchNode( $useOid, "* tlabel");
        while ( LF_count( $accountParentData) >= 2) {
            $parentData = $accoutParentData[1];
            $parentId = val( $parentData, 'id');
            $currentOid = LF_mergeOid( $currentOid, [LINKS_user, $parentId]);
            $accountUserOid = LF_mergeOid( $accountUserOid, [LINKS_user, $parentId]);
            $account = $parentId;
            $accountParentData = $this->fetchNode( $currentOid, "--NO|OIDLENGTH-tlabel|account", "* tlabel");
            // var_dump( $currentOid, $accountParentData);
        }
        $this->account = $account;
        return $accountUserOid;
        */
    } 

   /**
    * Return account subscription info
    *  Array of 
    * service => paidUse, extraUseLimit, extraUsePrice, lastPaid, extraUseOutstanding
    */    
    function accountPricing( $user=currrent) {
        // Find account user
        // Check if service is enabled and shared for this user
            // Unauthorised service enable in your config or contact account manager
        // Check throttle data
            // Insufficient credits
            // Surcharge
    } // UD_serviceThrottle=>isAvailable()

   /**
    * Return array with account's outstanding payments
    */
    function accountOutstanding( $service, $checkInvoices=false) {
        // Get outstanding
        if ( !$checkInvoices) return $outstanding;
        // Get all invoice records that are not paid
        // Build totals
        // Check against $outstanding
        // Update & report differences
        // Return $outstanding
    }

   /*
    * E - Logging functions
    * maybe adapted to environnment include in uddatamodel
    */
    function writeLog() {}
    function ReadLog() {}  
           
    function createLog( $service, $user="") {
        if ( count( $this->getLog( $throttleLogId = $service.'_throttle'))) return;
        $details = [ 'icredits'=>20, 'iallowedOverdraft'=>0, 'dvalidity'=>time()];
        $entry = [
            ['nevent'=>"create", 'iresult'=>0, 'tdetails'=>JSON_encode( $details)]
        ];
        $logId = "{$service}_throttle";
        $this->createLogEntry( $logId, $entry);
        // Create 1st entry
        $entry = ['nevent'=>"credit", 'iresult'=>0, 'tdetails'=>JSON_encode( $details)];        
        $this->createLogEntry( $logId, $entry);
    }

    function createLogEntry( $logId, $entry) {
        // Auto fill nname and iuser 
        $entry[ 'name'] = $logId.'_'.date( "YmdHis")."_".$entry[ 'nevent'].'_by_'.LF_env( 'user_id');
        $entry[ 'userId'] = LF_env( 'user_id');
        if ( $this->db) return $this->db->createLogEntry( $logId, $entry);
        else {
            // SOILinks version (still used by sd-bee.com and central marketplace)
            $entry[ 'nname'] = val( $entry, 'name');
            $entry[ 'iuser'] = val( $entry, 'userId');
            unset( $entry[ 'name']);
            unset( $entry[ 'userId']);
            $logData = [[ 'nname', 'iuser', 'nevent', 'iresult', 'tdetails'], $entry];
            $logTopData = LF_fetchNode( "LogEntry--22--nname|{$logId}");
            if ( LF_count( $logTopData) > 1) $logTopId = $logTopData[1][ 'id']; 
            if ( TEST_ENVIRONMENT) $r = $LFF->createNode( "LogEntry--22-$logTopId}", "LogEntry", $logData); 
            else $r = LF_createNode( "LogEntry--22-{$logTopId}", "LogEntry", $logData);        
            if ( $r < 0) { 
                $this->lastError = THROTTLE_writeError;
                $this->lastMessage = $r; 
                echo $r;
                return false;
            }
            return true;
        }
    }

    // test only ?
    function clearLog( $service) {
        if ( $this->db) $this->db->clearLog( $service.'_throttle');
    }

    function error( $code, $msg, $return=false) {
        $this->lastError = $code;
        $this->lastResponse = $msg;
        return $return;
    }

    function getLog( $logId) {
        if ( $this->db) return $this->db->getLog( $logId);
        else {
            // SOILinks version (still used by sd-bee.com and central marketplace)  
            $w = LF_fetchNode(  "LogEntry--22-0-22--nname|{$logId}");
            $log = [];
            //$w = LF_fetchNode(  "LogEntry--22-{$logId}-22--NO|OIDLENGTH-OR|nname%20%DESC|FR|1|LR|25"); 
            if ( $w && count( $w)) {
                // Adapt records to new format                
                for ( $wi=1; $wi < count( $w) &&  $wi < 25; $wi++) {                    
                   $w[ $wi][ 'name'] =  $w[ $wi][ 'nname'];
                   $w[ $wi][ 'userId'] = LF_env( 'user_id');
                   $log[] =  val( $w, $wi);
                }                
            }
            return $log;
        }
    }

} // PHP class UD_serviceThrottle

if ( isset( $argv) && $argv[0] && strpos( $argv[0], "udservicethrottle.php") !== false) {    
    // Launched with php.ini so run auto-test
    print "Syntax OK\n";
    define( 'TEST_ENVIRONMENT', true);    
    if ( file_exists( __DIR__."/../sdbee-config.php")) {
        // OS version
        include ( __DIR__."/../sdbee-config.php");
        include ( __DIR__."/../sdbee-access.php");
        include ( __DIR__."/../editor-view-model/helpers/uddatamodel.php");
        // 2DO include a testenv.php with next line or move to datamodel
        $_SERVER[ 'REMOTE_ADDR'] = "192.168.1.1";
        global $ACCESS, $CONFIG;
        $CONFIG = SDBEE_getconfig();
        $ACCESS = new SDBEE_access( $CONFIG[ 'access-database']);
        function nextTest( $throttle) {
            global $TEST_NO, $ACCESS, $CONFIG;
            switch ( $TEST_NO) {
                case 1 : // Login                
                    $r = $ACCESS->login( "a", "b", [ 'a'=>"demo", 'b'=>"demo"]);
                    // echo strlen( $r).substr( $r, 23000, 500);
                    if (  $r) echo "Login test : OK\n";
                    else echo "Login test: KO\n";
                    LF_env( 'user_id', $r);                
                    break;
                case 2 :
                    //$throttle->createLog( 'images');
                    /*$throttle->consume( 'images', 20, 'test');
                        */
                    $throttle->clearLog( 'images');
                    $throttle->addCredits( 'images', 20, 31, "test");
                    $rep = $throttle->getStatus( "images");
                    break;   
                case 3 :
                    $service = "images";
                    $rep = $throttle->isAvailable( "images");
                    if ( $rep)
                        echo "Throttle test : OK\n";
                    else {
                        echo "Throttle test: KO {$service} {$throttle->lastError}\n";
                        if ( strpos( $throttle->lastError, "not enabled")) {
                            $throttle->createLog( $service);
                            $TEST_NO--;
                        }
                        // echo $page;
                    }       
                    $rep = $throttle->getStatus( "images");
                    echo "You have ".$rep[ 'icredits']." credits\n";         
                    $throttle->consume( 'images', 10, 'test');
                    $throttle->consume( 'images', 10, 'test');
                    $rep = $throttle->getStatus( "images");
                    echo "You have ".$rep[ 'icredits']." credits\n";
                    break;            
                case 4 :
                    break;
            }
            $TEST_NO++;
        }
        // Test
        print "udservicethrottle.php auto-test program\n";    
        $throttle = new UD_serviceThrottle();
        $TEST_NO = 1;
        while( $TEST_NO < 4) { sleep(1); nextTest( $throttle);}
        echo "Test completed\n";
    } else {
        // Legacy SOILinks version
        // Launched with php.ini so run auto-test
        function nextTest( $throttle) {
            global $TEST_NO, $LF, $LFF;
            switch ( $TEST_NO) {
                case 1 : // Login
                    $r = $LFF->openSession( "demo", "demo", 133);
                    // echo strlen( $r).substr( $r, 23000, 500);
                    if (  strlen( $r) > 1000 && stripos( $r, "Autotest")) echo "Login test : OK\n";
                    else echo "Login test: KO\n";
                    LF_env( 'user_id', 133);
                    break;
                case 2 :
                    $service = "images";
                    $rep = $throttle->isAvailable( "images");
                    if ( $rep)
                        echo "Throttle test : OK\n";
                    else {
                        echo "Throttle test: KO {$service} {$throttle->lastError}\n";
                        if ( strpos( $throttle->lastError, "not enabled")) {
                            $throttle->createLog( $service);
                            $TEST_NO--;
                        }
                        // echo $page;
                    }
                    break;            
                case 3 :
                    break;
            }
            $TEST_NO++;
        }
        // Create test environment
        require_once( __DIR__."/../tests/testenv.php");
        require_once( __DIR__."/../tests/testsoilapi.php");
        $LFF = new Test_dataModel();
        LF_env( 'cache', 5);
        // Test
        print "udservicethrottle.php auto-test program\n";    
        $throttle = new UD_serviceThrottle();
        $TEST_NO = 1;
        while( $TEST_NO < 4) { sleep(1); nextTest( $throttle);}
        echo "Test completed\n";
    }
} 