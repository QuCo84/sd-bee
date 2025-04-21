REM copy sample config
mkdir .config
copy config-standard\* .config
move .config\composer.json .
REM Setup data directories
mkdir data
mkdir data\access
mkdir data\users
mkdir data\added-local-services
mkdir data\external-services
mkdir data\SD-bee-resources
mkdir data\tmp
mkdir data\archive
REM Setup additional local services
mkdir services
REM Setp links in root directory (mklink command not tested)
mklink /d fonts data/sd-bee-cdn/fonts
mklink /d tmp data/tmp
mklink /d upload data/sd-bee-cdn/resources/images
mklink udservices.php app/local-services/udservices.php
mklink udservicethrottle.php app/local-services/udservicethrottle.php 
php composer.phar update
REM check localhost:8080
php -S localhost:8080 index.php