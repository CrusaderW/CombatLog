# CombatLog

## Prerequisites:
You need the following things installed:
- docker
- docker-compose

## Submodules:
- This repository usses the datastructures for Crowfall Skills and Disciplines of https://github.com/MalekaiProject/crowfall-data
- To update all submodules type:
'''git submodule update --remote'''

## Usage: fire up the mongodb:
'''sudo docker-compose -f docker-compose.yml up'''

## runing the server
'''cd ./frontend'''
'''npm run build'''
'''cd ../backend'''
'''npm start'''
