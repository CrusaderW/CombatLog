FROM node:lts-alpine

# install simple http server for serving static content

# make the 'app' folder the current working directory
WORKDIR /app

# copy project files and folders to the current working directory (i.e. 'app' folder)
COPY ./frontend ./frontend
COPY ./backend ./backend

# install project dependencies and build them
WORKDIR /app/frontend
RUN npm install
RUN npm run build
WORKDIR /app/backend
RUN npm install

EXPOSE 8080
CMD ["npm", "start"]
