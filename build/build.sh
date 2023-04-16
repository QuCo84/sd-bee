# copy sample config
mkdir .config
cp config-standard/* .config
# Setup data directories
mkdir data
mkdir data\access
mkdir data\users
#Get PHP libraries
composer require google/cloud-storage