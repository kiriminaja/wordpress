import { createApp } from "vue";
import { createRouter, createWebHistory } from "vue-router";
import App from "./App.vue";
import ui from "@nuxt/ui/vue-plugin";
import "./style.css";

function mountApp() {
  const mountPoint = document.getElementById("kaj-admin-root");

  if (mountPoint) {
    const app = createApp(App);
    const router = createRouter({
      routes: [],
      history: createWebHistory(),
    });

    app.use(router);
    app.use(ui);
    app.mount(mountPoint);
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
