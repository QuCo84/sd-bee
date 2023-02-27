

"SD bee" (hereafter the software) is a software program for delivering web pages and web apps for designing, automating and deploying processes required for digitalisation.

THE SD BEE README FILE

This file provides an entry point to setting up SD bee after downloading the SD bee package available on GitHub at the address : https://github.com/QuCo84/sd-bee

THE SOFTWARE

SD bee makes it easy to design and automate processes required for digitalisation and then to execute tasks based on these processes.

The SD bee package provides a minimal PHP server program for setting up SD bee. It uses resources available on the sdb-bee.com website.
It has a modular design so it can be adapted to diffierent cloud environments.

For modifiying Javascript code used on the client side, please see the "SD bee client" project on GitHub.
For configuring and extending Services available to SD bee apps, please the "SD bee services"project on GitHub.

For all enquires and assistance, please fill in the contact form at www.sd-bee.com (padlock or Start button)

LICENSE

The software is published under the GNU GENERAL PUBLIC LICENSE Version 3.
see LICENSE.md

ARCHITECTURE

The sdbee-index.php handles all requests and dispatches them to files in the endpoints directory according to the request received after setting up data access in line with parameters provided by the sdbee-config.jsonc file. Endpoints are :
    Full page endpoints (GET)
        / home  Display home page if not connected or main task directory if connected    
        do     name  Execute a task
        edit   name    Edit a process, app or app model
    Get AJAX calls
        collection List tasks in a container
        fetch-element Return an element
        changes Return a list of elements that have been changed
        edit Display a task or model for editing
        marketplace Display a selection of models
    Post AJAX calls
        modify-element (create, update, delete)
        service-gateway Handle a call to a local or remote service
        add-doc Add a new document (task, model)
        add-user Add a new user
        delete-user
        delete-doc
        

All endpoint scripts access data via the sdbee-storage class and the sdbee-access setup by sdbee-index.  These in turn use classes defined in the storage-connectors and access-connectors to adapt to different environments. 

Client-side programs and resources are initially setup to come from a public CDN.

Payable services are configured to use the sd-bee.com service where an account must be created and credits bought.

For a minimal setup on GCP you will need :
- a Google account with API access
- a Google Cloud Storage bucket

For a minimal setup on a web server or trial setup on a PC, you will need :
- an HTTP server setup
- a directory where to place files


CONFIGURATION

The sdbee-config.json defines a set of configuration parameters :

public - Where to find public resources
   home
   models
   editor
   ...
admin - Where to find access database for controling 
  storage  - URL where access database is stored
  db type  - Type of DB used for access database
  dbName   - Name of the access database
private - where to find private user files protected by token 
  pattern
  crypt


  
