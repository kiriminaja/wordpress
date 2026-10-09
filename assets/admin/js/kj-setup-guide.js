(function () {
  "use strict";
  document.addEventListener("DOMContentLoaded", function () {
    var config = window.kiriofSetupGuide || {};
    var root = document.getElementById(config.noticeId);
    if (!root) return;
    var slides = Array.prototype.slice.call(
      root.querySelectorAll("[data-kiriof-setup-slide]"),
    );
    if (!slides.length) return;
    var currentIndex = parseInt(config.currentIndex, 10) || 0;
    var prev = root.querySelector("[data-kiriof-setup-prev]");
    var next = root.querySelector("[data-kiriof-setup-next]");
    function render(index) {
      currentIndex = index;
      slides.forEach(function (slide, i) {
        slide.style.display = i === currentIndex ? "block" : "none";
      });
      if (prev) {
        prev.disabled = currentIndex === 0;
        prev.style.opacity = currentIndex === 0 ? "0.35" : "1";
      }
      if (next) {
        next.disabled = currentIndex === slides.length - 1;
        next.style.opacity = currentIndex === slides.length - 1 ? "0.35" : "1";
      }
    }
    if (prev)
      prev.addEventListener("click", function () {
        if (currentIndex > 0) render(currentIndex - 1);
      });
    if (next)
      next.addEventListener("click", function () {
        if (currentIndex < slides.length - 1) render(currentIndex + 1);
      });
    render(Math.min(currentIndex, slides.length - 1));
  });
})();
