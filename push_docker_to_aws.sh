#!/bin/bash
sudo $(aws ecr get-login --no-include-email --region eu-central-1)
sudo docker-compose build
sudo docker tag combatlog_combat_log:latest 100256065505.dkr.ecr.eu-central-1.amazonaws.com/combat_log:latest
sudo docker push 100256065505.dkr.ecr.eu-central-1.amazonaws.com/combat_log:latest
