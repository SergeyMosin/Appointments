/******/ (function() { // webpackBootstrap
var __webpack_exports__ = {};
/*!*********************!*\
  !*** ./src/cncf.js ***!
  \*********************/
(function () {
  "use strict";

  window.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('srgdev-appt-cncf_action_btn');

    if (btn !== null) {
      btn.addEventListener("click", function (e) {
        /** @type {HTMLElement} */
        var t = e.currentTarget;
        var attrName = 'data-appt-action-url';

        if (t !== null && t.hasAttribute(attrName)) {
          var uri = t.getAttribute(attrName); //Avoid double clicks

          t.removeAttribute(attrName); // show spinner

          document.getElementById("srgdev-ncfp_fbtn-spinner").style.display = "inline-block";

          if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', uri);
            window.history.go();
          } else {
            window.location = uri;
          }
        }
      });
    } else {
      if (typeof URL === "function" && window.history && window.history.replaceState) {
        var u = new URL(window.location);
        u.searchParams.delete("h");
        window.history.replaceState({}, '', u.toString());
      }
    }
  });
})();
/******/ })()
;
//# sourceMappingURL=cncf.js.map