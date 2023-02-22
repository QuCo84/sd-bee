<?php
require_once( __DIR__."/testenv.php");
require_once( __DIR__."/testsoilapi.php");
$LFF = new Test_dataModel();
$TEST_NO = 1;
while( $TEST_NO < 6) { sleep(1); nextTest();}
echo "Test completed\n";

function nextTest() {
    global $TEST_NO, $LF, $LFF;
    switch ( $TEST_NO) {
        case 1 : // Login
            $r = $LFF->openSession( "demo", "demo", 133);
            // echo strlen( $r).substr( $r, 23000, 500);
            if (  strlen( $r) > 1000 && stripos( $r, "Autotest")) echo "Login test : OK\n";
            else echo "Login test: KO\n";
            break;
        case 2 :
            $docOid = 'UniversalDocElement--21-725-21--UD|3|NO|OIDLENGTH|CD|5';
            $docData = $LFF->fetchNode( $docOid);
            $ud = new UniversalDoc([ "mode"=>"edit", "displayPart" => "default"]); //, $LFF); 
            $ud->loadData( 'webdesk/UniversalDocElement--21-725-21--UD|3|NO|OIDLENGTH|CD|5', $docData);    
            $LF->currentBlock = "body/main/content/middleColumn/scroll"; // /document";
            $ud->initialiseClient();
            $page =  $LF->render()."\n";
            if ( strlen( $page) > 1000 && stripos( $page, "Autotest") && !stripos( $page, "warning") && !strpos( $page, "Fatal error"))
                echo "Load test : OK\n";
            else {
                echo "Load test: KO ".strlen( $page).' '.stripos( $page, "Autotest")."\n";
                // echo $page;
            }
            break;            
        case 3 :
            // Open form for doc creation
            $addFile = $LFF->fetchURI( "webdesk/UniversalDocElement--21/AJAX_addDirOrFile/p1%7Cfile");
            // Create a document without form = linksapi test mode
            $data = [
                [ '_nname', 'stype', 'tgivenname'], 
                [ '_nname'=>UD_utilities::getContainerName(), 'stype'=>UD_document, 'nstyle'=>"", 'tgivenname'=>"Save Test ".date( "Y/m/d H:i")]];
            $r = $LFF->createNode( "UniversalDocElement--21", 21, $data);;
            $p1 = strpos( $r, "id = ");
            $id = trim( substr( $r, $p1+5, strpos( $r, ",", $p1) - $p1 - 5));
            // echo "Docid $id "; die();
            LF_env( 'UDTEST_DOCID', $id);
            // Read newly created docuement
            $server = true;
            if ( $server) {
                $docOid = "UniversalDocElement--21-{$id}-21--UD|3|NO|OIDLENGTH|CD|5";
                $uri = "webdesk/{$docOid}/show";
                $page = $LFF->fetchURI( $uri);
            } else {
                $docData = $LFF->fetchNode( $docOid);
                $ud = new UniversalDoc([ "mode"=>"edit", "displayPart" => "default"], $LFF); 
                $ud->loadData( 'webdesk/UniversalDocElement--21-725-21--UD|3|NO|OIDLENGTH|CD|5', $docData);    
                $LF->currentBlock = "body/main/content/middleColumn/scroll"; // /document";
                $ud->initialiseClient();
                $page = $LFF->render()."\n";
            }
            if ( strlen( $page) > 1000 && stripos( $page, "Choisir un model") /*&& !stripos( $page, "warning")*/ && !strpos( $page, "Fatal error"))
                echo "Create test : OK\n";
            else echo "Create test: KO\n$uri\n$page\n";
            break;
        case 4 : 
            // Delete newly created doc
            $id = LF_env( 'UDTEST_DOCID', $id);
            $del = $LFF->deleteNode( "webdesk/UniversalDocElement--21-{$id}", "webdesk/UniversalDocElement--21-{$id}--AL|7/AJAX_deleteDocConf");
            //$uri = "webdesk/UniversalDocElement--21-{$id}--AL|7/AJAX_deleteDocConf";
            //$del = $LFF->fetchURI( $uri);            
            if ( ( stripos( $del, "page") || stripos( $del, "waste")) && !strpos( $page, "Fatal error"))
                echo "Delete test : OK\n";
            else echo "Delete test: KO\n$uri\n$del\n";
            break;
    }
    $TEST_NO++;
}

























?>