echo get latest version
git pull
#Get PHP libraries
composer require google/cloud-storage
gcloud app deploy
# Local run
echo check localhost:8080
php -S localhost:8080 index.php
read -p "Do you wish to deploy ? " yn
case $yn in
    [Yy]* ) gcloud app deploy, break;;
    [Nn]* ) exit;;
    * ) echo "Please answer yes or no.";;
esac
echo end of update