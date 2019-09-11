# Alarm-Frontend

On the Docker host:

## 1. Run
```
git clone https://github.com/stevescode/alarm-frontend/
```
to bring a local copy of this directory

## 2. Navigate to the root of the new directory

## 3. Run
```
docker run -d -p 80:80 --name alarm-frontend -v "$PWD":/var/www/html --network host php:7.2-apache
```
to start a docker container with mappings to this directory
