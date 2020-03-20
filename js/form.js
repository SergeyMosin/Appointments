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
/******/ 	return __webpack_require__(__webpack_require__.s = "./src/form.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./src/form.js":
/*!*********************!*\
  !*** ./src/form.js ***!
  \*********************/
/*! no static exports found */
/***/ (function(module, exports) {

function _typeof(obj) { "@babel/helpers - typeof"; if (typeof Symbol === "function" && typeof Symbol.iterator === "symbol") { _typeof = function _typeof(obj) { return typeof obj; }; } else { _typeof = function _typeof(obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }; } return _typeof(obj); }

(function () {
  "use strict";

  window.addEventListener('DOMContentLoaded', formReady);

  function formReady() {
    var gdpr = document.getElementById('appt_gdpr_id');

    if (gdpr !== null) {
      gdpr.addEventListener('change', gdprCheck);
      gdprCheck.apply(gdpr);
    }

    var f = document.getElementById("srgdev-ncfp_frm");
    f.addEventListener("submit", formSubmit); // chrome bfcache

    setTimeout(function () {
      f.autocomplete = "on";
    }, 1000);
    makeDpu(f.getAttribute("data-pps"));
    document.getElementById("srgdev-ncfp_sel-dummy").addEventListener("click", selClick);
    setTimeout(function () {
      var b = document.getElementById("srgdev-ncfp_fbtn");
      b.disabled = true;
      b.textContent = "Session Timeout. Reload.";
    }, 900000);
  }

  function gdprCheck() {
    var btn = document.getElementById("srgdev-ncfp_fbtn");

    if (this.checked) {
      if (btn.hasAttribute('shade')) btn.removeAttribute('shade');
    } else {
      if (!btn.hasAttribute('shade')) btn.setAttribute('shade', "1");
    }

    if (this.hasAttribute("err")) {
      this.removeAttribute("err");
    }

    if (this.hasAttribute("required")) {
      this.removeAttribute("required");
    }
  }

  function clearFormErr() {
    this.setCustomValidity('');

    if (this.getAttribute('err')) {
      this.removeAttribute('err');
      this.removeEventListener("focus", clearFormErr, false);
    } else {
      this.removeEventListener("input", clearFormErr, false);
    }
  }

  function formSubmit(e) {
    var lee = 0;
    var el = document.getElementById("srgdev-ncfp_fbtn");

    if (el.disabled === true) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }

    el = document.getElementById("srgdev-ncfp_sel-hidden");

    if (el.selectedIndex === -1 || el.value === "") {
      el = document.getElementById("srgdev-ncfp_sel-dummy");
      el.setAttribute('err', 'err');
      el.addEventListener("focus", clearFormErr, false);
      lee = 1;
    }

    el = document.getElementById("srgdev-ncfp_fname");

    if (el.value.length < 3) {
      el.setCustomValidity(t('appointments', 'Name is required.'));
      el.addEventListener("input", clearFormErr, false);
      lee = 1;
    }

    el = document.getElementById("srgdev-ncfp_femail");

    if (el.value.length < 5 || el.value.indexOf("@") === -1 || el.value.indexOf("@") > el.value.lastIndexOf(".")) {
      el.setCustomValidity(t('appointments', 'Email is required.'));
      el.addEventListener("input", clearFormErr, false);
      lee = 1;
    } // match [0-9], '.()-+,/' and ' ' (space) at least 9 digits


    el = document.getElementById("srgdev-ncfp_fphone");

    if (el.value === '' || el.value.length < 9 || /^[0-9 .()\-+,/]*$/.test(el.value) === false) {
      el.setCustomValidity(t('appointments', 'Phone number is required.'));
      el.addEventListener("input", clearFormErr, false);
      lee = 1;
    }

    el = document.getElementById('appt_gdpr_id');

    if (el !== null && el.checked === false) {
      el.setAttribute("err", "err");
      el.setAttribute("required", "1");
      lee = 1;
    }

    if (lee !== 0) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }
  }

  function selClick(e) {
    var elm = document.getElementById("srgdev-dpu_main-cont");

    if (elm.getAttribute("data-open") === null) {
      elm.setAttribute("data-open", '');
    } else {
      elm.removeAttribute("data-open");
    }

    e.preventDefault();
    return false;
  }

  function dateClick(e) {
    var n = this.id.slice(13);
    var c = this.parentElement.curActive;
    if (c === n) return;
    document.getElementById('srgdev-dpu_dc' + c).removeAttribute('data-active');
    document.getElementById('srgdev-dpu_dc' + n).setAttribute('data-active', '');
    this.parentElement.curActive = n;
    if (n.slice(-1) === 'e') n = 'e';
    if (c.slice(-1) === 'e') c = 'e';
    document.getElementById('srgdev-dpu_tc' + c).removeAttribute('data-active');
    document.getElementById('srgdev-dpu_tc' + n).setAttribute('data-active', '');
    e.stopPropagation();
  }

  function timeClick(e) {
    var t = e.target;

    if (t.dpuClickID !== undefined) {
      document.getElementById('srgdev-ncfp_sel-dummy').value = t.parentElement.getAttribute('data-dm') + ' - ' + t.textContent;
      document.getElementById('srgdev-ncfp_sel-hidden').selectedIndex = t.dpuClickID;
      document.getElementById("srgdev-dpu_main-cont").removeAttribute("data-open");
    }
  }

  function prevNextDPU(e) {
    var p = e.target.parentElement;

    if (e.target.id === "srgdev-dpu_bf-back") {
      if (p.curDP > 0) p.curDP--;
    } else {
      if (p.curDP < p.maxDP) p.curDP++;

      if (p.curDP === p.maxDP) {
        e.target.setAttribute('disabled', '');
      } else {
        e.target.removeAttribute('disabled');
      }
    }

    if (p.curDP === 0) {
      p.firstElementChild.setAttribute('disabled', '');
    } else {
      p.firstElementChild.removeAttribute('disabled');
    }

    if (p.curDP === p.maxDP) {
      p.lastElementChild.setAttribute('disabled', '');
    } else {
      p.lastElementChild.removeAttribute('disabled');
    } // TODO: find first not empty and select it ?


    document.getElementById("srgdev-dpu_main-date").style.left = "-" + p.curDP * 5 * 4.6 + "em";
  }

  function makeDpu(pps) {
    var PPS_NWEEKS = "nbrWeeks";
    var PPS_EMPTY = "showEmpty";
    var PPS_FNED = "startFNED";
    var PPS_WEEKEND = "showWeekends";
    var PPS_TIME2 = "time2Cols";
    var pso = {};
    var ta = pps.slice(0, -1).split('.');

    for (var a, _l = ta.length, i = 0; i < _l; i++) {
      a = ta[i].split(':');
      pso[a[0]] = +a[1];
    }

    var min_days = 7 * pso[PPS_NWEEKS];
    var s = document.getElementById('srgdev-ncfp_sel-hidden');

    if (s.getAttribute("data-state") !== '2') {
      console.log("data-state: ", s.getAttribute("data-state"));
      return;
    }

    var mn;
    var dn;

    if (window.monthNames !== undefined) {
      mn = window.monthNames;
    } else {
      mn = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    }

    if (window.dayNames !== undefined) {
      dn = window.dayNames;
    } else {
      dn = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    }

    var tf;

    if (window.Intl && _typeof(window.Intl) === "object") {
      var f = new Intl.DateTimeFormat([], {
        hour: "numeric",
        minute: "2-digit"
      });
      tf = f.format;
    } else {
      tf = function tf(d) {
        return d.toLocaleTimeString();
      };
    }

    var df;

    if (window.Intl && _typeof(window.Intl) === "object") {
      var _f = new Intl.DateTimeFormat([], {
        month: "long"
      });

      df = _f.format;
    } else {
      df = function df(d) {
        return mn[d.getMonth()];
      };
    }

    var wf;

    if (window.Intl && _typeof(window.Intl) === "object") {
      var _f2 = new Intl.DateTimeFormat([], {
        weekday: "short"
      });

      wf = _f2.format;
    } else {
      wf = function wf(d) {
        return dn[d.getDay()];
      };
    }

    var wft;

    if (window.Intl && _typeof(window.Intl) === "object") {
      var _f3 = new Intl.DateTimeFormat([], {
        weekday: "short",
        month: "long",
        day: "2-digit"
      });

      wft = _f3.format;
    } else {
      wft = function wft(d) {
        return d.toDateString();
      };
    }

    var wff;

    if (window.Intl && _typeof(window.Intl) === "object") {
      var _f4 = new Intl.DateTimeFormat([], {
        weekday: "long",
        month: "long",
        day: "numeric",
        year: "numeric"
      });

      wff = _f4.format;
    } else {
      wff = function wff(d) {
        return d.toLocaleDateString();
      };
    } // Options can be unsorted if mixed timezones are used
    // TODO: maybe use data-xxx to send info instead of options


    var so = s.options;
    var dta = [];

    for (var ts, dd = new Date(), o, _l2 = so.length, _i = 0; _i < _l2; _i++) {
      o = so[_i];
      ts = o.getAttribute('data-ts') * 1000;

      if (ts !== 0) {
        dd.setTime(ts);

        if (o.getAttribute('data-tz') === "L") {
          ts += dd.getTimezoneOffset() * 60000;
        }

        dta[_i] = {
          rts: ts,
          idx: _i
        };
      }
    }

    dta.sort(function (a, b) {
      return a.rts > b.rts ? 1 : -1;
    });
    dta.push({
      rts: 0,
      idx: 0
    }); //last option to finalize the loop

    var l = dta.length;
    s.selectedIndex = -1;
    s.value = "";
    var cont = document.createElement('div');
    cont.id = "srgdev-dpu_main-cont";
    cont.className = "srgdev-dpu-bkr-cls";
    var lcd = document.createElement('div');
    lcd.id = "srgdev-dpu_main-header";
    lcd.appendChild(document.createTextNode(t('appointments', 'Select Date and Time')));
    var lcdBF = document.createElement('div');
    lcdBF.id = "srgdev-dpu_main-hdr-icon";
    lcdBF.className = "icon-close";
    lcdBF.addEventListener('click', function () {
      document.getElementById("srgdev-dpu_main-cont").removeAttribute("data-open");
    });
    lcd.appendChild(lcdBF);
    cont.appendChild(lcd);
    lcdBF = document.createElement('div');
    lcdBF.maxDP = 0;
    lcdBF.curDP = 0;
    lcdBF.id = "srgdev-dpu_bf-cont";
    lcdBF.appendChild(document.createElement("span"));
    lcdBF.appendChild(document.createElement("span"));
    lcdBF.firstElementChild.id = "srgdev-dpu_bf-back";
    lcdBF.firstElementChild.appendChild(document.createTextNode(t('appointments', 'Back')));
    lcdBF.firstElementChild.addEventListener("click", prevNextDPU);
    lcdBF.firstElementChild.setAttribute('disabled', '');
    lcdBF.lastElementChild.id = "srgdev-dpu_bf-next";
    lcdBF.lastElementChild.appendChild(document.createTextNode(t('appointments', 'Next')));
    lcdBF.lastElementChild.addEventListener("click", prevNextDPU);
    cont.appendChild(lcdBF);
    lcd = document.createElement('div');
    lcd.id = "srgdev-dpu_main-date";
    lcd.className = "srgdev-dpu-bkr-cls";
    lcd.style.left = "0em";
    cont.appendChild(lcd);
    var lcTime = document.createElement('div');
    lcTime.id = "srgdev-dpu_main-time";
    cont.appendChild(lcTime);
    var lcc = 0;
    var rccN = 5;
    var d = new Date();
    var lastUD = -1;
    var an = -1;
    var do_break = false;

    var makeDateCont = function makeDateCont(d, is_empty) {
      var e1 = document.createElement("div");
      e1.id = "srgdev-dpu_dc" + lcc + (is_empty ? "e" : "");
      e1.className = 'srgdev-dpu-date-cont' + (is_empty ? " srgdev-dpu-dc-empty" : "");
      var e2 = document.createElement('span');
      e2.className = 'srgdev-dpu-date-wd';
      e2.appendChild(document.createTextNode(wf(d)));
      e1.appendChild(e2);
      e2 = document.createElement('span');
      e2.className = 'srgdev-dpu-date-dn';
      e2.appendChild(document.createTextNode(d.getDate()));
      e1.appendChild(e2);
      e2 = document.createElement('span');
      e2.className = 'srgdev-dpu-date-md';
      e2.appendChild(document.createTextNode(df(d)));
      e1.appendChild(e2);
      e1.addEventListener('click', dateClick);

      if (lcc === rccN) {
        rccN += 5;
        lcdBF.maxDP++;
        e1.setAttribute("fdsfs", "1");
        if (lcc > min_days) do_break = true;
      }

      lcc++;
      return e1;
    };

    var td = new Date();
    td.setSeconds(1);
    td.setMinutes(0);
    td.setHours(0); // This is Ugly...

    if (pso[PPS_EMPTY] === 1 && pso[PPS_FNED] === 0) {
      // Need to prepend epmty days so the week start on Monday
      var _ts = dta[0].rts;
      d.setTime(_ts);
      d.setTime(_ts + d.getTimezoneOffset() * 60000);
      d.setSeconds(1);
      d.setMinutes(0);
      d.setHours(0);
      var fd = d.getDay();

      if (fd > 0 && fd < 6) {
        td.setTime(d.getTime() - 86400000 * (fd - 1));
      }
    }

    var tu_class; // Time columns

    if (pso[PPS_TIME2] === 1) {
      tu_class = 'srgdev-dpu-time-unit2';
    } else {
      tu_class = 'srgdev-dpu-time-unit';
    }

    for (var _ts2, ti, ets, tts, te, pe, _i2 = 0; _i2 < l; _i2++) {
      _ts2 = dta[_i2].rts;
      if (_ts2 === 0) break;
      d.setTime(_ts2);
      var ud = d.getDate();

      if (lastUD !== ud) {
        // Show "empty" days ...
        tts = td.getTime();
        td.setTime(d.getTime());
        td.setSeconds(1);
        td.setMinutes(0);
        td.setHours(0);
        ets = td.getTime();

        if (pso[PPS_EMPTY] === 1) {
          while (tts < ets) {
            td.setTime(tts); // Deal with weekends

            if (pso[PPS_WEEKEND] === 0) {
              // only show weekdays
              ti = td.getDay();
            } else {
              // show all days
              ti = 1;
            }

            if (ti !== 0 && ti !== 6) {
              lcd.appendChild(makeDateCont(td, true));
              if (do_break) break;
            }

            tts += 86400000;
          }
        }

        if (do_break) {
          d = td;
          break;
        }

        td.setTime(tts + 86400000);
        te = makeDateCont(d, false);

        if (an === -1) {
          an = lcc - 1;
          te.setAttribute('data-active', '');
        }

        lcd.appendChild(te);
        if (do_break) break;
        te = document.createElement('div');
        te.id = "srgdev-dpu_tc" + (lcc - 1);
        te.className = 'srgdev-dpu-time-cont';
        pe = document.createElement('div');
        pe.className = "srgdev-dpu-tc-full-date";
        pe.appendChild(document.createTextNode(wff(d)));
        te.appendChild(pe);
        pe = document.createElement('div');
        pe.setAttribute('data-dm', wft(d));
        pe.className = "srgdev-dpu-tc-tu-wrap";
        te.appendChild(pe);
        lcTime.appendChild(te);
        lastUD = ud;
      }

      te = document.createElement("span");
      te.className = tu_class;
      te.dpuClickID = dta[_i2].idx;
      te.appendChild(document.createTextNode(tf(d)));
      pe.appendChild(te);
    } // fill in empty space


    d.setSeconds(0);
    d.setMinutes(0);
    d.setHours(1);
    d.setTime(d.getTime() + 86400000);
    lcc %= 5;

    if (lcc > 0) {
      for (var _l3 = 5 - lcc % 5, _i3 = 0; _i3 < _l3; _i3++) {
        lcd.appendChild(makeDateCont(d, true));
        d.setTime(d.getTime() + 86400000);
      }
    } // Make empty time cont


    lcdBF = document.createElement('div');
    lcdBF.id = "srgdev-dpu_tce";
    lcdBF.className = 'srgdev-dpu-time-cont';
    lcdBF.appendChild(document.createTextNode(t('appointments', 'No Appointments Available')));
    lcTime.appendChild(lcdBF);
    lcTime.firstElementChild.setAttribute('data-active', '');
    lcd.curActive = an.toString();
    cont.addEventListener("click", timeClick);
    document.getElementById('srgdev-ncfp_sel_cont').appendChild(cont);
  }
})();

/***/ })

/******/ });
//# sourceMappingURL=form.js.map