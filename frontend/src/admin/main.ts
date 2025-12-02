import { createApp } from "vue";
import { createRouter, createWebHistory } from "vue-router";
import App from "./App.vue";
import ui from "@nuxt/ui/vue-plugin";
import "./style.css";

function mountApp() {
  const mountPoint = document.getElementById("kiriminaja-admin-root");

  if (mountPoint) {
    const app = createApp(App);
    const router = createRouter({
      routes: [],
      history: createWebHistory(),
    });

    app.use(router);
    app.use(ui);
    app.mount(mountPoint);
    console.log("KiriminAja Admin Vue app mounted");
  } else {
    console.warn("KiriminAja Admin mount point not found");
  }
}

// Mount immediately if DOM is ready, otherwise wait
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", mountApp);
} else {
  mountApp();
}
