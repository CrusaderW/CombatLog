# CombatLog

## Important:
Please upvote this issue if you want this project to stay alive: https://issuetracker.google.com/issues/70779807

## Prerequisites:
You need the following things installed:
- docker
- docker-compose
- pip3
- node v12

## Submodules:
- This repository usses the datastructures for Crowfall Skills and Disciplines of https://github.com/MalekaiProject/crowfall-data
- To update all submodules type:

`git submodule update --remote`

## running the server
Run the following commands once:
`git submodule update --init`
`docker-compose -f docker-compose_dev.yml build`
`node powersLoader.js`
`cd frontend`
`npm install`
`cd ../backend`
`npm install`

To run in development mode:
`docker-compose -f docker-compose_dev.yml up`

