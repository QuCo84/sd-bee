echo setup newsletter services as local
wget "https://www.sd-bee.com/upload/newsletter.tar.gz"
tar -xzvf newsletter.tar.gz -C data/added-local-services
# copy model(s) to data/user/models
# cp data/added-local-services/models/*.json data/users/models/*.json
unlink newsletter.tar.gz