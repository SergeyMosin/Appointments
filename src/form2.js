(function () {
    "use strict"
    window.addEventListener('DOMContentLoaded',formReady)

    function formReady() {
        document.getElementById("f2_btn").addEventListener("click",goBtn)
    }


    function goBtn() {

        let url="http://cpe-74-72-54-44.nyc.res.rr.com:8080/remote.php/dav/calendars/Serioga/cal-m/0E8EE874-FC6D-4642-AFC3-D5807911CF00.ics"

        var oReq = new XMLHttpRequest();
        oReq.addEventListener("load", reqLoad);
        oReq.addEventListener("error", reqError);
        oReq.open("GET", url);
        oReq.send();

    }

    function reqLoad () {
        console.log(this);
    }

    function reqError (e) {
        console.log(e);
    }



})()
