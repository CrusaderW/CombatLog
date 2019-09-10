import Vue from "vue";
import VueAnalytics from "vue-ua";
import ElementUI from "element-ui";
import "element-ui/lib/theme-chalk/index.css";
import App from "./App.vue";
import router from "./router";

Vue.use(ElementUI);

Vue.use(VueAnalytics, {
  // [Required] The name of your app as specified in Google Analytics.
  appName: "crusaderw.com",
  // [Required] The version of your app.
  appVersion: "1",
  // [Required] Your Google Analytics tracking ID.
  trackingId: "UA-143780876-1",
  // If you're using vue-router, pass the router instance here.
  vueRouter: router,
  // Whether you want page changes to be recorded as pageviews (website) or screenviews (app), default: false
  trackPage: true
});

Vue.config.productionTip = false;

new Vue({
  router,
  render: h => h(App)
}).$mount("#app");
