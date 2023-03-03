/* ================================================================================================
 * UDAJAX class
 *  VIEW-MODEL class to handle exchange of data between VIEW-MODE client and server-side elements
 *
 */

//const { resolveInclude } = require('ejs');
//const ud = require('../ud-view-model/ud.js');

 

 class UDAJAX
 {
   ud;
   dom;
   server;
   service;
   serverRequestId = 0;
   serverFormName = "";
   pending = {};
   url;
   method;
   html; // HTML code of last response
   js ; // Javascript code of last reponse
   fetchAction = "AJAX_fetch";
   updateAction = ""; // just use post data
   refreshAction = "AJAX_show";
   useNodejs = false;
   PHPcookie = "";
 
    constructor( ud, server, service)
    {
      this.ud = ud;
      this.dom = ud.dom;
      this.server = server;
      this.service = service;
      this.serverFormName = ud.serverFormName;
      if ( typeof process == 'object') this.useNodejs = true;
    }  

    async serverRequestPromise ( uri, method="GET", postdata="", context={}, successCallback= null, errorCallback=null) {
        context.promise = new Promise( (resolve, reject) =>{
            //context.promise = this;
            context.resolve = resolve;
            context.reject = reject;
            this.serverRequest ( uri, method, postdata, context, successCallback, errorCallback);
        })
        return context.promise;
    }
    
    // Send a request to server and pre-process reponse before callback 
    serverRequest( uri, method="GET", postdata="", context={}, successCallback= null, errorCallback=null)
    {
        // 2DO add service unless already present and replace for name change
        if ( uri.charAt(0) == '/') this.url = this.server+uri;
        else this.url = this.server+'/'+uri;
        
        /* PATCH GCP*/
       if ( method == "POST") this.url = this.server;

        this.method = method;
        context.uri = uri;
        context.url = this.url;
        
        
        if (this.useNodejs)
        {
            // Running as node.js
            if ( method == "GET") this.nodejs_get( uri, context, successCallback, errorCallback);
            else if (  method == "POST" && TEST_serverSaving) this.nodejs_post( uri, postdata, context, successCallback, errorCallback);
            return;
        }            
        
        
        // Prepare request
      // if ( postdata && context.action=="reload") context.action="dummy";  // patch for reload bug
        var xhttp = new XMLHttpRequest();
        xhttp.udajax = this;
        xhttp.serverRequestId = ++this.serverRequestId;
        xhttp.callerContext = context;
        let popup = this.dom.element( "system-message-popup");
        xhttp.popup = (popup && !popup.classList.contains( 'show')) ? popup : false;
        // Idea 2225002 could use xhttp.onload
        xhttp.onreadystatechange = function() 
        {
            if (this.readyState == 4)
            {
                // Delete from pending
                if ( typeof this.udajax.pending[ this.serverRequestId] != "undefined" ) delete this.udajax.pending[ this.serverRequestId];
                // Process from reply
                if ( this.status == 200)
                {
                    // Successful reply 
                    debug( { level:5}, "server response (requestid, url, context, response)", 
                        this.serverRequestId, this.udajax.url, this.callerContext, this.responseText
                    );
                    debug( { level:2}, "Response server", this.serverRequestId);
                    this.udajax.splitResponseIntoHTMLandJS( this.responseText);
                    if ( successCallback) successCallback( this.callerContext, this.udajax.html, this.udajax.js); // Only 1 at a time 
                    // Use default callbacks
                    else if ( this.udajax.method == "POST") this.udajax.postSuccess( this.callerContext, this.udajax.html, this.udajax.js);
                    else if ( this.udajax.method == "GET") this.udajax.getSuccess( this.callerContext, this.udajax.html, this.udajax.js);
                    /* Promise handled by GET success
                    if ( this.callerContext.promise) {
                        console.log( "AJAX promise resolve");
                        Promise.resolve( this.callerContext.promise);
                    }
                    */
                    /*
                    if Inform matamo about ajax refresh 
                    _paq.push(['setCustomUrl', '/' + window.location.hash.substr(1)]);
                    _paq.push(['setDocumentTitle', 'My New Title']);
                    _paq.push(['trackPageView']);
                    */
               }
                else                
                {
                    // Error 
                    console.log( "AJAX error");
                    debug( { level:2}, "can't reach server with ", this.udajax.url);
                    if ( errorCallback) errorCallback( this.callerContext, this.status);
                    if ( this.callerContext.promise) {
                        Promise.reject( this.callerContext.promise);
                    }
                }
                // 2DO only if set by me
                if ( this.popup) {
                    this.popup.classList.remove( 'show');
                }
            }
        }    
        xhttp.open( this.method, this.url, true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        if ( typeof postdata == "object") {
            // Convert object data to query string
            let formData = ""
            for ( var key in postdata) formData += key+"="+postdata[ key]+"&";
            formData = formData.substring( 0, formData.length-2);
            postdata = formData;
        }
        xhttp.send( postdata);
        
        this.pending[ this.serverRequestId] = { method: this.method, url: this.url};
        // Show that request has been sent
        if ( context.action == "fill zone" && context.zone) {            
            if ( popup  && !popup.classList.contains( 'show')) {
                popup.innerHTML = '<div style="text-align:center"><img src="/upload/3VUvtUCVi_processing.gif"></div>';
                popup.classList.add( 'show');
            }
        }  
        /* Idea 2225002
        {
            let promise = new Promise( (keep, break) => {
                keep( context);
            }
            xhttp.callContext.promise = promise;
            retrun promise;
        }
        
        */
        debug( { level:2}, "request sent to server", xhttp.serverRequestId);
    } // UDAJAX.serverRequest()
    
    isBusy()
    {
        return Object.keys( this.pending).length;
    }
    
    splitResponseIntoHTMLandJS( response)
    {
        this.html = "";
        this.js = "";
          let p = response.indexOf('window.onloadapp');
          let tagLength = 16;
          if ( p == -1) {
            p = response.indexOf('window.onload');
            tagLength = 13;             
          }
          if (p > 50)
          {
            var str = '<script type="text/javascript" lang="javascript">';
            var p1 = p - str.length - 3;
            this.html = response.substr(0, p1);
            p1 = p + tagLength+3+9+2;
            var p2 = response.substr(p1).indexOf('}');
            this.js = response.substr(p1, p2);
            this.js = this.js.replace(/LFJ_openAcco/g, '{');
            this.js = this.js.replace(/LFJ_closeAcco/g, '}'); 
            // 2DO security check on js
          }
          else this.html = response;
          return { html:this.html, js:this.js}
    } // UDAJAX.splitReponseIntoHTMLandJS()
         
    // Fill context.element with content and run JS
    postSuccess( context, content, js)
    {
        if ( context.action == "ignore") return;
        else if ( context.action == "remove") {
            // Get element's view before deleting
            let view = this.dom.getView( context.element);            
            context.element.remove();
            // Update paging via hook
            if ( view) $$$.paginate( view);
        } else if (context.action == "fill zone") {
            document.getElementById( context.zone).innerHTML = content;
        } else if (context.action == "set json") {
            let holder = this.dom.element( context.holder);
            let json = this.dom.udjson.parse( content);
            if ( json) {
                holder.textContent = this.dom.udjson.valueByPath( holder.textContent, context.jsonPath, json);
            }
        } else if (context.action == "reload") {
            location.reload( true);
        } else if (context.action == "close") {
            this.dom.element( 'document').innerHTML = content;            
            window.close();
        } else if ( content.trim().indexOf( '{') == 0) {
            // update element if JSON response
            // TEMP trim to workaround server bug leading spaces in fetch
            //if ( this.action == "update" ||  this.action == "insert")
            context.ud.updateElement( context.element, content.trim()); 
        } else if ( content.indexOf( '<') == 0 || context.action == "refresh") {
            context.ud.topElement.innerHTML = content;
        }
        // Handle promises
        if ( context.promise) {
            if ( context.resolve) context.resolve( context);
            else if ( context.promise.then)  context.promise.then( context);
        }
        if ( context.setCursor) 
        {
            // 2DO use an UDE event or similar
            var cursor = context.ud.ude.dom.cursor;
            setTimeout( function () {cursor.restore()}, 100);
            debug( {level:4}, "Cursor restore set for 100ms");
        }
        // Run JS
        if ( js) debug( {level:3}, "js to run", js);
        if ( js) doOnload( js);
    } //UDAJAX_postSuccess();
    
    // Fill element with content and run JS
    getSuccess( context, content, js)
    {
        if ( context.action == "ignore") return;
        let action = context.action;
        if ( action == "update" ||  action== "insert") {
            context.ud.updateElement( context.element, content); 
        } else if ( action == "fill zone") {
            debug( {level:2}, "fill zone", context);
            document.getElementById( context.zone).innerHTML = content;
            // Update History dynamically if zone is document
            if ( context.zone == "document") { this.ud.addToHistory( context.url);}
        } else if (context.action == "set json") {
            let holder = this.dom.element( context.holder);
            let json = this.dom.udjson.parse( content);
            if ( json) {
                holder.textContent = this.dom.udjson.valueByPath( holder.textContent, context.jsonPath, json);
            }
        } else if ( action == "refresh") {   
            context.element.innerHTML = content;
            // Update History dynamically
            this.ud.addToHistory( context.url);
        } else if ( action == "reload") { 
            // Load received document ( single page mode and testing)
            document.open("text/html", "replace");
            document.write( content); 
            document.close();            
            // Reset topElement pointers
            ud.topElement = ud.dom.topElement = ud.dom.element( "document");
        } else if ( action == "compositeUpdate") {
            // Update Composite element's data
            context.element.textContent = content;
            // Initialise saveable element
            let saveable = this.dom.getSaveableParent( element);
            this.ud.ude.initialiseElement( saveable.id);
            // Check if element needs post update processing
            // if ( this.dom.attr( element, 'ud_display')) eval( this.dom.attr( element, 'ud_display'));                             
            
        }
        // Handle promises
        if ( context.promise) {
            if ( context.resolve) context.resolve( context);
            else if ( context.promise.then)  context.promise.then( context); // 220913 - connector still uses this
        }
        //if ( context.promise) Promise.resolve( context.promise);
        if ( js) debug( {level:3}, "js to run", js);
        if (js) doOnload( js);
    } //UDAJAX_getSuccess();
    
    // Update a zone with content returned by an AJAX call
    updateZone( oidAndAction, zone)
    {
        var url = '/'+this.service+'/'+oidAndAction;
        var context = { zone: zone, action:"fill zone", element:null, ud:this.ud};
        this.serverRequest( url, "GET", "", context);         
    } // UDAJAX.updateZone()
 
    // Updata an UD element - recheck split betwwen universaldoc.js and this fct
    postElement (element, oid, action, stype, successCallback=null, errorCallback=null)
    {
        // Get input_oid
        let input_oid = oid;
        if ( action == "refresh" || action == "insert") input_oid = this.dom.attr( element, 'ud_oid');
        // Force UD on request OID
        if (oid.indexOf( 'UD|') == -1) oid = oid.replace( 'CD', 'UD|3|CD');	 
        let setCursor = false;
        // Get fields to save
        let fieldsToSave = this.dom.attr( element, 'ud_fields');        
        // Build post data
        let postData  = "form="+this.serverFormName+"&input_oid="+input_oid;
        // Name if changed
        if ( this.dom.attr( element, 'ud_saveId').toLowerCase() == "yes") {
            postData += "&nname=" + element.id;
            this.dom.attr( element, 'ud_saveId', '__CLEAR__');
        }
        // Label = DOM name
        if ( fieldsToSave == "" || fieldsToSave.indexOf( 'nlabel') > -1) {
            let label = this.dom.attr( element, 'name');
           /* PATCH - 2DO need a prePost action attribute to be handled in ud.js 
              will need to set UD_saveId too */
            if ( element.id == API.getConstant( UD_wellKnownElements, 'docNameHolder')) { 
                console.error( 'DEPRECATED CODE USED udajax l 266');
                // Manage view doc name element : extract label field and rebuild name
                let nameParts = this.dom.children( element);
                if ( nameParts.length > 0) label = nameParts[0].textContent; 
                else label = element.textContent;
                // Rebuild DB nname field using nname info stored in extra 
                let shortLabel = label.replace( / /g, '').substring( 0, 8);
                let system = this.dom.udjson.parse( this.dom.attr( element, 'ud_extra'));
                let dbName = system[ 'name'] + "_" + shortLabel;
                if ( dbName && shortLabel) { postData += "&nname=" + dbName;}
            } /* end PATCH */
            if ( label) { postData += "&nlabel=" + label;}
        } 
        // Content = all content of node except binded elements with work classes removed on all elements
        let saveHTML = "";
        let content = "";
        if ( fieldsToSave == "" || fieldsToSave.indexOf( 'tcontent') > -1) {
            let contentElements = Array.prototype.slice.call( element.childNodes);
            if ( contentElements.length) {
                // Saved element contains HTML elements
                // Detect if 1st child is a JSON-encoded object ie JSON100 syntax
                let json = null;
                let first = contentElements[0].textContent;
                if ( first.indexOf( '{') == 0) json = API.json.parse( first); // API.json                
                if ( json && JSONvalue( json, "meta")) {
                    // JSON100 encoded object
                    content = contentElements[0].textContent; // Single JSON
                    if ( action != "remove") {
                        saveHTML = this.dom.udjson.valueByPath( json, "meta/zone");
                    }
                } else {
                    if ( this.useNodejs) {
                        // Nodejs robot or testing - no need to remove temporary clsses
                        content = element.innerHTML;
                    } else {
                        // Browser - remove temporary classes in child elements before saving
                        for ( let contenti=0; contenti < contentElements.length; contenti++) {
                            let contentChild = contentElements[ contenti];
                            if ( contentChild.nodeType == Node.TEXT_NODE) content += contentChild.textContent;
                            else if ( contentChild.nodeType == Node.ELEMENT_NODE && !this.dom.attr( contentChild, 'ude_bind')) {
                                // Only 1 level - 2DO improve with a function HTMLwithoutTempClasses                               
                                // Save complete HTML of children
                                let childClassesToKeep = this.dom.keepPermanentClasses( contentChild.className);     
                                let childContent = contentChild.outerHTML;
                                if ( childClassesToKeep != contentChild.className) {
                                    childContent = childContent.replace( 'class="'+contentChild.className+'"', 'class="'+childClassesToKeep+'"');
                                }
                                content += childContent;
                            }
                        }
                    }
                }
            }
            if ( this.dom.attr( element, "ud_type") == "html") content = content.replace( /&amp;/g, "&");
            // 2DO if too big or other conditions
            //     use updates div 
            if ( [ 4, 5, 51].indexOf( stype) > -1) content = "";
            else if ( this.dom.attr( element, 'ud_content')) content = doOnsave( this.dom.attr( element, 'ud_content'));
            // Hack 220625 toni no <script> .. </script> in elements
            // content = stripScripts( content, element);
            content = htmlEntities2( content, element);
            postData += "&tcontent="+encodeURIComponent(content);
        }
        // Save HTML field
        if ( saveHTML) {
           let saveHTMLel = this.dom.element( saveHTML);
           let thtml = ( saveHTMLel) ? saveHTMLel.innerHTML : "";
           if ( thtml && thtml.length < 65000) { postData += "&thtml=" + encodeURIComponent( thtml);}
        } 
        if (action == "remove") postData += "&iaccess=0&tlabel=owns";
        else if (action == "insert")
        {
            // New element - include name and type
            postData += "&nname="+this.dom.attr( element, 'id');
            //action = "refresh";
        }
        // UD type        
        if ( fieldsToSave == "" || fieldsToSave.indexOf( 'stype') > -1)  postData += "&stype="+stype;           
        // CSS class
        if ( fieldsToSave == "" || fieldsToSave.indexOf( 'nstyle') > -1)
        {
            postData += "&nstyle="+this.dom.keepPermanentClasses( element.className, true);
        }    
        // taccessRequest field
        postData += "&taccessRequest="+this.ud.userId;
        
        // textra
        if ( fieldsToSave == "" || fieldsToSave.indexOf( 'textra') > -1)
        {
            // Provide server with some DOM attributes for page management
            var attr = 
            { 
                width: element.scrollWidth, // +this.dom.attr( element, 'marginLeft')+this.dom.attr( element, 'marginRight'), /*offsetWidth*/
                height: element.scrollHeight, //+this.dom.attr( element, 'marginTop')+this.dom.attr( element, 'marginBottom'), 
                offsetLeft: element.offsetLeft, 
                offsetTop: element.offsetTop,
                marginTop : Math.round( parseFloat( this.dom.attr( element, "computed_marginTop"))),
                marginBottom : Math.round( parseFloat( this.dom.attr( element, "computed_marginBottom"))),
                //offsetParent: element.offsetParent.id,
                system: this.dom.udjson.parse( this.dom.attr( element, 'ud_extra'))
            };
            if ( fieldsToSave.indexOf( 'textra') > -1 && fieldsToSave.indexOf( 'tcontent') == -1) {
                let jsonData = this.dom.udjson.parse( element.getElementsByTagName( 'div')[0].textContent);
                if ( jsonData.meta) {
                    // JSON100 format
                    attr.system = jsonData.data.value;
                } else { attr.system = jsonData;}
            }
           postData += "&textra="+JSON.stringify(attr);
        }    
        postData += "&dmodified=auto";
     
        // Detect wether cursor is to be set
        if ( action=="refresh" /*|| action=="remove" || action == "insert"*/ ) setCursor = true;
     
        // Prepare AJAX call with result processing
        let call = '';
        if ( this.updateAction) call +='/'+this.service + '/' + oid + '/' + this.updateAction+"/";  
        if (action == "refresh" /*|| action == "insert"*/) call = '/'+this.service+'/'+oid+"/"+this.refreshAction+"/";   
        // Prepare context for response handling
        var context = {element:element, action:action, setCursor:setCursor, ud:this.ud};
        // send request to server
        this.serverRequest( call, "POST", postData, context, null); 
  } // UDAJAX.postElement()
  
    // Post 1st from found in containerId and refresh containerId with response and run any JS
    postForm ( containerId, uri, prompt="")
    {
        // Find containerId
        var container = this.dom.element( containerId);
        if (!container) return debug( {level:2, return:null}, "can't find ", containerId);
        // Find form
        let formElement = "";
        if ( container.tagName.toLowerCase() == "form") formElement = container;
        else formElement = this.dom.element( 'form', container);
        if (!formElement) return debug( {level:2, return:null}, "no form in", container);      
        // Build postdata
        //var postdata = new FormData(formElement);
        var postdata = "";
        var formElements = formElement.elements;
        for (var i=0; i < formElements.length;i++) postdata += "&"+formElements[i].name+"="+formElements[i].value;
        postdata = postdata.substr(1);
        var call = uri;
        // Prepare context for response handling
        var context = {zone:containerId, element: null, action:"fill zone", setCursor:false, ud:this.ud};
        if ( prompt == "" || confirm(prompt)) this.serverRequest( call, "POST", postdata, context);
        return false; // so onsubmit does nothing
    } // UDAJAX.postForm()
    
    
    // Set up or remove a link
    link( from, to, label, access)
    {
      // Use a link manager AJAX service  
    } // UDAJAX.link()
  
   /*
    * NODEJS ENVIRONMENT
    */
    // GET request when running under nodejs 
    nodejs_get( uri, context={}, successCallback= null, errorCallback=null)
    {
        // Get request class
        var request = window.request;
        // Build URL
        this.server = "http://dev.rfolks.com";
        if ( uri.charAt(0) == '/') this.url = this.server+uri;
        else this.url = this.server+'/'+uri;                
        // Set cookie
        var cookieJar = request.jar();
        if ( this.PHPcookie)
            cookieJar.setCookie( { "key":"PHPSESSID", "value":this.PHPcookie}, "http://dev.rfolks.com"); // this.server
        this.pending[ ++this.serverRequestId] = { method: "GET", url: this.url, context:context};         
        // Make the request
        let r = request(
          this.url,          
          { jar: true},
          ( error, response, body) => {
                process.ud = ud;
                let me = ud.udajax;
                let replyContext = null;
                // console.log( "GET RESPONSE", body, error);
                // process.exit(0);
                // Clear request from pending
                if ( typeof me.pending[ me.serverRequestId] != "undefined" ) {
                    replyContext = me.pending[ me.serverRequestId].context;
                    delete me.pending[ me.serverRequestId];
                }
                
                // Patch for RegisterBlock() 
                let html = body.replace( /RegisterBlock/g, "console.log");
                //Get PHPSESSID cookie from response
                let responseCookies = response.headers['set-cookie'];
                if ( responseCookies) {    
                    for( let cookiei=0; cookiei < responseCookies.length; cookiei++)
                    {
                        let cookie = responseCookies[ cookiei];
                        cookie = cookie.split(';');
                        // console.log( "cookie", cookie);
                        if ( cookie[0] == "PHPSESSID") this.PHPcookie = cookie[1];
                    }
                } 
                me.nodejs_processResponse( body, 'get', context, successCallback); 
                if ( context.promise) {
                    context.resolve();
                }
                /*                
                if ( me.url.indexOf('AJAX_') > -1) {
                    // AJAX call
                    // console.log( "AJAX", this.url);                    
                    me.splitResponseIntoHTMLandJS( html);
                    // console.log( ud.udajax.html);
                    if ( successCallback) successCallback( context, me.html, me.js); // Only 1 at a time 
                    // Use default callback
                    else me.getSuccess( context, me.html, me.js);
                }
                else
                {
                    // Non AJAX call
                    let p1 = html.indexOf('<body');
                    let p2 = html.indexOf('window.onloadapp');
                    // console.log( "Non AJAX", p2);
                    if (p2 > 50)
                    {
                        var str = '<script type="text/javascript" lang="javascript">';
                        let p3 = p2 - str.length - 3;
                        ud.udajax.html = html.substr(0, p3);                    
                        document.body.innerHTML = ud.udajax.html.substring( p1+8, p3-1);
                        // console.log( p1, ud.udajax.html.substr( 0, 5000));   
                        let p4 = p2 + 16+3+9+2;
                        let jspart = html.substring( p4);
                        let p5 = jspart.indexOf('</script>');
                        // console.log( "abc", jspart.substr( 0, 10), jspart.length, p5); 
                        me.js = jspart.substring(0, p5-2);
                        //console.log( jspart);
                    }
                    else document.body.innerHTML = html.substring( p1+6, html.indexOf('</body>')-1);

                }
                ud.topElement = ud.dom.topElement = ud.dom.element( "document");
                if ( me.js) { 
                    // var requirejs = require( '../../r.js');
                    let p1 = me.js.indexOf( 'require(');
                    const reqMod = require( '../tests/testenv.js');
                    if ( p1) {                                              
                        me.js = me.js.replace( /require\(/g, 'reqMod.require(');
                        // process.exit( 0);
                    }
                    let js = "try {"+me.js+"} catch( e) {\n";
                    js += "let lines = me.js.split( \"\\n\");\n";
                    js += 'console.log( e, e.message);'+"\n";
                    js += '}';
                    //console.log( js);
                    eval( js);
                    me.js = "";
                   // setTimeout( function() { eval( me.js);}, 700);
                }  
                */                
            }
        );
    } //udajax.nodejs_get()

// ( uri, method="GET", postdata="", context={}, successCallback= null, errorCallback=null)
   /*
    * POST request on node.js
    */     
    nodejs_post( uri, postdata, context={}, successCallback= null, errorCallback=null)
    {
        // Get request class
        let request = window.request;
        // Build URL
        this.server = "http://dev.rfolks.com"; // Temp
        if ( uri.charAt(0) == '/') this.url = this.server+uri;
        else this.url = this.server+'/'+uri;
        let dataObj = postdata;
        if ( typeof postdata != "object") {
            // Convert postdata to object
            dataObj = {};
            let data = postdata.split( '&');
            for (let datai=0; datai<data.length; datai++) 
            {
                let keyValue = data[ datai].split('=');
                dataObj[ keyValue[0]] = decodeURIComponent( keyValue[1]); 
            }            
        }
        // Set cookie
        let cookieJar = request.jar();
        if ( this.PHPcookie)
            cookieJar.setCookie( { "key":"PHPSESSID", "value":this.PHPcookie}, "http://dev.rfolks.com"); // this.server
        this.pending[ ++this.serverRequestId] = { method: "POST", url: this.url, context:context};    
       /* IDEA To simulate saving withiut actually posting to server
        *  if !TEST_serverSaving setTimeout to simulate response  
        * JSON response has { nname, stype, nstyle, tcontent, textra, modifiableBy, you, result, users, newElements}
        */
        // Make the request
        // console.log( "here", this.url, dataObj, postdata);  //             { jar: true, myData:"mydata"},      
        request.post({
            url:this.url,
            //json : true,
            // method: 'POST',           
            form: dataObj,
            // body: postdata,
            // headers: { "content-type": "application/text"},
            jar: true
        },            
        (error, response, body) => {
            // function ( error, response, body) {
                // Response
                let me = ud.udajax;
                let replyContext = null;
                if ( typeof me.pending[ me.serverRequestId] != "undefined" ) {
                    replyContext = me.pending[ me.serverRequestId].context;
                    delete me.pending[ me.serverRequestId];                   
                }                
                if (error) {
                    console.error( "nodejs_post", error)
                    return
                }
                //Get PHPSESSID cookie from response
                let responseCookies = response.headers['set-cookie'];
                if ( responseCookies) {    
                    for( let cookiei=0; cookiei < responseCookies.length; cookiei++)
                    {
                        let cookie = responseCookies[ cookiei];
                        cookie = cookie.split(';');
                        // console.log( "cookie", cookie);
                        if ( cookie[0] == "PHPSESSID") this.PHPcookie = cookie[1];
                    }
                } 

                // Split HTML and JS
                // console.log( response);
                // console.log( "RESPONSE NODEJS POST", body, me.url, dataObj); process.exit(0);
                me.nodejs_processResponse( body, 'post', replyContext, successCallback);
                // me.splitResponseIntoHTMLandJS( body);
                // console.log( me.js); process.exit(0);
                // if ( successCallback) successCallback( context, me.html, me.js);
                // else me.postSuccess( context, me.html, me.js);                
                // console.log(`statusCode: ${res.statusCode}`)
                // console.log(body)
            }
        )        

    } // UDAJAX.nodejs_post()
    
    nodejs_processResponse( html, method, context, successCallback = null) {
        if ( this.url.indexOf('AJAX_') > -1) {
            // AJAX call
            // console.log( "AJAX", this.url);                    
            this.splitResponseIntoHTMLandJS( html);
            if ( successCallback) successCallback( context, this.html, this.js); // Only 1 at a tithis 
            // Use default callback
            else {
                if ( method = "get") this.getSuccess( context, this.html, this.js);
                else if ( method == "post") this.postSuccess( context, this.html, this.js);
            }
        }
        else
        {
            // Non AJAX call
            let p1 = html.indexOf('<div id="main"'); // body
            let p1b = html.indexOf('</div> <!-- main -->');
            let p2 = html.indexOf('window.onloadapp');
            // console.log( "Non AJAX", p2);
            if (p2 > 50)
            {
                var str = '<script type="text/javascript" lang="javascript">';
                let p3 = p2 - str.length - 3;
                this.html = html.substr(0, p3);    
                // console.log( p1, p3, this.dom.element( 'document'), document.body);
                let main = document.getElementById( 'main');
                main.innerHTML = html.substring( p1+6, p1b);             
                // document.body.innerHTML = html.substring( p1+8, p3-1)+"</body>";
                // document = domjs.window.document;
                // console.log( "2ND", document.getElementById( 'document'), document.body);
                // console.log( p1, ud.udajax.html.substr( 0, 5000));   
                let p4 = p2 + 16+3+9+2;
                let jspart = html.substring( p4);
                let p5 = jspart.indexOf('</script>');
                // console.log( "abc", jspart.substr( 0, 10), jspart.length, p5); 
                this.js = jspart.substring(0, p5-2);
                //console.log( jspart);
            }
            else document.body.innerHTML = html.substring( p1+6, html.indexOf('</body>')-1);
            ud.topElement = ud.dom.topElement = document.getElementById( "document");
            if ( this.js) { 
                // var requirejs = require( '../../r.js');
                let p1 = this.js.indexOf( 'require(');
                const reqMod = require( '../tests/testenv.js');              
                if ( p1) {                                              
                    this.js = this.js.replace( /require\(/g, 'reqMod.require(');
                    // process.exit( 0);
                }
                let js = "try {"+this.js+"} catch( e) {\n";
                js += "let lines = this.js.split( \"\\n\");\n";
                js += 'console.log( e, e.message);'+"\n";
                js += '}';
                eval( js);
                this.js = "";
               // setTimeout( function() { eval( me.js);}, 700);
            }                 
        }
    } // UDAJAX.nodejs_processResponse()
    
} // class UDAJAX
 
if ( typeof process == 'object')
{
    // Running under node.js
    module.exports = { UDAJAX: UDAJAX};  
    // Load https
  // 2DO use request      npm install request@2.81.0   latest 2.88.2
    //window.UDAJAX = UDAJAX;    
    if ( typeof global.JSDOM == "undefined" && typeof window == "undefined")
    {
        console.log( 'Syntax udajax.js:OK');
        console.log( "Setup test environment");
        var envMod = require( '../tests/testenv.js');
        envMod.load();
        window.request = require( 'request');
        async function testGet( url, ud) {
            let test = "Async get test" 
            await ud.udajax.serverRequestPromise( url, 'GET', '', { ud:ud, action: "reload"});
            if(  ud.dom.value( "B01000000B0000000M...textContent") == "Text") console.log( test+" : OK");
            else console.log( test + " : KO");//, ud.topElement.innerHTML);
            console.log( "Test completed");
            process.exit(0);
        }
        // Setup our UniversalDoc object
        ud = new UniversalDoc( 'document', true, 133);
        console.log( 'Start of test program');
        let url = 'webdesk/UniversalDocElement--21-725-21--UD|3|NO|OIDLENGTH|CD|5/show/tusername|demo/tpassword|demo/';
        testGet( url, ud);
        // testGet.then() => nextTest();
        
        //process.exit(0);
    }    
    else
    {
        window.request = require( 'request');

    }
} // End of test routine
