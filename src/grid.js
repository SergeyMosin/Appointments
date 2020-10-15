function _apptGridMaker() {
    // !!! meke sure that the .grid-line height is 2px less thant this is !!!
    const LINE_HEIGHT_5M = 6
    const MPH = 3600000
    const MP5 = 300000
    // Start at 8AM
    const SH = 8
    // 11 hours
    const DH = 14

    let sP

    const mData = {
        /** @type HTMLDivElement */
        ce: null,
        uOffset: -1,
        /** @type HTMLElement[] */
        elms: [],

        /** @type HTMLElement */
        curDrag: null,
        diff: -1,
        uMax: -1,

        /** @type HTMLElement */
        apptLayer: null,
        /** @type HTMLElement */
        gridLayer: null,


        /** @type {number[]} */
        column_pos: undefined,
        /** @type {Array} */
        column_elm: undefined,

        // this is kind of bad...
        /** @type {Array[]} */
        mc_pos: [],
        /** @type {Array[]} */
        mc_elm: [],

        /** @type {HTMLElement[]} */
        mc_cols: []
    }

    /**
     * @param {HTMLElement} cont
     * @param {number} colCnt
     * @param {string} stylePrefix
     */
    function setup(cont,colCnt,stylePrefix="") {
        sP=stylePrefix
        let elm=document.createElement('div')
        elm.className=sP+'grid_layer'
        cont.appendChild(elm)
        mData.gridLayer = elm

        elm=document.createElement('div')
        elm.className=sP+'appt_layer'
        cont.appendChild(elm)
        mData.apptLayer = elm
        makeHGrid()
        makeColumns(colCnt)
    }


    /**
     * @param {number} start start time slot 8:00AM = 0, 8:05AM = 1, etc...
     * @param {number} len duration in minutes
     * @param {number} cnt number of appointments
     * @param {number} cID column ID 0=Monday, 1=Tuesday, etc...
     * @param {string} clr background color
     */
    function addAppt(start, len, cnt, cID, clr) {

        // For now its one shot only, need to reset and add again :(
        if (mData.mc_pos[cID].length !== 0) {
            return
        }

        if (len < 5) len = 5
        else if (len > 120) len = 120

        let uLen = Math.floor(len / 5)
        let uMax = mData.uMax - uLen + 1

        if (start < 0) start = 0
        else if (start > uMax) return

        let f = document.createDocumentFragment()
        for (let uTop, i = 0; i < cnt; i++) {
            uTop = i * uLen + start
            if (uTop > uMax) break

            f.appendChild(makeApptElement(uTop, uLen, i, cID, clr))
        }

        mData.mc_cols[cID].appendChild(f)
    }

    /**
     * @param {number} fromCID
     * @param {number} toCID
     * @param {string} clr background color
     */
    function cloneColumns(fromCID, toCID, clr) {
        if (mData.mc_pos[toCID].length !== 0) {
            return
        }
        let f = document.createDocumentFragment()
        mData.mc_elm[fromCID].forEach((a, i) => {
            f.appendChild(makeApptElement(a.uTop, a.uLen, i, toCID, clr))
        })
        mData.mc_cols[toCID].appendChild(f)
    }

    /**
     * Soft reset (keeps past appointments)
     * @param {number} cID
     */
    function resetColumn(cID) {
        let p = mData.mc_cols[cID]
        for(let els=mData.mc_elm[cID],c=els.length,i=0;i<c;i++){
                p.removeChild(els[i])
                els[i]=null; //???
        }
        mData.mc_pos[cID] = []
        mData.mc_elm[cID] = []
    }

    /**
     * Hard reset, deletes all elements including past appointments
     */
    function resetAllColumns() {
        for(let i=0,l=mData.mc_cols.length;i<l;i++){
            mData.mc_pos[i] = []
            mData.mc_elm[i] = []
            let p = mData.mc_cols[i]
            while (p.lastElementChild){
                p.removeChild(p.lastElementChild)
            }
        }
    }


    function makeApptElement(uTop, uLen, idx, cID, clr) {
        let elm = document.createElement('div')
        elm.className = sP+'appt'
        elm.style.backgroundColor=clr
        if(idx!==null) {
            elm.uTop = uTop
            elm.uLen = uLen
            elm.cIdx = idx
            elm.cID = cID
        }else{
            elm.className+=" "+sP+"appt-empty"
        }

        let ge = mData.elms[uTop]
        let e2 = mData.elms[uTop + uLen - 1]

        elm.style.top = ge.offsetTop + 'px'
        elm.style.height = (e2.offsetTop + e2.offsetHeight - ge.offsetTop - .25) + 'px'

        let et = document.createElement('div')
        et.className = sP+"appt_txt"
        et.appendChild(document.createTextNode(ge.dxt + ' - ' + mData.elms[uTop + uLen].dxt))
        elm.appendChild(et)

        if(idx!==null) {

            // TODO: delegate these events to the parent ???
            elm.addEventListener("mousedown", appGoDrag)

            mData.mc_pos[cID].push(uTop, uTop + uLen - 1)
            mData.mc_elm[cID].push(elm)

        }
        return elm
    }

    function addPastAppts(data,clr) {

        const btm=DH*12; // 12*5min=1hour
        const pd=data.split(String.fromCharCode(31))
        for(let pds,j=0,ll=pd.length;j<ll;j++) {
            pds=pd[j]
            if(pds.length<3) continue
            if(j>0){
                const sep=pds.indexOf(String.fromCharCode(30))
                if(sep===-1) continue
                clr=pds.substr(0,sep)
                pds=pds.substr(sep+1)
            }
            for (let sp, tzo, ets, elm, uTop, d = new Date(), ds, uLen, cID, da = pds.split(","),
                     l = da.length, i = 0; i < l; i++) {
                ds = da[i]

                sp = ds.indexOf(":", 8);

                //get end time first
                d.setTime(ds.substr(sp + 2) * 1000)

                tzo = d.getTimezoneOffset()
                if (ds.charAt(0) === "F") {
                    tzo *= 60000
                } else {
                    tzo = 0
                }

                ets = d.getTime() + tzo

                // start
                d.setTime(ds.substr(1, sp - 1) * 1000 + tzo)

                uLen = Math.floor((ets - d.getTime()) / 300000)

                cID = d.getDay() - 1
                if(cID<0){
                    // this is sunday
                    continue
                }
                // console.log("cID:",cID)

                uTop = Math.floor((((d.getHours() - 8) * 60) / 5)
                    + ((d.getMinutes() / 5)))

                if (uTop >= 0 && uTop + uLen <= btm) {
                    elm = makeApptElement(uTop, uLen, null, cID, clr)
                    mData.mc_cols[cID].appendChild(elm)
                }
            }
        }
    }



    function appGoDrag(e) {

        const cID = this.cID
        if (cID === undefined) return
        mData.column_pos = mData.mc_pos[cID]
        mData.column_elm = mData.mc_elm[cID]

        // mData.gridLayer.style.cursor = 'grabbing'
        mData.mc_cols[cID].style.pointerEvents = "none"

        mData.curDrag = this
        mData.diff = e.offsetY

        window.addEventListener('mouseup', apptStopDrag)

        e.stopPropagation()
        e.preventDefault()
    }

    function apptStopDrag() {
        mData.mc_cols[mData.curDrag.cID].style.pointerEvents = "all"
        // mData.gridLayer.style.cursor = 'default'

        mData.diff = -1
        mData.uOffset = -1
        if (mData.ce !== null) {
            mData.ce.removeAttribute('top_ok')
            mData.ce = null
        }
        mData.curDrag = null
        window.removeEventListener('mouseup', apptStopDrag)
    }

    function gridMouseEvt() {
        if (mData.diff < 0 || mData.curDrag === null) return
        const md = mData

        if (md.uOffset === -1) {
            let se = (this.mIdx - Math.floor(md.diff / (this.offsetHeight))) - 1 // -1 just in case
            if (se < 0) se = 0
            let trgO = md.elms[this.mIdx].offsetTop - md.diff
            for (let c, l = md.uMax; se < l; se++) {
                c = md.elms[se]
                if (trgO < md.elms[se].offsetTop) {
                    md.uOffset = this.mIdx - se
                    break
                }
            }
        }

        let idx = this.mIdx - md.uOffset

        if (idx < 0) idx = 0
        else if (idx > md.uMax) idx = md.uMax

        if (md.curDrag.uTop === idx) return

        let elm = md.elms[idx]

        if (md.ce !== null) {
            md.ce.removeAttribute('top_ok')
        }

        if (elm !== undefined) {
            elm.setAttribute('top_ok', '')
            md.ce = elm

            let de = md.curDrag
            let stackStart = de.cIdx
            let stackEnd = stackStart + 1
            // Move stack...
            if (de.uTop < idx) {
                // moving down
                let uo = de.uLen - 1
                for (let i = stackEnd, clm = md.column_pos, l = clm.length; i < l; i++) {
                    let ci = i * 2
                    // compare to the previous one
                    // bottom of prev >= top of cur
                    if (clm[ci - 1] + 1 >= clm[ci]) {
                        uo += md.column_elm[stackEnd].uLen
                        stackEnd++
                    } else break
                }

                if (idx + uo > md.uMax) idx = md.uMax - uo

            } else {
                // moving up
                for (let i = stackStart, clm = md.column_pos; i > 0; i--) {
                    let ci = i * 2
                    // compare to the previous one
                    // bottom of prev >= top of cur
                    if (clm[ci - 1] + 1 >= clm[ci]) {
                        stackStart--
                        idx -= md.column_elm[stackStart].uLen
                    } else break
                }
                if (idx < 0) idx = 0
            }

            for (let ge, el, els = md.column_elm, i = stackStart; i < stackEnd; i++) {
                ge = md.elms[idx]
                el = els[i]

                // Set txt
                el.firstElementChild.textContent = ge.dxt + ' - ' + md.elms[idx + el.uLen].dxt

                el.uTop = idx
                el.style.top = ge.offsetTop + 'px'
                let ci = i * 2
                md.column_pos[ci] = idx
                md.column_pos[ci + 1] = idx + el.uLen - 1
                idx += el.uLen

            }
        } else {
            md.ce = null
        }
    }

    /**
     * @param n number of columns
     */
    function makeColumns(n) {
        for (let al = mData.apptLayer, elm,
                 w = Math.floor((100 - 1) / n) + "%", i = 0; i < n; i++) {
            elm = document.createElement('div')
            elm.className = sP+"appt_columns"
            elm.style.width = w
            al.appendChild(elm)
            mData.mc_cols[i] = elm
            mData.mc_pos[i] = []
            mData.mc_elm[i] = []
        }
    }

    function makeHGrid() {
        // 5 Min will take 5px
        const STEP = LINE_HEIGHT_5M

        // Ever 3rd line visible i.e. 15min
        const VS_LINE = 2
        const lang=document.documentElement.lang
       let timeFormat
        if(window.Intl && typeof window.Intl === "object") {
            let f = new Intl.DateTimeFormat([lang],
                {hour: "numeric", minute: "2-digit"})
            timeFormat=f.format
        }else{
            timeFormat=function (d) {
                return d.toLocaleTimeString()
            }
        }

        let f = document.createDocumentFragment()

        // DH*12 = 12 5 minute sections per hour * DH
        let d = new Date()
        // let tzo = d.getTimezoneOffset() * 60000
        d.setMilliseconds(0)
        d.setSeconds(0)
        d.setMinutes(0)
        d.setHours(SH)
        let tss = d.getTime()

        for (let els = mData.elms, vc = VS_LINE, dxt, ce,
                 l = DH * 12, i = 0; i < l; i++) {

            ce = document.createElement('div')
            let tzo = d.getTimezoneOffset() * 60000
            d.setTime(tss)
            dxt = timeFormat(d)
            if (vc === VS_LINE) {
                ce.className = sP+"grid-line "+sP+"line-vis"
                ce.appendChild(document.createTextNode(dxt))
                vc = 0
            } else {
                ce.className = sP+"grid-line "+sP+"line-hid"
                vc++
            }
            ce.style.top = (i * STEP) + 'px'
            ce.tss = tss - tzo
            ce.dxt = dxt
            ce.mIdx = i
            ce.addEventListener("mouseenter", gridMouseEvt)
            els[i] = ce
            f.appendChild(ce)


            tss += MP5
        }
        mData.uMax = mData.elms.length - 1

        // This is a special line we just need it for the "dxt" string
        let el = document.createElement('div')
        d.setTime(tss)
        el.dxt = timeFormat(d)
        mData.elms.push(el)

        let tbl = mData.gridLayer
        tbl.style.height = ((mData.elms.length-1) * STEP) + 'px'

        tbl.appendChild(f)
    }

    function getStarEnds(ts,add_offset) {

        //For start and end need this: YYYYMMDDTHHMMSS
        function makeT(d) {
            const h=d.getHours()
            const m=d.getMinutes()
            return "T"+(h<10?"0"+h:""+h)+(m<10?"0"+m:""+m)+"00"
        }
        function makeD(d) {
            const month = d.getMonth() + 1
            const day = d.getDate()
            return d.getFullYear()
                + (month < 10 ? "0" + month : "" + month)
                + (day < 10 ? "0" + day : "" + day)
        }

        const day_start_ms=MPH*SH
        const ms_per_day=MPH*24

        // First element is the "create date" in UTC
        let r=[(new Date).toISOString().slice(0,-5).replace(/[\-:]/g,'')+'Z']
        let rc=0
        for(let d=new Date(),ds_ts,dst,i=0,l=mData.mc_cols.length;i<l;i++){
            ds_ts=ts+day_start_ms+ms_per_day*i
            d.setTime(ds_ts)
            if(add_offset){
                d.setTime(d.getTime()+d.getTimezoneOffset()*60000)
            }
            dst=makeD(d)
            for(let ofs=0,pa=mData.mc_pos[i], j=0,k=pa.length;j<k;j+=2){
                // Start
                d.setTime(pa[j]*MP5+ds_ts)
                if(add_offset){
                    ofs=d.getTimezoneOffset()*60000
                    d.setTime(d.getTime()+ofs)
                }
                r[++rc]=dst+makeT(d)
                // End
                d.setTime((pa[j+1]+1)*MP5+ds_ts+ofs)
                r[++rc]=dst+makeT(d)
            }
        }
        return r
    }


    return{
        setup:setup,
        addAppt:addAppt,
        cloneColumns:cloneColumns,
        resetColumn:resetColumn,
        resetAllColumns:resetAllColumns,
        getStarEnds:getStarEnds,
        addPastAppts:addPastAppts
    }
}

export default new _apptGridMaker()
