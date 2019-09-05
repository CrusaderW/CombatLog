#!/bin/bash
AWS_ACCESS_KEY_ID=pleaseEnter
AWS_SECRET_ACCESS_KEY=pleaseEnter
ecs-cli configure profile --profile-name combat_log --access-key $AWS_ACCESS_KEY_ID --secret-key $AWS_SECRET_ACCESS_KEY
ecs-cli configure --cluster combat_log --region eu-central-1 --default-launch-type FARGATE --config-name combat_log
