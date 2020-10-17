#!/bin/bash

docker-compose exec -e MYSQL_PWD=examplepass -T db /usr/bin/mysql -u exampleuser exampledb < db/backup.sql
