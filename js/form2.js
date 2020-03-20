/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "/js/";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "./src/form2.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./src/form2.js":
/*!**********************!*\
  !*** ./src/form2.js ***!
  \**********************/
/*! no static exports found */
/***/ (function(module, exports) {

(function () {
  "use strict";

  window.addEventListener('DOMContentLoaded', formReady);

  function formReady() {
    document.getElementById("f2_btn").addEventListener("click", goBtn);
  }

  function goBtn() {
    var url = "http://127.0.0.1:8080/remote.php/dav/calendars/Serioga/cal-m/";
    var body = '<?xml version="1.0" encoding="utf-8" ?>' + '<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">' + '<D:prop>' + '<C:calendar-data/>' + '</D:prop>' + '<C:filter>' + '<C:comp-filter name="VCALENDAR">' + '<C:comp-filter name="VEVENT">' + '<C:time-range start="20200308T050000Z" end="20200315T040000Z"/>' + '</C:comp-filter>' + '</C:comp-filter>' + '</C:filter>' + '</C:calendar-query>'; //     let body='<?xml version="1.0" encoding="utf-8" ?>\n' +
    //         '   <C:calendar-multiget xmlns:D="DAV:"\n' +
    //         '                 xmlns:C="urn:ietf:params:xml:ns:caldav">\n' +
    //         '     <D:prop>\n' +
    //         '       <C:calendar-data/>\n' +
    //         '     </D:prop>\n' +
    //         '     <D:href>/remote.php/dav/calendars/Serioga/cal-m/666B362B8-DCEDF-59199-SA50F-CFD01BBC603A.ics</D:href>\n' +
    //         '   </C:calendar-multiget>';

    var oReq = new XMLHttpRequest();
    oReq.addEventListener("load", reqLoad);
    oReq.addEventListener("error", reqError);
    oReq.open("REPORT", url);
    oReq.setRequestHeader("Depth", "1");
    oReq.setRequestHeader("Content-Type", "text/xml; charset=utf-8"); // oReq.open("GET", ug);

    oReq.send(body);
  }

  function reqLoad() {
    console.log(this);
  }

  function reqError(e) {
    console.log(e);
  }
})();

/***/ })

/******/ });
//# sourceMappingURL=form2.js.map