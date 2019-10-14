# CombatLog

## Important:
Please upvote this issue if you want this project to stay alive: https://issuetracker.google.com/issues/70779807

## Prerequisites:
You need the following things installed:
- docker
- docker-compose
- pip3 for AWS deployment
- node v12

## Submodules:
- This repository usses the datastructures for Crowfall Skills and Disciplines of https://github.com/MalekaiProject/crowfall-data
- To update all submodules type:

`git submodule update --remote`

## Local Development
For local development please make sure you are running node v12
Update our malekai.org data
`git submodule update --init`
Bring up a local copy of MongoDB, this will store data in `./.var/lib/mongo_combat_log_data_dev/`
`docker-compose -f docker-compose_dev.yml up`
Parse/position the malekai.org data in the frontend and backend folders.
`node powersLoader.js`
Install and build our frontend, this will output to ./backend/public
`cd frontend`
`npm install`
`npm run build`
Now run our backend application using nodemon.
`cd ../backend`
`npm install`
`npm run dev`

## Running a demo of the service
This will build the current working directory.
`docker-compose build`
`docker-compose up`

