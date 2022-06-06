(function () {
    "use strict"
    window.addEventListener('DOMContentLoaded', formReady)

    function formReady() {
        let gdpr = document.getElementById('appt_gdpr_id')
        if (gdpr !== null) {
            gdpr.addEventListener('change', gdprCheck)
            gdprCheck.apply(gdpr)
        }

        let f = document.getElementById("srgdev-ncfp_frm")
        f.addEventListener("submit", formSubmit)

        // chrome bfcache
        setTimeout(function () {
            f.autocomplete = "on"
        }, 1000)


        makeDpu(f.getAttribute("data-pps"))
        document.getElementById("srgdev-ncfp_sel-dummy").addEventListener("click", selClick)

        setTimeout(function () {
            let b = document.getElementById("srgdev-ncfp_fbtn")
            let txt
            // translations are done on the backend (bug???)
            if (b.hasAttribute("data-tr-ses-to")) {
                txt = b.getAttribute('data-tr-ses-to')
            } else {
                // Back-up ????
                txt = 'Session Timeout. Reload.';
            }
            b.disabled = true;
            b.textContent = txt
        }, 900000)
    }

    function gdprCheck() {
        let btn = document.getElementById("srgdev-ncfp_fbtn")
        if (this.checked) {
            if (btn.hasAttribute('shade')) btn.removeAttribute('shade')
        } else {
            if (!btn.hasAttribute('shade')) btn.setAttribute('shade', "1")
        }

        if (this.hasAttribute("err")) {
            this.removeAttribute("err")
        }
        if (this.hasAttribute("required")) {
            this.removeAttribute("required")
        }

    }

    function clearFormErr() {
        this.setCustomValidity('')
        if (this.getAttribute('err')) {
            this.removeAttribute('err')
            this.removeEventListener("focus", clearFormErr, false)
        } else {
            this.removeEventListener("input", clearFormErr, false)
        }
    }

    function formSubmit(e) {
        let lee = 0

        let el = document.getElementById("srgdev-ncfp_fbtn")
        if (el.disabled === true) {
            e.preventDefault()
            e.stopPropagation()
            return false
        }

        el = document.getElementById("srgdev-ncfp_sel-hidden")
        let sdx = el.selectedIndex
        let tzi
        if (sdx === -1 || el.value === "") {
            el = document.getElementById("srgdev-ncfp_sel-dummy")
            el.setAttribute('err', 'err');
            el.addEventListener("focus", clearFormErr, false)
            lee = 1
        } else {
            tzi = el.dataRef[sdx].tzi
        }

        el = document.getElementById("srgdev-ncfp_talk_type")
        if (el !== null) {
            sdx = el.selectedIndex
            if (sdx !== 1 && sdx !== 2) {
                el.setAttribute('err', 'err');
                el.addEventListener("focus", clearFormErr, false)
                lee = 1
            }
        }

        el = document.getElementById("srgdev-ncfp_fname")
        if (el.value.length < 3) {
            el.setCustomValidity(t('appointments', 'Name is required.'));
            el.addEventListener("input", clearFormErr, false)
            lee = 1
        }
        el = document.getElementById("srgdev-ncfp_femail")
        if (el.value.length < 5 || el.value.indexOf("@") === -1 || el.value.indexOf("@") > el.value.lastIndexOf(".")) {
            el.setCustomValidity(t('appointments', 'Email is required.'));
            el.addEventListener("input", clearFormErr, false)
            lee = 1
        }
        // Phone field is optional
        // match [0-9], '.()-+,/' and ' ' (space) at least 9 digits
        el = document.getElementById("srgdev-ncfp_fphone")
        if (el !== null && (el.value === '' || el.value.length < 9 || /^[0-9 .()\-+,/]*$/.test(el.value) === false)) {
            el.setCustomValidity(t('appointments', 'Phone number is required.'));
            el.addEventListener("input", clearFormErr, false)
            lee = 1
        }

        //Check for custom inputs
        let elms = document.getElementById('srgdev-ncfp-main-inputs').children
        for (let i = 0, elm, l = elms.length; i < l; i++) {
            elm = elms[i]
            if (elm.hasAttribute('data-more')) {
                if (elm.tagName === 'INPUT' || elm.tagName === 'TEXTAREA') {
                    let cv = elm.value.trim()
                    if (elm.getAttribute('data-more') === 'r1' && cv === '') {
                        elm.setCustomValidity(t('appointments', 'Required.'));
                        elm.addEventListener("input", clearFormErr, false)
                        lee = 1
                    } else if (elm.hasAttribute('type') && elm.getAttribute('type') === 'number' && isNaN(cv)) {
                        elm.setCustomValidity(t('appointments', 'Number required.'));
                        elm.addEventListener("input", clearFormErr, false)
                        lee = 1
                    }
                }
            }
        }


        el = document.getElementById('appt_gdpr_id')
        if (el !== null && el.checked === false) {
            el.setAttribute("err", "err")
            el.setAttribute("required", "1")
            lee = 1
        }

        if (lee !== 0) {
            e.preventDefault()
            e.stopPropagation()
            return false
        }
        document.getElementById("srgdev-ncfp_fbtn-spinner").style.display = "inline-block"
        el = document.createElement("input")
        el.type = "hidden"
        el.name = "tzi"
        el.value = tzi
        this.appendChild(el)
    }

    function selClick(e) {
        let elm = document.getElementById("srgdev-dpu_main-cont")
        if (elm.getAttribute("data-open") === null) {
            elm.setAttribute("data-open", '')
        } else {
            elm.removeAttribute("data-open")
        }
        e.preventDefault()
        return false
    }

    function dateClick(e) {

        let n = this.id.slice(13)
        let c = this.parentElement.curActive
        if (c === n) return
        document.getElementById('srgdev-dpu_dc' + c)
            .removeAttribute('data-active');
        document.getElementById('srgdev-dpu_dc' + n).setAttribute('data-active', '')
        this.parentElement.curActive = n
        if (n.slice(-1) === 'e') n = 'e'
        if (c.slice(-1) === 'e') c = 'e'

        document.getElementById('srgdev-dpu_tc' + c)
            .removeAttribute('data-active');
        document.getElementById('srgdev-dpu_tc' + n).setAttribute('data-active', '')

        e.stopPropagation()
    }


    function timeClick(e) {
        let t = e.target

        if (t.parentElement.dpuClickID !== undefined) {
            t = t.parentElement;
        }

        if (t.dpuClickID !== undefined) {
            document.getElementById('srgdev-ncfp_sel-dummy').value = t.parentElement.getAttribute('data-dm') + ' - ' + t.timeAt;
            let elm = document.getElementById('srgdev-ncfp_sel-hidden')
            elm.selectedIndex = t.dpuClickID
            elm.value = elm.dataRef[t.dpuClickID].d

            const dur = elm.dataRef[t.dpuClickID].dur
            elm = document.getElementById('srgdev-ncfp_dur-cont')

            const hrStr = elm.getAttribute("data-tr-hr")
            const mnStr = elm.getAttribute("data-tr-mn")
            const makeHrMin = function (v) {
                let hrMinStr = ""
                const hours = (v / 60) | 0
                if (hours > 0) {
                    hrMinStr += hours + hrStr + " "
                }
                return hrMinStr + (v - hours * 60) + mnStr
            }
            if (dur === null || dur.length === 1) {
                elm.style.display = 'none'
            } else {
                const opts = elm.lastElementChild.children
                const tr = window.t('appointments', 'Minutes')
                opts[0].textContent = makeHrMin(dur[0])
                for (let o, i = 1, hours = 0, minutes = 0, l = Math.max(opts.length, dur.length); i < l; i++) {
                    if (i >= opts.length) {
                        // create
                        o = document.createElement('option')
                        o.className = 'srgdev-ncfp-form-option'
                        o.appendChild(document.createTextNode(''))
                        elm.lastElementChild.appendChild(o)
                    } else {
                        o = opts[i]
                        if (i >= dur.length) {
                            o.style.display = 'none'
                            continue
                        }
                    }
                    o.style.display = 'block'
                    o.value = i
                    o.textContent = makeHrMin(dur[i])
                }
                elm.style.display = 'block'
            }
            elm.lastElementChild.value = 0

            document.getElementById("srgdev-dpu_main-cont").removeAttribute("data-open")
        }
    }

    function prevNextDPU(e) {
        let p
        // e.target===undefined when we do initial "scroll" @see makeDpu()
        if (e.target !== undefined) {
            p = e.target.parentElement
            if (e.target.id === "srgdev-dpu_bf-back") {
                if (p.curDP > 0) p.curDP--
            } else {
                if (p.curDP < p.maxDP) p.curDP++
                if (p.curDP === p.maxDP) {
                    e.target.setAttribute('disabled', '')
                } else {
                    e.target.removeAttribute('disabled')
                }
            }
        } else {
            p = e;
        }
        if (p.curDP === 0) {
            p.firstElementChild.setAttribute('disabled', '')
        } else {
            p.firstElementChild.removeAttribute('disabled')
        }

        if (p.curDP === p.maxDP) {
            p.lastElementChild.setAttribute('disabled', '')
        } else {
            p.lastElementChild.removeAttribute('disabled')
        }

        // TODO: find first not empty and select it ?

        document.getElementById("srgdev-dpu_main-date").style.left = "-" + (p.curDP * 5 * 4.6) + "em"
    }

    function addSwipe(cont, bfc) {
        cont.touchInfo = {x: 0, y: 0, id: -1}
        cont.bfNav = bfc
        cont.addEventListener("touchstart", swipeStart)
        cont.addEventListener("touchend", swipeEnd)

    }

    /** @param {TouchEvent} e */
    function swipeStart(e) {
        if (e.changedTouches !== undefined && e.changedTouches.length > 0) {
            const cc = e.changedTouches[0]
            const ti = this.touchInfo
            ti.x = cc.clientX
            ti.y = cc.clientY
            ti.id = cc.identifier
        }
    }

    /** @param {TouchEvent} e */
    function swipeEnd(e) {
        if (e.changedTouches !== undefined && e.changedTouches.length > 0) {
            const cc = e.changedTouches[0]
            const ti = this.touchInfo
            if (cc.identifier === ti.id) {
                const dx = (cc.clientX - ti.x) | 0
                const dy = (cc.clientY - ti.y) | 0
                let t = dx >> 31
                let dx_abc = ((dx + t) ^ t)
                t = dy >> 31
                if (dx_abc > ((dy + t) ^ t) && dx_abc > 50) {
                    if (dx < 0) {
                        // swipe left - push next
                        this.bfNav.lastElementChild.click()
                    } else {
                        // swipe right - push prev
                        this.bfNav.firstElementChild.click()
                    }
                }
            }
            ti.id = -1
        }
    }


    function makeDpu(pps) {

        const PPS_NWEEKS = "nbrWeeks";
        const PPS_EMPTY = "showEmpty";
        const PPS_FNED = "startFNED";
        const PPS_WEEKEND = "showWeekends";
        const PPS_SHOWTZ = "showTZ";
        const PPS_TIME2 = "time2Cols";
        const PPS_END_TIME = "endTime";

        let pso = {}
        let ta = pps.split('.')
        for (let a, l = ta.length, i = 0; i < l; i++) {
            a = ta[i].split(':')
            pso[a[0]] = +a[1]
        }

        let min_days = 7 * pso[PPS_NWEEKS]

        let s = document.getElementById('srgdev-ncfp_sel-hidden')
        if (s.getAttribute("data-state") !== '2') {
            console.log("data-state: ", s.getAttribute("data-state"))
            return
        }
        // There is a problem with js translations without Vue, so just get it from PHP for now
        const dpuTrHdr = s.getAttribute("data-hdr")
        const dpuTrBack = s.getAttribute("data-tr-back")
        const dpuTrNext = s.getAttribute("data-tr-next")
        const dpuTrNA = s.getAttribute("data-tr-not-available")

        let mn
        let dn

        if (window.monthNames !== undefined) {
            mn = window.monthNames
        } else {
            mn = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]
        }
        if (window.dayNames !== undefined) {
            dn = window.dayNames
        } else {
            dn = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]
        }

        const has_intl = window.Intl && typeof window.Intl === "object"
        const lang = document.documentElement.hasAttribute('data-locale')
            ? [document.documentElement.getAttribute('data-locale').replaceAll('_', '-'), document.documentElement.lang]
            : [document.documentElement.lang]

        let tf
        if (has_intl) {
            let f = new Intl.DateTimeFormat(lang,
                {hour: "numeric", minute: "2-digit"})
            tf = f.format
        } else {
            tf = function (d) {
                return d.toLocaleTimeString()
            }
        }

        let df
        if (has_intl) {
            let f = new Intl.DateTimeFormat(lang,
                {month: "long"})
            df = f.format
        } else {
            df = function (d) {
                return mn[d.getMonth()]
            }
        }

        let wf
        if (has_intl) {
            let f = new Intl.DateTimeFormat(lang,
                {weekday: "short"})
            wf = f.format
        } else {
            wf = function (d) {
                return dn[d.getDay()]
            }
        }

        let wft
        if (has_intl) {
            let f = new Intl.DateTimeFormat(lang,
                {weekday: "short", month: "long", day: "2-digit"})
            wft = f.format
        } else {
            wft = function (d) {
                return d.toDateString()
            }
        }

        let wff
        if (has_intl) {
            let f = new Intl.DateTimeFormat(lang,
                {weekday: "long", month: "long", day: "numeric", year: "numeric"})
            wff = f.format
        } else {
            wff = function (d) {
                return d.toLocaleDateString()
            }
        }

        let dta = []
        let tzn = undefined
        if (has_intl) {
            try {
                tzn = Intl.DateTimeFormat().resolvedOptions().timeZone
            } catch (e) {
                console.log("no Intl timeZone ", e)
            }
            if (typeof tzn !== "string") tzn = undefined
        }

        for (let md = new Date(), tzo, tzi, t, tStr, atStr, sp, sp2, dur, dur_idx,
                 ts, endTime = pso[PPS_END_TIME],
                 ia = s.getAttribute("data-info").split(','),
                 l = ia.length, i = 0, ds; i < l; i++) {

            //TODO: remove 'U' from ds ????
            ds = ia[i]

            sp = ds.indexOf(":", 8);
            md.setTime(+ds.substr(1, sp - 1) * 1000)
            tzo = md.getTimezoneOffset()

            // t='U' in simple and external modes
            // t='T' in template mode
            t = ds.charAt(0)

            tStr = atStr = tf(md)

            ts = md.getTime()

            dur = null
            if (endTime === 1 || t === 'T') {
                sp2 = sp + 1
                // sp must be the pos of the last used ':'
                sp = ds.indexOf(":", sp2)

                if (t === "T") {
                    dur = ds.substr(sp2, sp - sp2).split(';').map(n => n | 0)
                    if (endTime === 1 && dur.length < 2) {
                        md.setTime(ts + dur[0] * 60000)
                        tStr += ' - ' + tf(md)
                    }
                } else {
                    sp2 = +ds.substr(sp2, sp - sp2) * 1000
                    md.setTime(sp2)
                    tStr += ' - ' + tf(md)
                }
            }

            if (tzn !== undefined) {
                tzi = t + tzn
            } else {
                // fallback, needs to be done for every date because of daylight savings
                let ao = Math.abs(tzo)
                let h = Math.floor(ao / 60)
                let m = ao - h * 60
                // offset sign is reversed https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/getTimezoneOffset
                tzi = t + (tzo > 0 ? '-' : '+') + (h < 10 ? '0' + h : h) + (m < 10 ? '0' + m : m)
            }

            sp++
            sp2 = ds.indexOf(":", sp)
            dta[i] = {
                rts: ts,
                d: ds.substr(sp, sp2 - sp),
                t: ds.substr(sp2 + 2), // +2 is for ":_"
                dur: dur,
                tzi: tzi,
                time: tStr,
                timeAt: atStr
            }
        }

        dta.sort((a, b) => (a.rts > b.rts) ? 1 : -1)
        dta.push({rts: 0, d: "", t: "", tzi: "", time: ""}) //last option to finalize the loop

        // console.log(dta)
        s.dataRef = dta

        let l = dta.length

        let cont = document.createElement('div')
        cont.id = "srgdev-dpu_main-cont"
        cont.className = "srgdev-dpu-bkr-cls"

        let lcd = document.createElement('div')
        lcd.id = "srgdev-dpu_main-header"
        lcd.appendChild(document.createTextNode(dpuTrHdr))
        let lcdBF = document.createElement('div')
        lcdBF.id = "srgdev-dpu_main-hdr-icon"
        lcdBF.className = "icon-close"
        lcdBF.addEventListener('click', function () {
            document.getElementById("srgdev-dpu_main-cont").removeAttribute("data-open")
        })
        lcd.appendChild(lcdBF)
        cont.appendChild(lcd)


        lcdBF = document.createElement('div')
        lcdBF.maxDP = 0
        lcdBF.curDP = 0
        lcdBF.id = "srgdev-dpu_bf-cont"
        lcdBF.appendChild(document.createElement("span"))
        lcdBF.appendChild(document.createElement("span"))
        lcdBF.firstElementChild.id = "srgdev-dpu_bf-back"
        lcdBF.firstElementChild.appendChild(document.createTextNode(dpuTrBack))
        lcdBF.firstElementChild.addEventListener("click", prevNextDPU)
        lcdBF.firstElementChild.setAttribute('disabled', '')
        lcdBF.lastElementChild.id = "srgdev-dpu_bf-next"
        lcdBF.lastElementChild.appendChild(document.createTextNode(dpuTrNext))
        lcdBF.lastElementChild.addEventListener("click", prevNextDPU)

        cont.appendChild(lcdBF)

        lcd = document.createElement('div')
        lcd.id = "srgdev-dpu_main-date"
        lcd.className = "srgdev-dpu-bkr-cls"
        lcd.style.left = "0em"
        addSwipe(lcd, lcdBF)
        cont.appendChild(lcd)

        let lcTime = document.createElement('div')
        lcTime.id = "srgdev-dpu_main-time"
        cont.appendChild(lcTime)

        let lcc = 0
        let rccN = 5

        let d = new Date()

        let lastUD = -1

        let an = -1
        let do_break = false

        let makeDateCont = function (d, is_empty) {
            let e1 = document.createElement("div")
            e1.id = "srgdev-dpu_dc" + lcc + (is_empty ? "e" : "")
            e1.className = 'srgdev-dpu-date-cont' + (is_empty ? " srgdev-dpu-dc-empty" : "")

            let e2 = document.createElement('span')
            e2.className = d.getDay() !== 0 ? 'srgdev-dpu-date-wd' : 'srgdev-dpu-date-wd srgdev-dpu-date-wd-sunday';
            e2.appendChild(document.createTextNode(wf(d)))
            e1.appendChild(e2)

            e2 = document.createElement('span')
            e2.className = 'srgdev-dpu-date-dn'
            e2.appendChild(document.createTextNode(d.getDate()))
            e1.appendChild(e2)

            e2 = document.createElement('span')
            e2.className = 'srgdev-dpu-date-md'
            e2.appendChild(document.createTextNode(df(d)))
            e1.appendChild(e2)
            e1.addEventListener('click', dateClick)

            if (lcc === rccN) {
                rccN += 5
                lcdBF.maxDP++
                if (lcc > min_days) do_break = true
            }
            ++lcc
            return e1
        }

        let td = new Date()
        td.setSeconds(1)
        td.setMinutes(0)
        td.setHours(0)

        if (pso[PPS_EMPTY] === 1 && pso[PPS_FNED] === 0) {
            // Need to prepend empty days so the week start on Monday
            let ts = dta[0].rts
            d.setTime(ts)
            d.setSeconds(1)
            d.setMinutes(0)
            d.setHours(0)
            let fd = d.getDay()
            if (fd > 0 && fd < 6) {
                td.setTime(d.getTime() - 86400000 * (fd - 1))
            }
        }

        let tu_class
        // Time columns
        if (pso[PPS_TIME2] === 0 || pso[PPS_END_TIME] === 1) {
            tu_class = 'srgdev-dpu-time-unit' +
                (pso[PPS_END_TIME] === 1 ? "_tn" : "")
        } else {
            tu_class = 'srgdev-dpu-time-unit2'
        }

        let getTzName
        if (pso[PPS_SHOWTZ] === 1 && has_intl) {
            // getTzName=
            getTzName = function (d) {
                const short = d.toLocaleDateString(lang);
                const full = d.toLocaleDateString(lang, {timeZoneName: 'long'});

                // Trying to remove date from the string in a locale-agnostic way
                const shortIndex = full.indexOf(short);
                if (shortIndex >= 0) {
                    const trimmed = full.substring(0, shortIndex) + full.substring(shortIndex + short.length);

                    // by this time `trimmed` should be the timezone's name with some punctuation -
                    // trim it from both sides
                    return trimmed.replace(/^[\s,.\-:;]+|[\s,.\-:;]+$/g, '');

                } else {
                    return full;
                }

            }
        } else {
            getTzName = function () {
                return ''
            }
        }


        for (let tl, ts, ti, ets, tts, te, pe, elt, dto, tzn, i = 0; i < l; i++) {
            dto = dta[i]
            ts = dto.rts
            if (ts === 0) break
            d.setTime(ts)

            let ud = d.getDate()

            if (lastUD !== ud) {

                // if(do_break) break

                // Show "empty" days ...
                tts = td.getTime()
                td.setTime(d.getTime())
                td.setSeconds(1)
                td.setMinutes(0)
                td.setHours(0)
                ets = td.getTime()

                if (pso[PPS_EMPTY] === 1) {
                    while (tts < ets) {
                        td.setTime(tts)

                        // Deal with weekends
                        if (pso[PPS_WEEKEND] === 0) {
                            // only show weekdays
                            ti = td.getDay()
                        } else {
                            // show all days
                            ti = 1
                        }

                        if (ti !== 0 && ti !== 6) {
                            lcd.appendChild(makeDateCont(td, true))
                            if (do_break) break
                        }
                        tts += 86400000;
                    }
                }

                if (do_break) {
                    d = td
                    break
                }

                td.setTime(tts + 86400000)

                te = makeDateCont(d, false)
                if (an === -1) {
                    an = lcc - 1
                    te.setAttribute('data-active', '')
                }
                lcd.appendChild(te)

                te = document.createElement('div')
                te.id = "srgdev-dpu_tc" + (lcc - 1)
                te.className = 'srgdev-dpu-time-cont'

                pe = document.createElement('div')
                pe.className = "srgdev-dpu-tc-full-date"
                pe.appendChild(document.createTextNode(wff(d)))
                // time zone
                tzn = getTzName(d)
                if (tzn !== '') {
                    elt = document.createElement('div')
                    elt.className = "srgdev-dpu-tc-timezone"
                    elt.appendChild(document.createTextNode(tzn))
                    pe.appendChild(elt)
                }
                te.appendChild(pe)

                pe = document.createElement('div')
                pe.setAttribute('data-dm', wft(d))
                pe.className = "srgdev-dpu-tc-tu-wrap"
                te.appendChild(pe)

                lcTime.appendChild(te)

                lastUD = ud
            }
            te = document.createElement("span")
            te.className = tu_class
            te.dpuClickID = i
            te.timeAt = dto.timeAt
            // te.appendChild(document.createTextNode(tf(d)))
            te.appendChild(document.createTextNode(dto.time))
            if (dto.t !== "") {
                tl = document.createElement("span")
                tl.className = "srgdev-dpu-appt-title"
                tl.appendChild(document.createTextNode(dto.t))
                te.appendChild(tl)
            }
            pe.appendChild(te)
        }

        // fill in empty space
        d.setSeconds(0)
        d.setMinutes(0)
        d.setHours(1)
        d.setTime(d.getTime() + 86400000)

        // lcc%=5
        // if (lcc % 5 > 0) {
        //     for (let ti, l = 5 - (lcc % 5), i = 0; i < l; i++) {
        //
        //         ti = d.getDay()
        //
        //         // Deal with weekends
        //         if (pso[PPS_WEEKEND] === 0) {
        //             // only show weekdays
        //             ti = d.getDay()
        //         } else {
        //             // show all days
        //             ti = 1
        //         }
        //
        //         if (ti !== 0 && ti !== 6) {
        //             lcd.appendChild(makeDateCont(d, true))
        //         } else {
        //             //skipping weekend
        //             i--
        //         }
        //         d.setTime(d.getTime() + 86400000)
        //     }
        // }


        // Make empty time cont
        let te = document.createElement('div')
        te.id = "srgdev-dpu_tce"
        te.className = 'srgdev-dpu-time-cont'
        te.appendChild(document.createTextNode(dpuTrNA))
        lcTime.appendChild(te)

        lcTime.firstElementChild.setAttribute('data-active', '')
        lcd.curActive = an.toString()

        cont.addEventListener("click", timeClick)
        document.getElementById('srgdev-ncfp_sel_cont').appendChild(cont)

        // let's make sure the correct date square is shown...
        // ... 5 is the number of available slots per pagination page
        let ti = Math.floor(an / 5)
        if (ti > 0) {
            lcdBF.curDP = ti
            prevNextDPU(lcdBF)
        }
    }

})()
