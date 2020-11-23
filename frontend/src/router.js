import Vue from "vue";
import Router from "vue-router";

Vue.use(Router);

export default new Router({
  routes: [
    {
      path: "/upload",
      name: "upload",
      component: () => import("./views/Upload.vue")
    },
    {
      path: "/logs",
      name: "logs",
      component: () => import("./views/Logs.vue")
    },
    {
      path: "/mylogs",
      name: "mylogsPage",
      component: () => import("./views/myLogs.vue")
    },
    {
      path: "/fights",
      name: "fights",
      component: () => import("./views/Fights.vue")
    },
    {
      path: "/",
      name: "trainingDummy",
      component: () => import("./views/TrainingDummy.vue")
    }
  ]
});
