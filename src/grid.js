function _apptGridMaker() {
	// !!! make sure that the .grid-line height is 2px less thant this is !!!
	const LINE_HEIGHT_5M = 7
	const MPH = 3600000
	const MP5 = 300000
	// Start at 6AM
	// !! CHANGE 'const SH' in components too !!!
	const SH = 0 // if this is lest than 4 there might be a problem on daylight savings day.
	// 17 hours
	const DH = 24
	// SH + 8 = 8AM, The grid will be scrolled to here and newly added slots will start at this time
	const SH_OFFSET = 8

	const MODE_SIMPLE = 0
	const MODE_TEMPLATE = 1

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

		/** @type HTMLElement */
		scrollCont: null,
		/** @type HTMLElement */
		headerElm: null,
		headerHeight: 0,

		/** @type {Array[]} */
		mc_elm: [],

		/** @type {HTMLElement[]} */
		mc_cols: [],

		sorted: [],

		mode: MODE_SIMPLE
	}

	/**
	 * @param {HTMLElement} cont
	 * @param {number} colCnt
	 * @param {string} stylePrefix
	 */
	function setup(cont, colCnt, stylePrefix = "") {

		mData.scrollCont = cont.parentElement;
		mData.headerElm = mData.scrollCont.firstElementChild

		sP = stylePrefix
		let elm = document.createElement('div')
		elm.className = sP + 'grid_layer'
		cont.appendChild(elm)
		mData.gridLayer = elm

		elm = document.createElement('div')
		elm.className = sP + 'appt_layer'
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
	 * @param {string|Object} clr background color or template mode appt. info
	 */
	function addAppt(start, len, cnt, cID, clr) {

		if (len < 5) len = 5
		else if (len > 480) len = 480

		let uLen = Math.floor(len / 5)
		let uMax = mData.uMax - uLen + 1

		start += (SH_OFFSET * 60) / 5

		if (start < 0) start = 0
		else if (start > uMax) return

		const existingElms = mData.mc_elm[cID].length
		let f = document.createDocumentFragment()
		for (let uTop, i = 0; i < cnt; i++) {
			uTop = i * uLen + start
			if (uTop > uMax) break

			f.appendChild(makeApptElement(uTop, uLen, i + existingElms, cID, clr))
		}

		mData.mc_cols[cID].appendChild(f)

		const colElms = mData.mc_elm[cID]
		setSorted(colElms, colElms[colElms.length - 1])
		setMargins(colElms)

		scrollGridToTopElm()
	}

	function scrollGridToTopElm() {
		mData.scrollCont.scrollTop = document.getElementById("grid-scroll-top").offsetTop
	}

	/**
	 * @param {number} fromCID
	 * @param {number} toCID
	 * @param {string} clr background color
	 */
	function cloneColumns(fromCID, toCID, clr) {
		if (mData.mc_elm[toCID].length !== 0 || mData.mc_elm[fromCID].length === 0) {
			return
		}
		let f = document.createDocumentFragment()
		const copyDur = mData.mode === MODE_TEMPLATE
		mData.mc_elm[fromCID].forEach((a, i) => {
			f.appendChild(makeApptElement(a.uTop, a.uLen, i, toCID, copyDur ? {dur: [...a.dur], title: a.title} : clr))
		})

		mData.mc_cols[toCID].appendChild(f)

		//Set z-index & margin
		mData.mc_elm[fromCID].forEach((a, i) => {
			let te = mData.mc_elm[toCID][i]
			te.style.zIndex = a.style.zIndex
			te.style.marginRight = a.style.marginRight
		})

	}

	/**
	 * Soft reset (keeps past appointments)
	 * @param {number} cID
	 */
	function resetColumn(cID) {
		let p = mData.mc_cols[cID]
		for (let els = mData.mc_elm[cID], c = els.length, i = 0; i < c; i++) {
			p.removeChild(els[i])
			els[i] = null; //???
		}
		mData.mc_elm[cID] = []
	}

	/**
	 * Hard reset, deletes all elements including past appointments
	 */
	function resetAllColumns() {
		for (let i = 0, l = mData.mc_cols.length; i < l; i++) {
			mData.mc_elm[i] = []
			let p = mData.mc_cols[i]
			while (p.lastElementChild) {
				p.removeChild(p.lastElementChild)
			}
		}
	}


	function makeApptElement(uTop, uLen, idx, cID, clr) {
		let elm = document.createElement('div')
		elm.className = sP + 'appt'
		if (mData.mode !== MODE_TEMPLATE && clr !== null) elm.style.backgroundColor = clr
		elm.uTop = uTop
		elm.uLen = uLen
		if (idx !== null) {
			elm.cIdx = idx
			elm.cID = cID
			elm.style.zIndex = "" + (mData.mc_elm[cID].length + 1)
			if (mData.mode !== MODE_SIMPLE && clr !== null) {
				elm.dur = clr.dur
				elm.title = clr.title
			}
		} else {
			elm.className += " " + sP + "appt-empty"
			elm.style.zIndex = "-1"
		}

		let ge = mData.elms[uTop]
		let e2 = mData.elms[uTop + uLen - 1]

		elm.style.top = ge.offsetTop + 'px'
		elm.style.height = (e2.offsetTop + e2.offsetHeight - ge.offsetTop - .25) + 'px'

		let et = document.createElement('div')
		et.className = sP + "appt_txt"
		et.appendChild(document.createTextNode(makeTxt(elm)))
		elm.appendChild(et)

		if (elm.title !== undefined && elm.title !== '') {
			et = document.createElement('div')
			et.className = sP + "appt_txt"
			et.appendChild(document.createTextNode(elm.title))
			elm.appendChild(et)
		}

		if (idx !== null) {

			// TODO: delegate these events to the parent ???
			elm.addEventListener("mousedown", appGoDrag)
			if (mData.mode === MODE_TEMPLATE) {
				elm.addEventListener("contextmenu", editAppt)
			}
			mData.mc_elm[cID].push(elm)

		}
		return elm
	}

	function editAppt(evt) {
		mData.scrollCont.lastElementChild.dispatchEvent(new CustomEvent('gridContext', {detail: evt.target}))
		evt.preventDefault()
		evt.stopPropagation()
	}

	function updateAppt(info) {
		const colElms = mData.mc_elm[info.cID]
		const el = colElms[info.cIdx]

		if (info.del !== undefined) {
			// delete
			el.parentElement.removeChild(el)
			colElms.splice(info.cIdx, 1)

			// (re)set cIdxs and z-index
			colElms.forEach((elm, index) => {
				elm.cIdx = index
				elm.style.zIndex = index + 1
			})

			this.sorted = []
			if (colElms.length !== 0) {
				setSorted(colElms, colElms[colElms.length - 1])
				setMargins(colElms)
			}
			return
		}

		el.dur = info.dur
		el.title = info.title

		const uLen = Math.floor(info.dur[0] / 5)
		const uTop = el.uTop

		let ge = mData.elms[uTop]
		let e2 = mData.elms[uTop + uLen - 1]

		el.uLen = uLen
		el.style.height = (e2.offsetTop + e2.offsetHeight - ge.offsetTop - .25) + 'px'

		el.firstElementChild.textContent = makeTxt(el)
		if (info.title === "") {
			if (el.children.length > 1) {
				el.removeChild(el.lastElementChild)
			}
		} else {
			if (el.children.length > 1) {
				el.lastElementChild.textContent = info.title
			} else {
				const et = document.createElement('div')
				et.className = sP + "appt_txt"
				et.appendChild(document.createTextNode(info.title))
				el.appendChild(et)
			}
		}

		setSorted(colElms, el)
		setMargins(colElms)
	}

	function makeTxt(el) {
		if (mData.mode === MODE_SIMPLE || el.dur.length === 1) {
			return mData.elms[el.uTop].dxt + ' - ' + mData.elms[el.uTop + el.uLen].dxt
		} else {
			return mData.elms[el.uTop].dxt + ' (' + el.dur.join(', ') + ')'
		}
	}

	function addTemplateData(data, gridShift) {
		const day_start_ts = SH * 3600
		for (let f, iMod, colElms, l = data.length, i = 0; i < l; i++) {
			if (data[i].length !== 0) {

				// @see gridShift in App.vue
				iMod = (i + gridShift) % 7

				f = document.createDocumentFragment()
				for (let uTop, uLen, info, d = data[i], k = d.length, j = 0; j < k; j++) {
					info = d[j]
					uTop = Math.floor((info.start - day_start_ts) / 300)
					uLen = Math.floor(info.dur[0] / 5)
					f.appendChild(makeApptElement(uTop, uLen, j, iMod, info))
				}

				mData.mc_cols[iMod].appendChild(f)
				colElms = mData.mc_elm[iMod]
				setSorted(colElms, colElms[0])
				setMargins(colElms)
			}
		}
	}

	function addPastAppts(data, clr, gridShift = 0) {

		if (!data) return

		if (clr === null) {
			addTemplateData(data, gridShift)
			return
		}

		const btm = DH * 12; // 12*5min=1hour
		const pd = data.split(String.fromCharCode(31))
		// [d,m+1,yyyy]
		const startDateArr = pd.shift().split('-')
		const startDateUTC = Date.UTC(startDateArr[2],
			startDateArr[1] - 1,
			startDateArr[0])

		for (let pds, j = 0, ll = pd.length; j < ll; j++) {
			pds = pd[j]
			if (pds.length < 3) continue
			if (j > 0) {
				const sep = pds.indexOf(String.fromCharCode(30))
				if (sep === -1) continue
				clr = pds.substr(0, sep)
				pds = pds.substr(sep + 1)
			}
			for (let sp, ets, elm, uTop, d = new Date(), ds, uLen, cID, da = pds.split(","),
				     l = da.length, i = 0; i < l; i++) {
				// TODO: remove 'U' from ds
				ds = da[i]

				sp = ds.indexOf(":", 8);

				//get end time first
				d.setTime(ds.substr(sp + 2) * 1000)

				ets = d.getTime()

				// start
				d.setTime(ds.substr(1, sp - 1) * 1000)

				uLen = Math.floor((ets - d.getTime()) / 300000)

				cID = Math.floor(Math.abs(
					Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()) - startDateUTC) / 86400000);
				// console.log("cID:", cID)
				if (cID < 0 || cID > 6) {
					console.log("invalid cID:", cID)
					continue
				}

				uTop = Math.floor((((d.getHours() - (SH)) * 60) / 5)
					+ ((d.getMinutes() / 5)))

				if (uTop >= 0 && uTop + uLen <= btm) {
					elm = makeApptElement(uTop, uLen, null, cID, clr)
					mData.mc_cols[cID].appendChild(elm)
				}
			}
		}
	}


	function appGoDrag(e) {

		// we need the "contextmenu" event
		if (e.button !== undefined && e.button === 2) return;

		const cID = this.cID
		if (cID === undefined) return

		// mData.gridLayer.style.cursor = 'grabbing'
		mData.mc_cols[cID].style.pointerEvents = "none"

		mData.diff = e.offsetY

		mData.curDrag = this
		setSorted(mData.mc_elm[cID], mData.curDrag)

		mData.headerHeight = mData.headerElm.offsetHeight

		window.addEventListener('mouseup', apptStopDrag)

		e.stopPropagation()
		e.preventDefault()
	}

	function setSorted(clmElms, curDrag) {
		for (let els = clmElms, el, z, sorted = mData.sorted
			     , de = curDrag, dz = +de.style.zIndex
			     , i = 0, l = els.length; i < l; i++) {
			el = els[i]
			if (el !== de) {
				z = +el.style.zIndex
				if (z > dz) {
					el.style.zIndex = --z
				}
			} else {
				z = els.length
				el.style.zIndex = z
			}

			// start sorted at 0
			z--

			if (sorted[z] === undefined) {
				sorted[z] = {
					idx: i,
					m: 0,
					h: el.uTop,
					l: el.uTop + el.uLen
				}
			} else {
				sorted[z].idx = i
				sorted[z].m = 0
				sorted[z].h = el.uTop
				sorted[z].l = el.uTop + el.uLen
			}
		}
	}

	function setMargins(clmElms) {
		const cl = clmElms.length
		// calculate margins
		for (let i = 1, cur, sorted = mData.sorted; i < cl; i++) {
			cur = sorted[i]
			for (let prev, j = 0; j < i; j++) {
				prev = sorted[j]
				if (prev.h < cur.l && cur.h < prev.l) {
					// overlap
					cur.m = Math.max(cur.m, prev.m + 3)
				}
			}
		}

		// set margins
		for (let i = 0, sorted = mData.sorted, s; i < cl; i++) {
			s = sorted[i]
			clmElms[s.idx].style.marginRight = s.m + "px"
		}
	}

	function apptStopDrag() {

		mData.mc_cols[mData.curDrag.cID].style.pointerEvents = "all"

		mData.diff = -1
		mData.uOffset = -1
		if (mData.ce !== null) {
			mData.ce.removeAttribute('top_ok')
			mData.ce = null
		}

		const clmElms = mData.mc_elm[mData.curDrag.cID]
		// set h & l for curDrag
		const ts = mData.sorted[clmElms.length - 1]
		ts.h = +mData.curDrag.uTop
		ts.l = ts.h + mData.curDrag.uLen

		// calculate margins
		setMargins(clmElms)

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

			const de = md.curDrag

			if (de.uTop < idx) {
				// moving down

				if (idx + de.uLen > md.uMax) idx = md.uMax - de.uLen + 1

				const diff = (md.scrollCont.offsetHeight + md.scrollCont.scrollTop) - (md.elms[idx + de.uLen].offsetTop + md.headerHeight)
				if (diff < 10) {
					md.scrollCont.scrollBy(0, Math.abs(10 - diff))
				}
			} else {
				// moving up
				if (idx < 0) idx = 0

				const diff = md.elms[idx].offsetTop - md.scrollCont.scrollTop
				if (diff < 10) {
					md.scrollCont.scrollBy(0, -Math.abs(10 - diff))
				}
			}

			// Set txt
			de.uTop = idx
			de.firstElementChild.textContent = makeTxt(de)
			de.style.top = md.elms[idx].offsetTop + 'px'
		} else {
			md.ce = null
		}
	}

	/**
	 * @param n number of columns
	 */
	function makeColumns(n) {
		for (let al = mData.apptLayer, elm,
			     w = getColumnWidth(n), i = 0; i < n; i++) {
			elm = document.createElement('div')
			elm.className = sP + "appt_columns"
			elm.style.width = w
			al.appendChild(elm)
			mData.mc_cols[i] = elm
			mData.mc_elm[i] = []
		}
	}

	function getColumnWidth(n) {
		return Math.floor((100 - 1) / n) + "%"
	}

	function makeHGrid() {
		const STEP = LINE_HEIGHT_5M

		// Ever 3rd line visible i.e. 15min
		const VS_LINE = 2
		const lang = document.documentElement.hasAttribute('data-locale')
			? [document.documentElement.getAttribute('data-locale').replaceAll('_', '-'), document.documentElement.lang]
			: [document.documentElement.lang]
		let timeFormat
		if (window.Intl && typeof window.Intl === "object") {
			let f = new Intl.DateTimeFormat(lang,
				{hour: "numeric", minute: "2-digit"})
			timeFormat = f.format
		} else {
			timeFormat = function (d) {
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

		const gridScrollTopTs = d.setHours(SH + SH_OFFSET)
		let tss = d.setHours(SH)

		for (let els = mData.elms, vc = VS_LINE, dxt, ce,
			     l = DH * 12, i = 0; i < l; i++) {

			ce = document.createElement('div')
			let tzo = d.getTimezoneOffset() * 60000
			d.setTime(tss)
			dxt = timeFormat(d)
			if (vc === VS_LINE) {
				ce.className = sP + "grid-line " + sP + "line-vis"
				if (tss === gridScrollTopTs) {
					ce.id = "grid-scroll-top"
				}
				ce.appendChild(document.createTextNode(dxt))
				vc = 0
			} else {
				ce.className = sP + "grid-line " + sP + "line-hid"
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
		tbl.style.height = ((mData.elms.length - 1) * STEP) + 'px'

		tbl.appendChild(f)
	}

	function getStarEnds(ts, add_offset) {

		function makeDT(d) {
			const month = d.getMonth() + 1
			const day = d.getDate()
			const h = d.getHours()
			const m = d.getMinutes()
			return d.getFullYear()
				+ (month < 10 ? "0" + month : "" + month)
				+ (day < 10 ? "0" + day : "" + day)
				+ "T" + (h < 10 ? "0" + h : "" + h) + (m < 10 ? "0" + m : "" + m) + "00"
		}

		// First element is the "create date" in UTC
		let r = [(new Date).toISOString().slice(0, -5).replace(/[\-:]/g, '') + 'Z']
		let rc = 0

		for (let d = new Date(), ds_ts, i = 0, l = mData.mc_cols.length; i < l; i++) {
			d.setTime(ts) // ts hourse:minutes are always midnight
			d.setHours(SH) // set initial hours
			ds_ts = d.setDate(d.getDate() + i)
			for (let ofs = 0, elm, ea = mData.mc_elm[i], j = 0, k = ea.length; j < k; j++) {
				// Start
				elm = ea[j]
				d.setTime(elm.uTop * MP5 + ds_ts)
				if (add_offset) {
					ofs = d.getTimezoneOffset() * 60000
					d.setTime(d.getTime() + ofs)
				}
				r[++rc] = makeDT(d)
				// End
				d.setTime((elm.uTop + elm.uLen) * MP5 + ds_ts + ofs)
				r[++rc] = makeDT(d)
			}
		}
		return r
	}

	function setMode(mode) {
		mData.mode = mode
	}

	/**
	 * @param gridShift - here we shift the grid so that grid Monday is at index 0 in template data, @see gridShift in App.vue
	 * @returns {*[]}
	 */
	function getTemplateData(gridShift) {
		const day_start_ts = SH * 3600

		const wa = []
		for (let da, i = 0 + gridShift, l = mData.mc_cols.length + gridShift; i < l; i++) {
			da = []

			// the (i % 7) is because of the gridShift
			for (let elm, ea = mData.mc_elm[(i % 7)], j = 0, k = ea.length; j < k; j++) {
				elm = ea[j]
				da.push({
					start: day_start_ts + elm.uTop * 300,
					dur: elm.dur,
					title: elm.title.replaceAll(',', ' ')
				})
			}
			wa.push(da)
		}
		return wa
	}

	function makeHeader(startDate, n) {
		const w = getColumnWidth(n)
		let tff
		if (window.Intl && typeof window.Intl === "object") {
			const lang = document.documentElement.hasAttribute('data-locale')
				? [document.documentElement.getAttribute('data-locale').replaceAll('_', '-'), document.documentElement.lang]
				: [document.documentElement.lang]

			tff = (mData.mode === MODE_SIMPLE
				? new Intl.DateTimeFormat(lang, {weekday: "short", month: "2-digit", day: "2-digit"})
				: new Intl.DateTimeFormat(lang, {weekday: "long"})).format
		} else {
			const _sl = mData.mode === MODE_SIMPLE ? 10 : 3
			// noinspection JSUnusedLocalSymbols
			tff = function (d) {
				return d.toDateString().slice(0, _sl)
			}
		}

		const header = []
		for (let ts = startDate.getTime(), i = 0; i < n; i++) {
			header[i] = {
				ts: ts,
				txt: tff(startDate),
				w: w,
				n: '8',// Initial value for "add" input must be string
				hasAppts: false,
			}
			ts = startDate.setDate(startDate.getDate() + 1)
		}
		return header
	}

	return {
		MODE_SIMPLE: MODE_SIMPLE,
		MODE_TEMPLATE: MODE_TEMPLATE,
		setup: setup,
		addAppt: addAppt,
		cloneColumns: cloneColumns,
		resetColumn: resetColumn,
		resetAllColumns: resetAllColumns,
		getStarEnds: getStarEnds,
		addPastAppts: addPastAppts,
		setMode: setMode,
		updateAppt: updateAppt,
		getTemplateData: getTemplateData,
		scrollGridToTopElm: scrollGridToTopElm,
		makeHeader: makeHeader
	}
}

export default new _apptGridMaker()
