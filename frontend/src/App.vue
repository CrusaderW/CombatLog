<template>
  <div id="app">
    <div id="nav">
      <router-link to="/upload">Upload</router-link>
      {{" | "}}
      <router-link to="/mylogs">My Logs</router-link>
      {{" | "}}
      <router-link to="/fights">Fights</router-link>
      {{" | "}}
      <router-link to="/">Training Dummy</router-link>
    </div>
    <router-view />
  </div>
</template>

<style>
@import url("https://fonts.googleapis.com/css?family=Asul");
#app {
  font-family: "Asul", Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
}
#nav {
  padding: 30px;
}

#nav a {
  font-weight: bold;
  color: #2c3e50;
}

#nav a.router-link-exact-active {
  color: #42b983;
}
</style>
