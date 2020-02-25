(function () {
    "use strict"
    window.addEventListener('DOMContentLoaded',formReady)

    function formReady() {

        let f=document.getElementById("srgdev-ncfp_frm")
        f.addEventListener("submit",formSubmit)

        // chrome bfcache
        setTimeout(function (){f.autocomplete="on"},1000)


        makeDpu()
        document.getElementById("srgdev-ncfp_sel-dummy").addEventListener("click",selClick)

        setTimeout(function () {
            let b=document.getElementById("srgdev-ncfp_fbtn")
            b.disabled=true;
            b.textContent="Session Timeout. Reload."
        },900000)
    }

    function clearFormErr() {
        this.setCustomValidity('')
        if(this.getAttribute('err')){
            this.removeAttribute('err')
            this.removeEventListener("focus",clearFormErr,false)
        }else{
            this.removeEventListener("input",clearFormErr,false)
        }
    }

    function formSubmit(e){
        let lee=0
        let el=document.getElementById("srgdev-ncfp_fbtn")
        if(el.disabled===true){
            e.preventDefault()
            e.stopPropagation()
            return false
        }

        el=document.getElementById("srgdev-ncfp_sel-hidden")
        if (el.options[el.selectedIndex].getAttribute('data-ts')==='0'){
            el=document.getElementById("srgdev-ncfp_sel-dummy")
            el.setAttribute('err','err');
            el.addEventListener("focus",clearFormErr,false)
            lee=1
        }
        el=document.getElementById("srgdev-ncfp_fname")
        if (el.value.length<3){
            el.setCustomValidity("Name is required.");
            el.addEventListener("input",clearFormErr,false)
            lee=1
        }
        el=document.getElementById("srgdev-ncfp_femail")
        if (el.value.length<5 || el.value.indexOf("@")===-1 || el.value.indexOf("@")>el.value.lastIndexOf(".")){
            el.setCustomValidity("Email is required.");
            el.addEventListener("input",clearFormErr,false)
            lee=1
        }
        // match [0-9], '.()-+,/' and ' ' (space) at least 9 digits
        el=document.getElementById("srgdev-ncfp_fphone")
        if (el.value==='' || el.value.length<9 || /^[0-9 .()\-+,/]*$/.test(el.value)===false){
            el.setCustomValidity("Phone number is required.");
            el.addEventListener("input",clearFormErr,false)
            lee=1
        }

        if(lee!==0){
            e.preventDefault()
            e.stopPropagation()
            return false
        }
    }



    function selClick(e) {
        let elm=document.getElementById("srgdev-dpu_main-cont")
        if(elm.getAttribute("data-open")===null){
            elm.setAttribute("data-open",'')
        }else{
            elm.removeAttribute("data-open")
        }
        e.preventDefault()
        return false
    }

    function dateClick() {
        let n=this.id.slice(-1)
        let c=this.parentElement.curActive
        if(c===n) return

        document.getElementById('srgdev-dpu_dc'+c)
            .removeAttribute('data-active');
        document.getElementById('srgdev-dpu_dc'+n).setAttribute('data-active','')

        document.getElementById('srgdev-dpu_tc'+c)
            .removeAttribute('data-active');
        document.getElementById('srgdev-dpu_tc'+n).setAttribute('data-active','')

        this.parentElement.curActive=n
    }

    function timeClick(e) {
        let t=e.target
        if(t.dpuClickID!==undefined){
            document.getElementById('srgdev-ncfp_sel-dummy').value=t.parentElement.getAttribute('data-dm')+' - '+t.textContent;
            document.getElementById('srgdev-ncfp_sel-hidden').selectedIndex=t.dpuClickID
            document.getElementById("srgdev-dpu_main-cont").removeAttribute("data-open")
        }
    }

    function makeDpu() {

        let s=document.getElementById('srgdev-ncfp_sel-hidden')
        if(s.getAttribute("data-state")!=='2'){
            console.log("data-state: ",s.getAttribute("data-state"))
            return
        }

        let tf
        if(window.Intl && typeof window.Intl === "object") {
            let f = new Intl.DateTimeFormat([],
                {hour: "numeric", minute: "2-digit"})
            tf=f.format
        }else{
            tf=function (d) {
                return d.toLocaleTimeString()
            }
        }
        let df
        if(window.Intl && typeof window.Intl === "object") {
            let f = new Intl.DateTimeFormat([],
                {month: "short", day: "2-digit", year:"numeric"})
            df=f.format
        }else{
            df=function (d) {
                return d.toLocaleDateString()
            }
        }
        let wf
        if(window.Intl && typeof window.Intl === "object") {
            let f = new Intl.DateTimeFormat([],
                {weekday: "long"})
            wf=f.format
        }else{
            wf=function (d) {
                return ''
            }
        }

        // temp (last option to finalize the loop)
        let cont=document.createElement('option')
        cont.setAttribute('data-ts','0')
        s.appendChild(cont)

        let opts=s.options
        let l=opts.length

        s.selectedIndex=l-1

        cont=document.createElement('div')
        cont.id="srgdev-dpu_main-cont"
        cont.className="srgdev-dpu-bkr-cls"

        let lcd=document.createElement('div')
        lcd.id="srgdev-dpu_main-date"
        lcd.className="srgdev-dpu-bkr-cls"

        let lcc=0

        let lastUD=-1;
        let d=new Date()
        let tzOffset=d.getTimezoneOffset()*60000
        for(let txt,te,pe,ce,i=0;i<l;i++){
            let ts= opts[i].getAttribute('data-ts')*1000
            d.setTime(ts+tzOffset)

            let ud=d.getUTCDate()
            if(lastUD!==ud){
                if(lastUD!==-1){
                    cont.appendChild(pe)
                    if(ts===0) break // the end
                }

                ce=document.createElement("div")
                ce.id="srgdev-dpu_dc"+lcc
                ce.className='srgdev-dpu-date-cont'

                te=document.createElement('span')
                te.className='srgdev-dpu-date-wd'
                te.appendChild(document.createTextNode(wf(d)))
                ce.appendChild(te)

                txt=df(d)
                te=document.createElement('span')
                te.className='srgdev-dpu-date-md'
                te.appendChild(document.createTextNode(txt))
                ce.appendChild(te)
                ce.addEventListener('click',dateClick)
                lcd.appendChild(ce)

                pe=document.createElement('div')
                pe.id="srgdev-dpu_tc"+lcc
                pe.setAttribute('data-dm',txt)
                pe.className='srgdev-dpu-time-cont'

                lcc++
                lastUD=ud
            }
            te=document.createElement("span")
            te.className="srgdev-dpu-time-unit"
            te.dpuClickID=i
            te.appendChild(document.createTextNode(tf(d)))
            pe.appendChild(te)
        }

        cont.firstElementChild.setAttribute('data-active','')
        lcd.firstElementChild.setAttribute('data-active','')
        lcd.curActive='0'
        cont.appendChild(lcd)
        cont.addEventListener("click", timeClick)
        document.getElementById('srgdev-ncfp_sel_cont').appendChild(cont)

    }

})()
