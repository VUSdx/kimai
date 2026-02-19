 # Remember to remove ssl cert lines from .docker/dbtest.php and config/packages/doctrine.yaml
 
 docker run --name kimai-mysql-testing -e MYSQL_DATABASE=kimai -e MYSQL_USER=kimai -e MYSQL_PASSWORD=kimai -e MYSQL_ROOT_PASSWORD=kimai -p 3456:3306 -d mysql
 
 docker run --rm --name kimai-test-vusdx -d -ti -p 8001:8001 -e APP_ENV="prod" -e DATABASE_URL="mysql://kimai:kimai@host.docker.internal:3456/kimai?charset=utf8mb4&serverVersion=9.5.0" crvusdxprodne001.azurecr.io/kimai2
 