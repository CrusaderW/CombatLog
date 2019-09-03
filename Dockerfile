FROM node:lts-alpine

# install simple http server for serving static content

# make the 'app' folder the current working directory
WORKDIR /app

# copy project files and folders to the current working directory (i.e. 'app' folder)
COPY . .

# install project dependencies and build them
RUN node powersLoader.js
WORKDIR /app/frontend
RUN npm install
RUN npm run build
WORKDIR /app/backend
RUN npm install

EXPOSE 8080
CMD ["npm", "start"]
