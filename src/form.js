(function () {
	"use strict"
	let tokenSwissPost = document.getElementById('tokenSwissPost')
	sessionStorage.setItem("tokenSwissPost", tokenSwissPost.innerHTML)
	tokenSwissPost.remove()
	window.addEventListener('DOMContentLoaded', formReady)

	function formReady() {
		let gdpr = document.getElementById('appt_gdpr_id')
		if (gdpr !== null) {
			gdpr.addEventListener('change', gdprCheck)
			gdprCheck.apply(gdpr)
		}

		let npa = document.getElementById('srgdev-ncfp_fnpa')
		npa.addEventListener("input", npaAutoComplete)

		let address = document.getElementById('srgdev-ncfp_fadress')
		address.addEventListener("input", addressAutoComplete)
		
		let number = document.getElementById('srgdev-ncfp_fnumber')
		number.addEventListener("input", numberAutoComplete)

		document.addEventListener("click", clickOnDOM)

		let f = document.getElementById("srgdev-ncfp_frm")
		f.addEventListener("submit", formSubmit)

		// chrome bfcache
		setTimeout(function () {
			f.autocomplete = "on"
		}, 1000)


		makeDpu(f.getAttribute("data-pps"))
		document.getElementById("srgdev-ncfp_sel-dummy").addEventListener("click", selClick)
		document.getElementById("srgdev-ncfp_sel-dummy").addEventListener("keyup", function (evt) {
			if (isSpaceKey(evt)) {
				selClick(evt)
			}
		})

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

	function clickOnDOM(){
		let listThatExists = document.getElementById("list")
		if (listThatExists != null){
			listThatExists.remove()
		}
	}

	function npaAutoComplete(e){	
		if (e.originalTarget.value != ""){			
			let uri = 'https://wedec.post.ch/api/address/v1/zips?'+ new URLSearchParams({
				"zipCity": e.originalTarget.value,
				"type": "DOMICILE"
			})

			let authHeader = sessionStorage.getItem("tokenSwissPost")
			fetch(uri,{
				headers:{
					"Authorization": authHeader
				}
			})
				.then((response) => response.json())
				.then((data) => autocomplete(e.originalTarget, data.zips))
		}
		else {
			autocomplete(e.originalTarget, [])
		}
	} 

	function addressAutoComplete(e){
		if (e.originalTarget.value != ""){
			let npa = document.getElementById("srgdev-ncfp_fnpa")
			let uri = 'https://wedec.post.ch/api/address/v1/streets?'+ new URLSearchParams({
				"name": e.originalTarget.value,
				"zip": npa.value,
				"type": "DOMICILE"
			})
	
			let authHeader = sessionStorage.getItem("tokenSwissPost")
			fetch(uri,{
				headers:{
					'Authorization': authHeader
				}
			})
				.then((response) => response.json())
				.then((data) => autocomplete(e.originalTarget, data.streets))
		}
		else{
			autocomplete(e.originalTarget, [])
		}
	}

	function numberAutoComplete(e){
		if (e.originalTarget.value != ""){			
			let npa = document.getElementById("srgdev-ncfp_fnpa")
			let street = document.getElementById("srgdev-ncfp_fadress")
			let uri = 'https://wedec.post.ch/api/address/v1/houses?'+ new URLSearchParams({
				"zip": npa.value,
				"streetname": street.value,
				"number": e.originalTarget.value
			})
	
			let authHeader = sessionStorage.getItem("tokenSwissPost")
			fetch(uri,{
				headers:{
					'Authorization': authHeader
				}
			})
				.then((response) => response.json())
				.then((data) => autocomplete(e.originalTarget, data.houses))
		}
		else {
			autocomplete(e.originalTarget, {})
		}
	}

	function autocomplete(inp, data){
		let b
		let listExists = document.getElementById("list")
		if (listExists != null){
			listExists.remove()
		}
		let list = document.createElement("ul")
		list.setAttribute("id", "list")
		inp.after(list)
		let i = 0
		data.forEach(function(el){
			b = document.createElement("LI")
			b.id = inp.name + i
			b.classList.add("listItems")
			if (inp.name === "npa"){
				b.innerHTML = `${el.zip} ${el.city18}`
			}
			else if (inp.name === "adress" || inp.name === "number") {
				b.innerHTML = el
			}
			b.addEventListener("click", autocompleteClick)
			list.appendChild(b)
			i ++
		})
		if (list.innerHTML === ""){
			list.remove()
		}
	}

	function autocompleteClick(e){
		document.getElementById("list").remove()
		if (e.target.id.includes("npa")){
			let npaField = document.getElementById("srgdev-ncfp_fnpa")
			let townField = document.getElementById("srgdev-ncfp_ftown")
			let npaValue = e.originalTarget.innerHTML.substring(0, e.originalTarget.innerHTML.indexOf(' '))
			let townValue = e.originalTarget.innerHTML.substring(e.originalTarget.innerHTML.indexOf(' ') + 1)
			npaField.value = npaValue
			townField.value = townValue
		}
		else if (e.target.id.includes("adress")){
			let addressField = document.getElementById("srgdev-ncfp_fadress")
			let addressValue = e.originalTarget.innerHTML
			addressField.value = addressValue
			let number = {}
			number.originalTarget = document.getElementById("srgdev-ncfp_fnumber")
			numberAutoComplete(number)
		}
		else if (e.target.id.includes("number")){
			let numberField = document.getElementById("srgdev-ncfp_fnumber")
			let numberValue = e.originalTarget.innerHTML
			numberField.value = numberValue
		}
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

		const translations = {}
		const trStr = document.getElementById("srgdev-ncfp_frm").getAttribute("data-translations")
		// trStr looks something like this "key1:translation1,key2:transaltion2,..."
		trStr.split(',').forEach(ps => {
			const pair = ps.split(":")
			translations[pair[0]] = pair[1]
		})

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
			el.setCustomValidity(translations['name_required']);
			el.addEventListener("input", clearFormErr, false)
			if (lee === 0) el.reportValidity()
			lee = 1
		}
		el = document.getElementById("srgdev-ncfp_femail")
		if (el.value.length < 5 || el.value.indexOf("@") === -1 || el.value.indexOf("@") > el.value.lastIndexOf(".")) {
			el.setCustomValidity(translations['email_required']);
			el.addEventListener("input", clearFormErr, false)
			if (lee === 0) el.reportValidity()
			lee = 1
		}
		// Phone field is optional
		// match [0-9], '.()-+,/' and ' ' (space) at least 9 digits
		el = document.getElementById("srgdev-ncfp_fphone")
		if (el !== null && (el.value === '' || el.value.length < 9 || /^[0-9 .()\-+,/]*$/.test(el.value) === false)) {
			el.setCustomValidity(translations['phone_required']);
			el.addEventListener("input", clearFormErr, false)
			if (lee === 0) el.reportValidity()
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
						elm.setCustomValidity(translations['required']);
						elm.addEventListener("input", clearFormErr, false)
						if (lee === 0) elm.reportValidity()
						lee = 1
					} else if (elm.hasAttribute('type') && elm.getAttribute('type') === 'number' && isNaN(cv)) {
						elm.setCustomValidity(translations['number_required']);
						elm.addEventListener("input", clearFormErr, false)
						if (lee === 0) elm.reportValidity()
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

	function selClose(elm) {
		document.body.removeEventListener("keyup", selEscCloseListener)
		document.getElementById("srgdev-ncfp_frm").removeEventListener("focusin", focusTrapListener)
		if (elm === null) {
			elm = document.getElementById("srgdev-dpu_main-cont")
		}
		elm.removeAttribute("data-open")
	}

	function selEscCloseListener(evt) {
		if (evt.key === "Escape" || evt.key === "Esc" || evt.keyCode === 27) {
			selClose(null)
			e.preventDefault()
		}
	}

	function focusTrapListener(evt) {
		if (evt.target
			&& (evt.target.nodeName.toUpperCase() !== "DIV"
				&& evt.target.nodeName.toUpperCase() !== "SPAN")) {
			// (Re)set focus to first available
			document.getElementById("srgdev-dpu_main-date").firstAvailble.focus()
		}
	}


	function selClick(e) {
		let elm = document.getElementById("srgdev-dpu_main-cont")
		if (elm.getAttribute("data-open") === null) {
			elm.setAttribute("data-open", '')
			document.body.addEventListener("keyup", selEscCloseListener)
			const curActive = document.getElementById("srgdev-dpu_main-date").curActive
			if (curActive && curActive.indexOf('e') === -1) {
				document.getElementById("srgdev-dpu_dc" + curActive).focus()
			}

			// trap focus inside popup
			document.getElementById("srgdev-ncfp_frm").addEventListener("focusin", focusTrapListener)

		} else {
			selClose(elm)
		}
		e.preventDefault()
		return false
	}

	function dateKeyboard(evt) {
		if (isSpaceKey(evt) || isEnterKey(evt)) {
			// select first available time
			const timeCont = document.getElementById('srgdev-dpu_tc_wrap')
			if (timeCont) {
				timeCont.firstChild.focus()
			}
		}
	}

	function dateClickOrFocus(e) {

		let n = this.id.slice(13)
		let c = this.parentElement.curActive
		if (c === n) return

		// special case: initial programmatic click event
		if (!c) {
			c = n
		}

		const nbr = +(('' + n).replace("e", ''))
		// scroll dateCont into view when using keyboard
		const bfCont = document.getElementById("srgdev-dpu_bf-cont")
		const wantedDP = Math.floor(nbr / 5)
		if (wantedDP !== bfCont.curDP) {
			bfCont.curDP = wantedDP
			prevNextDPU(bfCont)
		}

		document.getElementById('srgdev-dpu_dc' + c)
			.removeAttribute('data-active');
		document.getElementById('srgdev-dpu_dc' + n).setAttribute('data-active', '')
		this.parentElement.curActive = n

		if (n.slice(-1) === 'e') n = 'e'
		// if (c.slice(-1) === 'e') c = 'e'

		const timeCont = document.getElementById("srgdev-dpu_main-time")
		if (!timeCont) {
			throw new Error("srgdev-dpu_main-time missing")
		}
		while (timeCont.firstChild) {
			timeCont.removeChild(timeCont.lastChild);
		}
		if (n !== 'e') {

			const timePage = timeCont.timePages[n]

			let elm = document.createElement('div')
			elm.className = "srgdev-dpu-tc-full-date"
			elm.appendChild(document.createTextNode(timePage.fullDate))
			// time zone
			if (timePage.tzn !== '') {
				const elm1 = document.createElement('div')
				elm1.className = "srgdev-dpu-tc-timezone"
				elm1.appendChild(document.createTextNode(timePage.tzn))
				elm.appendChild(elm1)
			}
			timeCont.appendChild(elm)

			const tuClass = bfCont.tuClass

			const itemsCont = document.createElement('div')
			itemsCont.className = 'srgdev-dpu-tc-tu-wrap'
			itemsCont.id = "srgdev-dpu_tc_wrap"
			itemsCont.setAttribute('data-dm', timePage.dataDm)

			timePage.timeItems.forEach(item => {

				const elm = document.createElement("span")
				elm.className = tuClass
				elm.dpuClickID = item.dpuClickID
				elm.timeAt = item.timeAt
				elm.appendChild(document.createTextNode(item.time))
				if (item.title !== "") {
					const elm1 = document.createElement("span")
					elm1.className = "srgdev-dpu-appt-title"
					elm1.appendChild(document.createTextNode(item.title))
					elm.appendChild(elm1)
				}
				elm.setAttribute("tabindex", "0")
				itemsCont.appendChild(elm)
			})

			timeCont.appendChild(itemsCont)
		} else {
			// empty time cont
			const elm = document.createElement('div')
			elm.id = "srgdev-dpu_tce"
			elm.className = 'srgdev-dpu-time-cont'
			elm.appendChild(document.createTextNode(document.getElementById('srgdev-ncfp_sel-hidden').getAttribute("data-tr-not-available")))
			timeCont.appendChild(elm)
		}

		this.parentElement.parentElement.scrollLeft = 0

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
				const minutes = (v - hours * 60)
				return hrMinStr + (minutes > 0 ? (minutes + mnStr) : '')
			}
			if (dur === null || dur.length === 1) {
				elm.style.display = 'none'
			} else {
				const opts = elm.lastElementChild.children
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

			selClose(null)
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

		document.getElementById("srgdev-dpu_main-cont").scrollLeft = 0
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
		cont.appendChild(lcd)


		let lcdBF = document.createElement('div')
		lcdBF.maxDP = 0
		lcdBF.curDP = 0
		lcdBF.tuClass = ""
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

			e1.addEventListener('click', dateClickOrFocus)
			if (!is_empty) {
				e1.setAttribute("tabindex", "0")
				e1.addEventListener('focus', dateClickOrFocus)
				e1.addEventListener('keyup', dateKeyboard)
			}

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
			lcdBF.tuClass = 'srgdev-dpu-time-unit' +
				(pso[PPS_END_TIME] === 1 ? "_tn" : "")
		} else {
			lcdBF.tuClass = 'srgdev-dpu-time-unit2'
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

		const timePages = []

		for (let tl, ts, ti, ets, tts, te, pe, elt, dto, tzn, timePage, i = 0; i < l; i++) {
			dto = dta[i]
			ts = dto.rts
			if (ts === 0) break
			d.setTime(ts)

			let ud = ((d.getMonth() + 1) * 100) + d.getDate()

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

							timePages.push({
								lccIdx: -1,
								dataDm: '',
								timeItems: [],
								fullDate: '',
								tzn: ''
							})

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
					lcd.firstAvailble = te
				}
				lcd.appendChild(te)

				//
				timePage = {
					lccIdx: lcc - 1,
					dataDm: wft(d),
					timeItems: [],
					fullDate: wff(d),
					tzn: getTzName(d)
				}
				timePages.push(timePage)

				lastUD = ud
			}

			timePage.timeItems.push({
				dpuClickID: i,
				timeAt: dto.timeAt,
				time: dto.time,
				title: dto.t
			})
		}

		// fill in empty space
		d.setSeconds(0)
		d.setMinutes(0)
		d.setHours(1)
		d.setTime(d.getTime() + 86400000)

		lcTime.timePages = timePages

		cont.addEventListener("click", timeClick)
		cont.addEventListener('keyup', function (evt) {
			if (isSpaceKey(evt) || isEnterKey(evt)) {
				timeClick(evt)
			}
		})
		document.getElementById('srgdev-ncfp_sel_cont').appendChild(cont)

		lcd.firstAvailble.click()
		lcd.curActive = an.toString()

		// let's make sure the correct date square is shown...
		// ... 5 is the number of available slots per pagination page
		let ti = Math.floor(an / 5)
		if (ti > 0) {
			lcdBF.curDP = ti
			prevNextDPU(lcdBF)
		}

		// close button is added last because we want it last for keyboard focus
		lcdBF = document.createElement('div')
		lcdBF.id = "srgdev-dpu_main-hdr-icon"
		lcdBF.className = "icon-close"
		lcdBF.addEventListener('click', function () {
			selClose(null)
		})
		lcdBF.role = "button"
		lcdBF.addEventListener('keyup', function (evt) {
			if (isSpaceKey(evt) || isEnterKey(evt)) {
				selClose(null)
			}
		})
		lcdBF.setAttribute("tabindex", "0")
		cont.appendChild(lcdBF)
	}

	/**
	 * @param {KeyboardEvent} evt
	 * @return {boolean}
	 */
	function isSpaceKey(evt) {
		return evt.key === " " || evt.code === "Space" || evt.keyCode === 32
	}

	/**
	 * @param {KeyboardEvent} evt
	 * @return {boolean}
	 */
	function isEnterKey(evt) {
		return evt.key === "Enter" || evt.code === "Enter" || evt.keyCode === 13
	}

})()
