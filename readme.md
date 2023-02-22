

"SD bee server" (hereafter the software) is a PHP program for delivering web pages and web apps for designing and automating processes required for digitalisation and  to execute tasks based on these processes.

THE SD BEE README FILE

This file provides an entry point to setting up SD bee after downloading the SD bee package available on GitHub at the address :

THE SOFTWARE

SD bee makes it easy to design and automate processes required for digitalisation and then to execute tasks based on these processes.

The SD bee package provides a minimal server program for setting up SD bee.
It has a modular design so it can be adapted to diffierent cloud environments. 


LICENCE

The software is published under the APACHE Open source licence.
see sdbee-licence.txt

ARCHITECTURE

The sdbee-index.php handles all requests and dispatches them to files in the endpoints directory according to the request received after setting up data access in line with parameters provided by the sdbee-config.jsonc file. Endpoints are :
    Full page endpoints (GET)
        / home  Display home page if not connected or main task directory if connected    
        do     name  Execute a task
        edit   name    Edit a process, app or app model
    Get AJAX calls
        collection List tasks in a container
        fetch Return an element
        getChangedElements Return a list of elements that have been changed
    Post AJAX calls
        fetch Modify an element DEPRECATED
        modifyElement (create, update, delete)
        modifyTask
        service Handle a service call (post)
        addUser Add a user
        addTask Add a task
        addModel Add a process, app or app model
        deleteUser
        deleteTask
        deleteModel
        

All endpoint scripts access data via the sdbee-storage class and the sdbee-access setup by sdbee-index.  These in turn use classes defined in the storage-connectors and access-connectors to adapt to different environments. 

Client-side programs and resources are initially setup to come from a public CDN.

Payable services are configured to use the sd-bee.com service where an account must be created and credits bought.

For a minimal setup on GCP you will need :
- a Google account with API access
- a Google Cloud Storage bucket


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


  
