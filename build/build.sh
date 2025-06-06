echo get latest version
git pull
# copy sample config
mkdir -p .config
cp -r config-standard/* .config
mv .config/app.yam .
mv .config/composer.json .
# Setup data directories
mkdir -p data
mkdir -p data/access
mkdir -p data/users
mkdir -p data/added-local-services
mkdir -p data/external-services
mkdir -p data/SD-bee-resources
mkdir -p data/tmp
mkdir -p data/archive
# Setup services directory
mkdir -p services
# Setup links in root directory
ln -s data/sd-bee-cdn/fonts fonts
ln -s data/tmp tmp
ln -s data/sd-bee-cdn/resources/images upload
ln -s app/local-services/udservices.php udservices.php
ln -s app/local-services/udservicethrottle.php udservicethrottle.php
#Get PHP libraries
php composer.phar update
#composer require google/cloud-storage
# Local run
echo check localhost:8080
php -S localhost:8080 index.php
read -p "Do you wish to deploy ? " yn
case $yn in
    [Yy]* ) gcloud app deploy, break;;
    [Nn]* ) exit;;
    * ) echo "Please answer yes or no.";;
esac
echo end of build