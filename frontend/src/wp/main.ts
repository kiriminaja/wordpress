import { createApp } from "vue";
import App from "./App.vue";
import "./style.css";

function mountApp() {
  const mountPoint = document.getElementById("kiriminaja-wp-root");

  if (mountPoint) {
    const app = createApp(App);
    app.mount(mountPoint);
    console.log("KiriminAja Frontend Vue app mounted");
  } else {
    console.warn("KiriminAja Frontend mount point not found");
  }
}

// Mount immediately if DOM is ready, otherwise wait
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", mountApp);
} else {
  mountApp();
}
