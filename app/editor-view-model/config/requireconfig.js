require.config({
    baseUrl: 'https://www.sd-bee.com/upload/smartdoc',
    paths: {
        /* for compatability with OVH setup, dayjs & moment copied to vendor after installation with npm. moment-with-locales.min copied up to mai dir */
        'moment': "https://www.sd-bee.com/upload/smartdoc/node_modules/moment.js/moment-with-locales.min",
        /*
        'node_modules/dayjs/dayjs.min.js' : 'https://www.sd-bee.com/upload/smartdoc/node_modules/dayjs/dayjs.min.js',
        'node_modules/dayjs/plugin/relativeTime.js' : 'https://www.sd-bee.com/upload/smartdoc/node_modules/dayjs/plugin/relativeTime.js', 
        'node_modules/dayjs/plugin/customParseFormat.js' : 'https://www.sd-bee.com/upload/smartdoc/node_modules/dayjs/plugin/customParseFormat.js',
        'node_modules/dayjs/plugin/weekOfYear.js' : 'https://www.sd-bee.com/upload/smartdoc/node_modules/dayjs/plugin/weekOfYear.js',
        'node_modules/dayjs/locale/fr.js' : 'https://www.sd-bee.com/upload/smartdoc/node_modules/dayjs/locale/fr.js',
        */
        'ejs' : "https://www.sd-bee.com/upload/smartdoc/node_modules/ejs/ejs.min"
    },
    waitSeconds : 25
});

/*
function requireMany () {
    return Array.prototype.slice.call(arguments).map(function (value) {
        try {
            return require(value)
        }
        catch (event) {
            return console.log(event)
        }
    })
}
*/
function app_load_do() {
    let ud = window.ud;
    // ud.ude.calc.redoDependencies();
    leftColumn = new Zone('leftColumn', 'rightColumn'); 
    rightColumn = new Zone('rightColumn', 'leftColumn');
    if ( typeof window.onloadapp != "undefined") window.onloadapp();
}
function app_load( user, path) {
    // window.UDincludePath = ( path.indexOf( 'smartdoc_prod') > -1) ? "/upload/smartdoc_prod/" : "/upload/smartdoc/";
    let pathHolder = document.getElementById( 'UD_rootPath');
    if ( pathHolder) window.UDincludePath = pathHolder.textContent;
    else window.UDincludePath = ( path.indexOf( 'smartdoc_prod') > -1) ? "/upload/smartdoc_prod/" : "/upload/smartdoc/";
    let versionHolder = document.getElementById( 'UD_version');
    if ( versionHolder) version = versionHolder.textContent; //"-v-"+
    else version = ( path.indexOf( 'smartdoc_prod') > -1) ? "-v-0-1-6" : "-v-0-1"; 
    window.UD_SERVER = ( path.indexOf( 'smartdoc_prod') > -1) ? "https://www.sd-bee.com" : "http://dev.rfolks.com";
    window.UD_SERVICE = "webdesk"; 
    // 2DO look for cloudshell in $_SERVER urm
    $url = window.location.href;
    $urlParts = $url.split( '/');
    window.UD_SERVER = $urlParts[ 0]+"//"+$urlParts[ 2]; //"https://ud-server-372110.oa.r.appspot.com"; //https://8080-cs-482142111769-default.cs-europe-west1-iuzs.cloudshell.dev/";
    window.UD_SERVICE = "sdbee"; 
    window.version = version; 
    window.global = window;
    //require.config( { baseUrl :( path.indexOf( 'smartdoc_prod') > -1) ?  '/upload/smartdoc_prod': 'https://www.sd-bee.com/upload/smartdoc'});
    //require.config( { baseUrl: window.UDincludePath});
    require.onError = function(e) {
        console.log( e.stack);
        alert( "Loading error Please reload page " + e.message);
    };
    // !!! Uodate standard modules in udconstants.php when changing loaded modules under modules/    / *'ude-view/udecalc_css'+version,* /
    /*
    node_modules/dayjs/dayjs.min', 'node_modules/dayjs/plugin/relativeTime', 'node_modules/dayjs/plugin/customParseFormat',
        //'node_modules/dayjs/locale/fr',
        'node_modules/dayjs/plugin/weekOfYear',
    */
    modules = [
        'node_modules/dayjs/dayjs.min', 'node_modules/dayjs/plugin/plugin/relativeTime', 
        'node_modules/dayjs/plugin/customParseFormat', 'node_modules/dayjs/plugin/weekOfYear', //'node_modules/dayjs/locale/fr',
        'moment', // has to be here until we configure chart.js to use dayjs or don't add v string
        //'config/udregister'+version,
        'https://www.sd-bee.com/upload/smartdoc/require-version/udregister-v-0-3-1.js',
        //'https://www.sd-bee.com/upload/smartdoc/browser/udajax-v-0-3-1.js',
        'https://www.sd-bee.com/upload/ude-min-v-0-3-1.js',
        '/sd-bee/app/editor-view/udajax.js',
        /*
        'debug/debug'+version,
        'ud-view-model/udconstants'+version, 'ud-view-model/udregister'+version,
        'ud-utilities/udjson'+version, 
        'browser/domcursor'+version, 'browser/domvalue'+version, 'browser/dom'+version, 'browser/udajax'+version,
        'ude-view/udeconstants'+version, 'ude-view/udecalc'+version, 
        'ude-view/udemenu'+version, 'ude-view/udelayout'+version, 
        'ude-view/udeclickhandler'+version, 'ude-view/ude'+version, 
        'modules/editors/udetext'+version,
        'api/apiset1'+version, 'api/apiset2'+version, 'api/udapi'+version, 'api/udmodule'+version, 'ud-utilities/udutilities'+version,
        'ud-utilities/udresources'+version,'ud-utilities/udcontent'+version,
        'modules/tools/zone'+version,
        'ud-view-model/ud'+version,
        */
    ];
    let led = document.getElementById( 'STATUS_busy');
    if (led) {
        led.setAttribute( 'stroke', "pink");    
        led.setAttribute( 'fill', "pink");          
    }    
    let footer = document.getElementById( 'footer');
    if ( footer) { footer.classList.add( 'hidden');}
    if ( typeof requirejs_app != "undefined") app_load_do();
    else {
        requirejs_app = "loading";            
        require( modules, function() {
            let modeHolder = document.getElementById( 'UD_mode');
            if ( modeHolder) {
                let mode = modeHolder.textContent;
                let langHolder = document.getElementById( 'UD_lang');
                window.lang = ( langHolder) ? langHolder.textContent : "FR";
                let editable = true;
                if ( mode == "model" || mode == "public" || mode == "display") editable = false;
                window.ud = new UniversalDoc( 'document', editable, user);
                app_load_do(); 
            }
            // POST Test  &stype=10&nstyle=standard&taccessRequest=0000M&dmodified=auto &input_oid=_FILE_UniversalDocElement-A0000002NHSEB0000M_Repageaf-_FILE_UniversalDocElement-B01000001M0000000M--21-0-21-7--AL|7&tcontent=We%20need%20lots%20of%20textss
            //ud.udajax.serverRequest( "", "POST", "form=INPUT_UDE_FETCH&stype=10");
            let debugLevelHolder = document.getElementById( 'UD_debug');
            if ( debugLevelHolder) {
                DEBUG_level = 10 - ( parseInt( debugLevelHolder.textContent) % 10);
            }
            // window.onload();
            requirejs_app = "loaded";
            debug( {level:5}, "Finished require");
        });
    }
}

