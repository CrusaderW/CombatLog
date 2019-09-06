#!/bin/bash
AWS_ACCESS_KEY_ID=pleaseEnter
AWS_SECRET_ACCESS_KEY=pleaseEnter
ecs-cli configure profile --profile-name combatLog --access-key $AWS_ACCESS_KEY_ID --secret-key $AWS_SECRET_ACCESS_KEY
ecs-cli configure --cluster combatLog --region eu-central-1 --default-launch-type FARGATE --config-name combatLog
aws iam --region eu-central-1 create-role --role-name ecsTaskExecutionRole --assume-role-policy-document file://task-execution-assume-role.json
aws iam --region eu-central-1 attach-role-policy --role-name ecsTaskExecutionRole --policy-arn arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy
ecs-cli up --cluster combatLog
aws ec2 create-security-group --group-name "crusaderwCom-sg" --description "My CrusaderW security group" --vpc-id "vpc-0a848352d9de1ecb3"
aws ec2 authorize-security-group-ingress --group-id "sg-0998fddd5a8f96cf3" --protocol tcp --port 80 --cidr 0.0.0.0/0

