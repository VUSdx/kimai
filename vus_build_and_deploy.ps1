az acr login --name crvusdxprodne001
docker build --tag crvusdxprodne001.azurecr.io/kimai2 --no-cache --target=prod --build-arg BASE=apache .
docker push crvusdxprodne001.azurecr.io/kimai2-app-service

