
<p align="center>
<img src="https://www.sd-bee.com/upload/O0W1b3s20_logosite.png" alt="Simply Done Bee" />
SD bee - Simply Done
Design, automate and deploy processes for digtla tasks
</p>

"SD bee" (hereafter the software) is a software program for delivering web pages and web apps for designing, automating and deploying processes required for digitalisation.

THE SD BEE README FILE

This file provides an entry point to setting up SD bee after downloading the SD bee package available on GitHub at the address : https://github.com/QuCo84/sd-bee

THE SOFTWARE

SD bee makes it easy to design and automate processes required for digitalisation and then to execute tasks based on these processes.

The SD bee package provides a minimal PHP server program for setting up SD bee. It uses resources available on the sd-bee.com website.
It has a modular design so it can be adapted to diffierent cloud environments.

For modifiying Javascript code used on the client side, please see the "SD bee client" project on GitHub.
For configuring and extending Services available to SD bee apps, please the "SD bee services" project on GitHub.

For all enquires and assistance, please fill in the contact form at www.sd-bee.com (padlock or Start button)

LICENSE

The software is published under the GNU GENERAL PUBLIC LICENSE Version 3.
see LICENSE.md

ARCHITECTURE

The sdbee-index.php handles all requests and dispatches them to files in the endpoints directory according to the request received after setting up data access in line with parameters provided by the sdbee-config.jsonc file. Endpoints are :
    <ul><li>Full page endpoints (GET)</li>
        <ul><li>/ home  Display home page if not connected or main task directory if connected</li>
        <li>/?task=name    Edit a process, app or app model</li></ul>
    <li>Get AJAX calls</li>
        <ul><li>/name/AJAX_listContainers collection List tasks in a container</li>
        <li>/name/AJAX_fetch fetch-element Return an element</li>
        <li>/name/AJAX_getChanged changes Return a list of elements that have been changed</li>
        <li>//marketplace Display a selection of models with links to select</li></ul>
    <li>Post AJAX calls</li>
        <ul><li>form=INPUT_UDE_FETCH modify-element (create, update, delete)</li>
        <li>nServiceRequest=JSON service-gateway Handle a call to a local or remote service</li>
        <li>form=INPUT_addApage add-doc Add a new document (task, model)</li>
        <li>form=INPUT_createUser add-user Add a new user</li>
        <li>TODO delete-user</li>
        <li>TODO delete-doc</li></ul>       

All endpoint scripts access data via the sdbee-storage class and the sdbee-access setup by sdbee-index.  These in turn use classes defined in the storage-connectors and access-connectors to adapt to different environments. 

Client-side programs and resources are initially setup to come from a public CDN.

Payable services are configured to use the sd-bee.com service where an account must be created and credits bought.

For a minimal setup on Google Cloud Platform (hereafter GCP), you will need :
- a Google account with facturation setup
- API access 
- at least one Google Cloud Storage bucket

For a minimal setup on a web server or trial setup on a PC, you will need :
- an HTTP server setup
- a directory where to place files

DOWNLOAD

To install the Software, from your main directory (contact in GCP) download from git using
git clone https://github.com/QuCo84/sd-bee

This will create a directory sd-bee and populate it with the program's files.
Enter the sd-bee directory.

CONFIGURATION

Before configurating, create a file .gitignore with .config so that your configuration changes are not overwritten if you pull an update of the Software.

The .config/sdbee-config.json defines a set of configuration parameters :

public - Where to find public resources
Leave the default settings to use the sd-bee CDN or sd-bee.com website for access to public resources.

ACCESS DATABASE
 1) ON GOOGLE CLOUD STORAGE
  To use Google Cloud Storage for the access database, 
    save your credentials file in the .config directory in a file named sd-bee-gcs.json.
    create a bucket, eg sd-bee-access, to store the access database
      you can choose the region adapted to your use and do not give public access to this bucket.
  Then configure the "admin-storage" section with :
    "storage-service" : "gs",
    "keyFile" :".config/sd-bee-gcs.json",
    "bucket" : "sd-bee-access",
    "top-dir" : "",
    "prefix" : "",
 2) ON A SERVER
    Create a directory to store system data such as the access database
    Configure the "admin-storage" section with :
      "storage-service" : "file",
      "top-dir" : "<full path to the directory>", 
      "prefix" : ""

USERS'S DATA
  1) ON GOOGLE CLOUD STORAGE
    create a bucket, eg sd-bee-users, to store users' documents
     you can choose the region adapted to your use and do not give public access to this bucket.
    Then configure the "private-storage" section with :
      "storage-service" : "gs",
      "keyFile" :".config/sd-bee-gcs.json",
      "bucket" : "sd-bee-users",
      "top-dir" : "",
      "prefix" : ""
  2) ON A SERVER
    Create a directory to store system data such as the access database
    Configure the "provate-storage" section with :
      "storage-service" : "file",
      "top-dir" : "<full path to the directory>", 
      "prefix" : ""

RUNNING LOCALLY
If you wish to run a local test, some packages are required. Execute in the main directory :
   composer require google/cloud-storage

Create access to Google CLoud STorage (see bloww) and add the test task to your pribvate storage with the default prefix.

Start local server with 
php -S localhost:8080 index.php

Use the "See on the web" button top right to visualise the local server.

DEPLOYMENT

1) ON GCP WITH APP ENGINE
Access to Google CLoud Storage
  Create a service account
  Get JSON file from API/Identifiants and save in the .config file under the name "sd-bee-gcs.json".
Deploy  
  gcloud app deploy 

2) ON A SERVER
No configuration should be required

KEYS
