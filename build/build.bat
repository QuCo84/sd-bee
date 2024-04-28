REM copy sample config
mkdir .config
copy config-standard\* .config
move .config\composer.json .
REM Setup data directories
mkdir data
mkdir data\access
mkdir data\users
mkdir data\SD-bee-resources
mkdir data\tmp
mkdir data\archive
REM Setup additional local services
mkdir services
php composer.phar update
REM check localhost:8080
php -S localhost:8080 index.php