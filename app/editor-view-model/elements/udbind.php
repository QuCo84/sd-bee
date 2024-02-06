<?php
/**
 * Enable binding
 */
function UD_setupInputScriptWithBinding() {
    $scr = <<<EOT
if ( LF_env( 'cache')>10) { include_once( "./upload/smartdoc/ud-view-model/udbind.php");}
else { include_once( "./upload/smartdoc_prod/ud-view-model/udbind.php");}
if ( !UD_processBindedElementWrite()) {
   // Not binded - default processing
   if ( \$INPUT_DATA[1]['tcontent']) \$INPUT_DATA[1]['tcontent']=rawurldecode( \$INPUT_DATA[1]['tcontent']);
}
return true;
EOT;
    $_SESSION["INPUT_UDE_FETCH"] = $scr;
 }
/**
 * Bind an element, ie capture write operations to an element & deflect them to a another element
 * typically written element is JSON & deflect element is SetOfValues
 */
function UD_bindAnElement( $source, $target, $fieldMap = [ 'tcontent'=>'tvalues'], $jsonPath = [ 'tcontent'=>'data/value']) { 
    // Bind a UD element
    $bind = [ 'oid'=>$target, 'fieldMap'=>$fieldMap, 'jsonPath'=>$jsonPath];
    $binds = LF_env( 'UD_binded');
    if ( !$binds) { $binds = [];}
    $binds[ $source] = $bind;
    LF_env( 'UD_binded', $binds);
}
/**
 * Process captured write operations for element binding
 */
function UD_processBindedElementWrite() {
    global $input_oid, $INPUT_DATA, $INPUT_RESULT;
    $binded = LF_env( 'UD_binded');
    if ( val( $binded, $input_oid)) { $bind = val( $binded, $input_oid);}
    if ( !$bind) return false;    
    // Use binded OID
    $input_oid = val( $bind, 'oid');
    // Copy /map field values
    $fieldMap = val( $bind, 'fieldMap');
    $data = $INPUT_DATA[ 1];
    $INPUT_DATA = [[], []];
    foreach ( $fieldMap as $source => $target) {
        $INPUT_DATA[0][] = $target;
        $value = val( $data, $source);
        if ( val( $bind, 'jsonPath')[ $source])) { 
            $jsonPath = $bind[ 'jsonPath'][ $source];
            $json = JSON_decode( $value, true);
            $jsonPath = explode( '/', $jsonPath);            
            switch (LF_count( $jsonPath)) {
               case 1 : $value = $json[ $jsonPath[0]]; break;
               case 2 : $value = $json[ $jsonPath[0]][ $jsonPath[1]]; break;
               case 3 : $value = $json[ $jsonPath[0]][ $jsonPath[1]][ $jsonPath[2]]; break;
            }
        }
    }
    if ( is_array( $value)) { $value = JSON_encode( $value);}
    $INPUT_DATA[1][ $target] = $value;
    return true;
}
// Auto-test
if ( isset( $argv) && strpos( $argv[0], "udbind.php") !== false) {
    // CLI launched for tests
    echo "Syntax OK\n";
    echo "Setup test environment\n";    
    require_once( __DIR__."/../tests/testenv.php");
    LF_env( 'cache', 5);    
    global $input_oid, $INPUT_DATA;
    UD_bindAnElement( "UniversalDocElement--21-6", "SetOfValues--16-5");
    $input_oid = "UniversalDocElement--21-6";
    $INPUT_DATA = [ [ "tcontent"], [ 'tcontent'=>'{"meta":{}, "data":{"value":{"right":"left"}}}']];
    UD_processBindedElementWrite();
    if ( $input_oid == "SetOfValues--16-5" && $INPUT_DATA[1]['tvalues'] == '{"right":"left"}' ) echo "Test 1: OK"; else echo "Test 1 : KO $input_oid ". $INPUT_DATA[1]['tvalues'];
    // var_dump( LF_env( 'UD_binded'));
}

?>