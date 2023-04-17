<?php
// SPDX-License-Identifier: GPL-3.0
/**
 *  udservicethrottle.php -- gateway to services
 *
 *  For each service a log file stores the history of the service's use :
 *  <li><ul>previousRecordOffset<ul>
 *  <ul>ddatetime (SOILinks format)</ul>
 *  <ul>nuser a unique identifier of the user (a single log file can be used for multiple users</ul>
 *  <ul>naccount a unique identifier of the associated account</ul>
 *  <ul>nservice (a single log can be used for multiple services</ul>
 *  <ul>sevent (status, use, credit)
 *  <ul>ivalue</ul>
 *  <ulttextra</ul></li>
 *
 *  A LINKS_log object is unique for each user & service. It contains the following information :
 *  <li><ul>nname</ul>
 *  <ul>nfilename multiple logs can use the same log file to simplify server backup operations</ul>
 *  <ul>ioffset</ul>
 *  <ul>irowsCount</ul>
 *  <ul>tentry writing appends to log & updates writeoffset, reading returns entry at _readOffset</ul>
 *  <ul>tformat a format string to convert array data for tentry into a string and vice versa </ul>
 *  <ul>tdata data updated on the fly. Writtng an array adds a value to each field, Reading returns an array</ul>
 *  <ul>dlastArchive</ul>
 *  <ul narchiveNamePattern</ul>
 *  <ul>iarchiveFrequency</ul>
 *  <ul>tsecret</ul></li>
 *
 *  For SOILinks :
 *  modifyInstance if table == LINKS_log :
 *    if field == tentry convert array to string using tformat, write line to filename and update lastWriteOffset,
 *    if field == tdata decode, add array to values and re-encode
 *  read
 *    if linked_field label starts with LOGFILE read rowCount lines backwards from cached read offset, convert to arrays. 
    dataFromDB
 *    if field == tdata, decode
 *    field.label.class    => nfilename.ioffset.LOGFILE_20_10
 *  
 *  The current status is determined by finding the most recent "status" event and takeing into account 
 *  all movements since this event. This is performed by isAvailable(). COnsumption is indicated by 
 *  consume() and credits are added with payment().
 *  Consumption and invoicing is determined by looking at all movements between 2 dates
 *  Connection with an external system is achieved bu erp() which can retrieve payments and update invoices.
 */
 
 /* How to detect browser print
 Use background image.php in print css to detect print request. 
 or window.addEventListener('beforeprint', (event) => {
  console.log('Before print');
});
same for afterprint

{ "icredits":50, "iallowedOverdraft":0, "dvalidity":"31/10/2022"}
bug dataset eof ret

2DO
   clear() delete old entries
   use integer for dvalidty = date + days
 
 */
define ( 'THROTTLE_noService', 201);
define ( 'THROTTLE_serviceDisabled', 202);
define ( 'THROTTLE_noCredits', 203);
define( 'THROTTLE_maxEntriesAfterStatus', 20);

 
class UD_serviceThrottle {
    
   public $lastError = 0;
   public $lastResponse = "";
   public $lastResponseRaw = null;
   /**
    * A - METHODS FOR DOCUMENTS AND WEB APPS OPERATIONS
    */    

   /**
    * Return id of throttle if service available to current user else false (0)
    */    
    function isAvailable( $service, $user="") {
        // 2DO check session
        $this->lastError= "";
        // Find account user
        if ( !$user) $accountOID = $this->accountUser();
        // Check if service is enabled and shared for this user
        // Look for throttle log   2DO parameters ?
        $logClassId = LF_getClassId( "LogEntry");
        if ( $accountOID) $throttleLogOid = new DynamicOID( "#DOCN", 0, $accountOID, "LogEntry", "{$service}_throttle");
        else $throttleLogOid = new DynamicOID( "#DCN", 0, "LogEntry", "{$service}_throttle");
        $throttleLogData = $this->fetchNode( $throttleLogOid->asString()); //LF_oidToString( [(int) $logClassId], "--nname|{$service} throttle"));
        if ( LF_count( $throttleLogData)>1) {
            // Log found so service enabled
            $throttleLogId = $throttleLogData[1][ 'id'];
        } else {
            // Unauthorised service enable in your config or contact account manager
            $this->lastError = THROTTLE_serviceDisabled;
            $this->lastResponse = "Service not enabled. Please enable in your config or contact account manager";
            return false;
        }
        // Get status
        $throttleStatus =  $this->getStatus( $throttleLogId);
        // Check throttle data
        $credits = $throttleStatus[ 'icredits'];
        $allowedOverdraft =  $throttleStatus[ 'iallowedOverdraft'];
        $currentPeriodEnds = $throttleStatus[ 'dvalidity'];
        //var_dump( $throttleLogId, $throttleStatus);
        if ( $credits > -$allowedOverdraft) {
            // Sufficient credits
            $r = $throttleLogId;
        } else {
            // Insufficient credits
            // Surcharge
            $this->lastError = THROTTLE_noCredits;
            $this->lastResponse = "Please buy credits and retry";
            $r = false;
        }
        /*
        // Check account and sub-accounts NOT always see timing
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

    function getStatus( $logId) {
        $status = [];
        $credits = 0;
        $validityDate = -1;
        $nbProcessed = 0;
        $logData = $this->fetchNode(  "LogEntry--22-{$logId}-22--NO|OIDLENGTH-OR|nname%20%DESC|FR|1|LR|25"); 
        $logSet = new Dataset();
        $logSet->load( $logData);
        $today = LF_date();
        while ( !$logSet->eof()) {
            $log = $logSet->next();
            $dbName = $log[ 'nname'];
            if ( strpos( $dbName, '{') !== false) {
                // Name contains fields that need substitution
                $substitute = [ 
                    'DateTimeStamp' => date( "YmdHis"),
                    'user' => LF_env( 'user_id')
                ];
                $log[ 'nname'] = $dbName = LF_substitute( $dbName, $substitute);
                $logSet->update( $log);
            }
            // Get entry's date            
            $entryDateStr = "";
            $entryDateStr .= substr( $dbName, 0, 4).'-'.substr( $dbName, 4, 2).'-'.substr( $dbName, 6, 2).' ';
            $entryDateStr .= substr( $dbName, 8, 2).':'.substr( $dbName, 10, 2);
            $entryDate = LF_date( $entryDateStr);
            if ( $log[ 'nevent'] == "status") { 
                // Status record : add already counted credits, take latest validity and return
                $status = JSON_decode( $log[ 'tdetails'], true);
                $status[ 'icredits'] += $credits;
                $credits = 0;
                if ( is_string( $status[ 'dvalidity'])) $status[ 'dvalidity'] = LF_date( $status[ 'dvalidity']);                    
                else $status[ 'dvalidity'] = $entryDate + $status[ 'dvalidity']*8640;                
                if ( $validityDate > $status[ 'dvalidity']) $status[ 'dvalidity'] = $validityDate;
                break;
            } elseif ( $log[ 'nevent'] == "consume") {
                $credits -= $log[ 'iresult'];
            } elseif ( $log[ 'nevent'] == "credit") {                
                $details = JSON_decode( $log[ 'tdetails'], true);
                if ( is_string( $details[ 'dvalidity'])) $validityDate = LF_date( $details[ 'dvalidity']);
                else $validityDate = $entryDate + $details[ 'dvalidity']*8640;
               // else $validityDate = $details[ 'dvalidity'];
               if ( $validityDate >= $today) $credits += $log[ 'iresult'];
            }
            $nbProcessed++;
        } // end while    
        if ( !$status) $status = [ 'icredits'=>0, 'iallowedOverDraft'=>0, 'dvalidity'=>""];
        if ( $credits) $status[ 'icredits'] += $credits;
        if ( $allowedOverdraft) $status[ 'iallowedOverdraft'] = $allowedOverdraft;
        if ( $validityDate) $status[ 'dvalidity'] = $validityDate;
        if ( $nbProcessed > THROTTLE_maxEntriesAfterStatus) {
            // Add a status record 2D0 use $logSet->addEntry
            $this->setStatus( $logId, $status);
        }
        // Save any changes (substitutions)
        /*
        try {
            $logSet->save();
        } catch( Exception $e) {
            // Don't do anything            
        }*/
        return $status;
    }

    function setStatus( $logId, $status) {
        $entry = [
            'nname' => "Entryxx",
            'iuser' => $this->account,
            'nevent' => "status",
            'iresult' => $status[ 'icredits'],
            'tdetails' => JSON_encode( $status)
        ];
        $data = [ [ 'nname', 'iuser', 'nevent', 'iresult', 'tdetails'], $entry];
        $r = $this->createLogEntry( $logId, $data);
    }
    
   /**
    * Record consumption of a service
    */    
    function consume( $throttleId, $credits, $comment) {
        // Check is available
            // Alert site manager
        // Record ticket
        $details = [ 'icredits'=>-$credits, 'comment'=>$comment];
        $logEntry = ['nevent'=>"consume", 'iresult'=>$credits, 'tdetails'=>JSON_encode( $details)];    
        $r = $this->createLogEntry( $throttleId, $logEntry);            
    }

   /**
    * B - METHODS FOR ACCOUNT PAGES AND ACCOUNTING
    */    
    
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
        // Find account user
        $currentOid = "LINKS_user--2-".LF_env( 'user_id');
        $accountUserOid = "";
        $account = LF_env( 'user_id');
        // Follow account labeled links from user
        $useOid = $currentOid."-2--NO|OIDLENGTH|tlabel|account";
        $accountParentData = $this->fetchNode( $useOid, "* tlabel");
        while ( LF_count( $accountParentData) >= 2) {
            $parentData = $accoutParentData[1];
            $parentId = $parentData[ 'id'];
            $currentOid = LF_mergeOid( $currentOid, [LINKS_user, $parentId]);
            $accountUserOid = LF_mergeOid( $accountUserOid, [LINKS_user, $parentId]);
            $account = $parentId;
            $accountParentData = $this->fetchNode( $currentOid, "--NO|OIDLENGTH-tlabel|account", "* tlabel");
            // var_dump( $currentOid, $accountParentData);
        }
        $this->account = $account;
        return $accountUserOid;
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
        global $LF, $LFF;
        if ( !$user) {
            $details = [ 'icredits'=>20, 'iallowedOverdraft'=>0, 'dvalidity'=>LF_date( "31/10/2022")];
            $logData = [
                ['nname', 'iuser', 'nevent', 'iresult', 'tdetails'],
                ['nname'=>"{$service}_throttle", 'iuser'=>LF_env( 'user_id'), 'nevent'=>"status", 'iresult'=>0, 'tdetails'=>JSON_encode( $details)]
            ];
            if ( TEST_ENVIRONMENT) $r = $LFF->createNode( "", "LogEntry",$logData); else $r = LF_createNode( "", "LogEntry", $logData);
            if ( $r < 0) { echo $r; die();}
            $logId = $r;
            // Create 1st entry
            $entry = ['nevent'=>"credit", 'iresult'=>0, 'tdetails'=>JSON_encode( $details)];        
            $r = $this->createLogEntry( $logId, $entry);
        }
    }

    function createLogEntry( $logId, $entry) {
        global $LF, $LFF;
        // Auto fill nname and iuser 
        $entry[ 'nname'] = date( "YmdHis")."_".$entry[ 'nevent'].'_by_'.LF_env( 'user_id');
        $entry[ 'iuser'] = LF_env( 'user_id');
        $logData = [
            [ 'nname', 'iuser', 'nevent', 'iresult', 'tdetails'],
            $entry
        ];
        /*
        if ( TEST_ENVIRONMENT) 
            $r = $LFF->createNode( "LogEntry--22-{$logId}", "LogEntry", $logData); 
        else 
            $r = LF_createNode( "LogEntry--22-{$logId}", "LogEntry", $logData);
        if ( $r < 0) { echo $r; die();}
        */
    }

    function fetchNode( $oid, $cols="") {
        global $LF, $LFF;
        if ( TEST_ENVIRONMENT) return $LFF->fetchNode( $oid);
        return  LF_fetchNode( $oid, $cols);
    }

} // PHP class UD_serviceThrottle

if ( $argv[0] && strpos( $argv[0], "udservicethrottle.php") !== false)
{    
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