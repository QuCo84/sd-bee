
<p align="center">
<img src="https://www.sd-bee.com/upload/O0W1b3s20_logosite.png" alt="Simply Done Bee" /><br>
SD bee - Simply Done<br>
<strong>Design, automate and deploy processes for digital tasks</strong>
</p>

"SD bee" (hereafter the software) is a software program for delivering web pages and web apps for designing, automating and deploying processes required for digitalisation.

THE SD BEE README FILE

This file provides an entry point to setting up SD bee after downloading the SD bee package available on GitHub at the address : https://github.com/QuCo84/sd-bee

THE CONCEPT

SD bee provides a collaborative document editor with several distinct features :
<ul>
  <li>documents are a stack of elements saved in JSON format</li>
  <li>every document is based on a model document and every element can have zero, one or more style classes</li>
  <li>the top level of a document are view containers used to display a specific part of a document at a time</li>
  <li>no general menus, only contextual ones - all styling and formatting is achieved by selecting from a short list of classes provided by the model the document is based on</li>
  <li>elements include containers, titles, paragraphs, lists, tables, drawings, connectors, user interface controls, style instructions and program code</li>
  <li>views have a type used to determine which elements can be inserted there, helping for example to group style and programs in dedidcated views</li>
  <li>program code and styling may draw from an extensible resource library using a variety of style and programming formats</li>
  <li>progam code accesses third-party applications via an extensible service gateway with in-built throttling (for invoiced services) and secure credentals management</li>
  <li>the clipboard is replaced by a permanent gallery of clips that can be initialised by the model</li>
  <li>documents contain task management information</li>
  <li>documents contain automation data for executing functions without user intervention</li>
</ul>

Process design and execution is based on a 3 level approach :
<ul>
  <li>Each <strong>Process Model</strong> contains the functionality required for a type of process and defines elements which the programs will use to get user input, parameters and where to display output</li>
  <li>Each <strong>Process</strong> uses a Process Model and includes the data required for the process being designed</li>
  <li>Each <strong>Task</strong> uses a Process and represents a specific instance of that process</li>
</ul>

As an example, the Questionnaire Process-Model asks questions in a zone or view, one at a time, based on a list of conditional questions taken form a table, and writes the user's answers to a second table. The "Define a marketing target" uses this model for a specific set of questions pertinent to defining marketing targets. A user uses this process several times to define each of their marketing targets.

At connection, SD bee displays the user's top task directory with the current status and progress of each task.

WHAT'S INCLUDED
The SD bee package provides the PHP programs for setting up a SD bee server. It uses resources available from the sd-bee.com website and has a modular design so it can be adapted to different cloud environments.
The admin user, created automatically, is setup with an initial "Get started" task.

For modifiying Javascript code used on the client side, please see the "SD bee client" project on GitHub.
For configuring and extending Services available to SD bee apps, please the "SD bee services" project on GitHub.

For all enquires and assistance, please fill in the contact form at www.sd-bee.com (padlock or Start button)

LICENSE

The software is published under the GNU GENERAL PUBLIC LICENSE Version 3.
see LICENSE.md

CREATOR

SD bee was created by Quentin Cornwell
[Find me on LinkedIn](https://www.linkedin.com/in/quentin-cornwell-895b0a/)

CONTRIBUTING

SD bee is in search of software and business developpers interested in using the Software for a single company or for providing an online service.



ARCHITECTURE

The sdbee-index.php handles all requests and dispatches them to files in the endpoints directory according to the request received after setting up data access in line with parameters provided by the sdbee-config.jsonc file. Endpoints are :
    <ul><li>Full page endpoints (GET)</li>
        <ul><li>/ built-in  Display home page if not connected or main task directory if connected</li>
        <li>/?task=name    Edit a process, app or app model</li></ul>
    <li>Get AJAX calls</li>
        <ul><li>/name/AJAX_listContainers sdbee-collection List tasks in a container</li>
        <li>/name/AJAX_fetch sdbee-fetch-element Return an element</li>
        <li>/name/AJAX_getChanged sdbee-changes Return a list of elements that have been changed</li>
        <li>//marketplace sdbee-marketplace Display a selection of models with links to select</li></ul>
    <li>Post AJAX calls</li>
        <ul><li>form=INPUT_UDE_FETCH sdbee-modify-element (create, update, delete)</li>
        <li>nServiceRequest=JSON sdbee-service-gateway Handle a call to a local or remote service</li>
        <li>form=INPUT_addApage sdbee-add-doc Add a new document (task, model)</li>
        <li>form=INPUT_createUser sdbee-add-user Add a new user</li>
        <li>form=INPUT_deleteUser sdbee-delete-user Delete a user</li>
        <li>form=INPUT_deleteDoc sdbee-delete-doc Delete a doc</li></ul>       
      </ul>
All endpoint scripts access data via the sdbee-storage class and the sdbee-access setup by sdbee-index.  These in turn use classes defined in the storage-connectors and access-connectors to adapt to different environments. 

Client-side programs and resources are initially setup to come from a public CDN.

Payable services are configured to use the sd-bee.com service where an account must be created and credits bought.

For a minimal setup on Google Cloud Platform (hereafter GCP), you will need :
- a Google account with facturation setup
- API access 
- at least one bucket on Google Cloud Storage (GCS hereafter)

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

Create access to GCS (see bloww) and add the test task to your pribvate storage with the default prefix.

Start local server with 
php -S localhost:8080 index.php

Use the "See on the web" button top right to visualise the local server.

DEPLOYMENT
<ol>
  <li>ON GCP WITH APP ENGINE
    <ul>
      <li>Access to GCS
        <ul>
          <li>Create a service account</li>
         <li>Get JSON file from API/Identifiants and save in the .config file under the name "sd-bee-gcs.json".</li>
        </ul>
      </li>
      <li>Deploy  
        <ul><li>gcloud app deploy </li></ul>
      </li>
    </ul>
  <li>
  <li> ON A SERVER
    <ul><li>No configuration should be required</li></ul>
  </li>
</ol>
