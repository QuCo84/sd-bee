REM copy sample config
mkdir .config
copy config-standard\* .config
REM Setup data directories
mkdir data
mkdir data\access
mkdir data\users
mkdir data\resources
mkdir data\tmp
REM Setup additional local services
mkdir services
REM check localhost:8080
php -S localhost:8080 index.php