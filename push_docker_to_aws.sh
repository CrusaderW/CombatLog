#!/bin/bash
sudo $(aws ecr get-login --no-include-email --region eu-central-1)
sudo docker-compose build
sudo docker push 100256065505.dkr.ecr.eu-central-1.amazonaws.com/combat_log:latest

# Start the Cluster
#ecs-cli compose --project-name ecs_combatLog service up --cluster-config combatLog --cluster combatLog

# Stop the Cluster
#ecs-cli compose --project-name ecs_combatLog service down --cluster-config combatLog --cluster combatLog

# Scale the Cluster
#ecs-cli compose --project-name ecs_combatLog service scale <NumberOfInstances> --cluster-config combatLog --cluster combatLog

# Terminate the Cluster
#ecs-cli down --force --cluster-config combatLog --cluster combatLog

