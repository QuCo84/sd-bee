
<p align="center">
<img src="https://www.sd-bee.com/upload/O0W1b3s20_logosite.png" alt="Simply Done Bee" /><br>
SD bee - Simply Done<br>
<strong>Design, automate and deploy processes for digital tasks</strong>
</p>

"SD bee" (hereafter the software) is a software program for delivering web pages and web apps for designing, automating and deploying processes required for digitalisation.

# THE SD BEE README FILE

This file provides an entry point to setting up SD bee after downloading the SD bee package available on GitHub at the address : https://github.com/QuCo84/sd-bee

## THE CONCEPT

Process design and execution is achieved through 3 levels of smart documents (ie. documents including programs) :
<ul>
  <li>Each <strong>Process Model</strong> document contains the functionality required for a type of process and defines elements which the programs will use to get user input, parameters and where to display output</li>
  <li>Each <strong>Process</strong> document uses a Process Model and includes the data required for the process being designed</li>
  <li>Each <strong>Task</strong> uses a Process and represents a specific instance of that process</li>
</ul>

As an example, the Questionnaire Process-Model asks questions in a zone or view, one at a time, based on a list of conditional questions taken form a table, and writes the user's answers to a second table. The "Define a marketing target" uses this model for a specific set of questions pertinent to defining marketing targets and then uses a library function to generate keywords from searches defined by the answers. A user uses this process several times to define each of their marketing targets and the associated keywords

These smart documents are created and modified using SD bee's collaborative editor with has some distinct features :
<ul>
  <li>easy-to-parse JSON storage of element stack and task progress</li>
  <li>multiple views</li>
  <li>adaptable contextual menu</li> 
  <li>formulas anywhere</li>
  <li><a href="editor-features.md">Read more</a></li>
</ul>
  
At connection, SD bee displays the user's top task directory with the current status and progress of each task.

## WHAT'S INCLUDED

The SD bee package provides the PHP programs for setting up a SD bee server. It uses resources available from the sd-bee.com website and has a modular design so it can be adapted to different cloud environments.
The admin user, created automatically, is setup with an initial "Get started" task.

For modifiying Javascript code used on the client side, please see the "sdbee-client" project on GitHub. For configuring and extending Services available to SD bee apps, please see the "sdbee-service-gateway" project on GitHub.

For all enquires and assistance, please fill in the contact form at www.sd-bee.com (padlock or Start button)

## LICENSE

The software is published under the GNU GENERAL PUBLIC LICENSE Version 3.
see LICENSE.md

## CREATOR

SD bee was created by Quentin Cornwell
[Find me on LinkedIn](https://www.linkedin.com/in/quentin-cornwell-895b0a/)

## CONTRIBUTING

SD bee is in search of software and business developpers interested in using the Software for a single company or for providing an online service.

## REQUIREMENTS

For a minimal setup on Google Cloud Platform (hereafter GCP), you will need :
- a Google account (with payment setup)
- API access : Google Cloud Storage, 
- at least one bucket on Google Cloud Storage (GCS hereafter)

For a minimal setup on a web server or trial setup on a PC, you will need :
- an HTTP server setup
- a directory where to place files

## DOWNLOAD

To install the Software, from your main directory (contact in GCP) download from git using

git clone https://github.com/QuCo84/sd-bee

This will create a directory sd-bee and populate it with the program's files.
Enter the sd-bee directory.

## CONFIGURATION

Before configurating, check the .gitignore file to make sure it matches your setup. By default, the directories .config and data (not created) are included in this file so that your configuration changes or any data you wish to include in the sd-bee directory are not overwritten when you pull an update of the Software.

To create a local directory for data, run build\build.bat from the top directory.

The .config/sdbee-config.json defines a set of configuration parameters :

<ul>
  <li>public - Where to find public resources

  Leave the default settings to use the sd-bee CDN or sd-bee.com website for access to public resources.
  </li>

  <li>Access Database - control access to documents
    <ol>
      <li>on Google Cloud Storage( GCS hereafter)
        <ul>
          <li>To use Google Cloud Storage for the access database, 
            <ul>
              <li>save your credentials file in the .config directory in a file named sd-bee-gcs.json.</li>
              <li>create a bucket, eg sd-bee-access, to store the access database</li>
              <li>you can choose the region adapted to your use and do not give public access to this bucket.</li>
            </ul>
          </li>
          <li>Then configure the "admin-storage" section with :
            <ul>
              <li>"storage-service" : "gs",</li>
              <li>"keyFile" :".config/sd-bee-gcs.json",</li>
              <li>"bucket" : "sd-bee-access",</li>
              <li>"top-dir" : "",</li>
              <li>"prefix" : "<0-8 characters>",</li>
            </ul>
          </li>
        </ul>
      </li>
      <li>on a server or VPS
        <ul>
          <li>Create a directory to store system data such as the access database eg sdbee-access</li>
          <li>Configure the "admin-storage" section with :
            <ul>
              <li>"storage-service" : "file",</li>
              <li>"top-dir" : "< full path to the directory>", </li>
              <li>"prefix" : "<0-8 characters>"</li>
            </ul>
          </li>
        </ul>
      </li>
    </ol>  
  </li>
  <li>Users' data
    <ol>
      <li>on GCS
      <ul>
        <li>create a bucket, eg sd-bee-users, to store users' documents</li>
        <li>you can choose the region adapted to your use and do not give public access to this bucket.</li>
        <li>Then configure the "private-storage" section with :
        <ul>
          <li>"storage-service" : "gs",</li>
          <li>"keyFile" :".config/sd-bee-gcs.json",</li>
          <li>"bucket" : "sd-bee-users",</li>
          <li>"top-dir" : "",</li>
          <li>"prefix" : "<0-8 characters>"</li>
        </ul>
      </ul>
    </li>
    <li>on a server
      <ul>
        <li>Create a directory to store users' data, eg sdbee-users</li>
        <li>Configure the "provate-storage" section with :
          <ul>
            <li>"storage-service" : "file",</li>
            <li>"top-dir" : "< full path to the directory>", </li>
            <li>"prefix" : "<0-8 characters>"</li>
          </ul>
        </li>
      </ul>
    </li>
  </ol>
</ul>

## RUNNING LOCALLY

### RUNNING LOCALLY ON WINDOWS

Make sure you have created a local data directory with build\build.bat or setup the configuration to access available directories.

Start local server, on port 8080 for example, with 

php -S localhost:8080 index.php

Open a browser tab to localhost:8080



### RUNNING LOCALLY ON GCP
  
If you wish to run a local test on GCP, some packages are required. Execute in the main directory :
  
   composer require google/cloud-storage

Create access to GCS (see below) and add the test task to your private storage with the default prefix.

Start local server with 

php -S localhost:8080 index.php

Use the "See on the web" button top right to visualise the local server.
  

## DEPLOYMENT
<ol>
  <li>on GCP with App Engine
    <ul>
      <li>Create a project on your Google CLoud account</li>
      <li>Activate GCS API for the project</li>
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
  </li>
  <li> on a server
    <ul><li>Configure routing (ex. .htaccess) to send appropriate requests to the index.php file in the top sd-bee directory.</li></ul>
  </li>
</ol>



## READ MORE

[Source file overview](source-overview.md)