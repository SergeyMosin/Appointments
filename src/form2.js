(function () {
    "use strict"
    window.addEventListener('DOMContentLoaded',formReady)

    function formReady() {
        document.getElementById("f2_btn").addEventListener("click",goBtn)
    }


    function goBtn() {

        let url="http://127.0.0.1:8080/remote.php/dav/calendars/Serioga/cal-m/"


        let body=
'<?xml version="1.0" encoding="utf-8" ?>'+
'<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">' +
 '<D:prop>' +
  '<C:calendar-data/>' +
 '</D:prop>' +
 '<C:filter>' +
  '<C:comp-filter name="VCALENDAR">' +
   '<C:comp-filter name="VEVENT">' +
    '<C:time-range start="20200308T050000Z" end="20200315T040000Z"/>' +
   '</C:comp-filter>' +
  '</C:comp-filter>' +
 '</C:filter>' +
'</C:calendar-query>';

    //     let body='<?xml version="1.0" encoding="utf-8" ?>\n' +
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
        oReq.setRequestHeader("Depth","1")
        oReq.setRequestHeader("Content-Type","text/xml; charset=utf-8")
        // oReq.open("GET", ug);
        oReq.send(body);
    }

    function reqLoad () {
        console.log(this);
    }

    function reqError (e) {
        console.log(e);
    }



})()
