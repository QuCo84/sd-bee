<?php

echo "Loading test environment\n";
//phpinfo();
//echo "Ini file:".php_ini_loaded_file()."\n";
error_reporting(E_ALL & ~E_NOTICE);
define ( "TEST_ENVIRONMENT", true);
define ( "TEST_ENV_COLS", "nname stype nstyle tcontent textra");
define ( "TEST_ENV_CLASSID", 21);
define( "TEST_USER", "demo");
define( "TEST_PASS", "demo");
if ( !defined( "VENDOR_DIR")) {
    define( "VENDOR_DIR", 'D:\GitHub\vendor\\');
    define( "VENDOR_AUTOLOAD",VENDOR_DIR."autoload.php");
}
if ( FILE_exists( __DIR__."/../../../core/dev/linksapi.php")) define( "LINKS_DIR",  __DIR__."/../../../core/dev/");
if ( !defined( "LINKS_DIR")) {
    // On PC
    define( "LINKS_DIR", __DIR__."/../../../../links/");
    require_once LINKS_DIR."model/LF_PHP_lib.php";
    require_once LINKS_DIR."model/linkscorelib.php";
    require_once LINKS_DIR."api/linksapi.php";
    require_once LINKS_DIR."api/linksapi2.php";
    require_once LINKS_DIR."controller/linkscore.php";
    require_once LINKS_DIR."model/linksfiles.php";
    require_once LINKS_DIR."api-extensions/lfhtml.php";
    require_once LINKS_DIR."api-extensions/lfoid.php";
    require_once LINKS_DIR."api-extensions/lfdataset.php";
} else {
    // On server
    require_once LINKS_DIR."LF_PHP_lib.php";
    require_once LINKS_DIR."linkscorelib.php";
    require_once LINKS_DIR."linksapi.php";
    require_once LINKS_DIR."linksapi2.php";
    require_once LINKS_DIR."linkscore.php";
    require_once LINKS_DIR."linksfiles.php";
    require_once LINKS_DIR."../../upload/L0e3t3g2m_html.php";
    require_once LINKS_DIR."../../upload/K3j2Q1d10_oid.php";
    require_once LINKS_DIR."../../upload/F0GaoATAK_dataset.php";
}
// Create Links API v2
global $LF;
$LF = new LinksAPI();
LF_env( 'LINKS_noRedirect', true);
// Load UD project
require_once(  __DIR__.'/../ud.php');   
?>