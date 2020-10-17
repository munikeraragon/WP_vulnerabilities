#!/bin/bash

docker-compose exec -e MYSQL_PWD=examplepass db /usr/bin/mysqldump -u exampleuser --no-tablespaces exampledb 2>/dev/null 1> db/backup.sql
