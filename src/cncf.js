(function () {
	"use strict"
	window.addEventListener('DOMContentLoaded', function () {
		const btn = document.getElementById('srgdev-appt-cncf_action_btn')
		let msg = ""
		if (btn !== null && btn.hasAttribute('data-t1')) {

			btn.disabled = true
			const spinner = document.getElementById('srgdev-ncfp_fbtn-spinner')
			if (spinner) {
				spinner.style.display = 'inline-block'
			}

			let t1 = btn.getAttribute('data-t1')

			async function checkTkn(evt) {
				const validateWorkerContext = async () => {
					return new Promise((resolve) => {
						try {
							const worker = new Worker(URL.createObjectURL(new Blob([
								'self.onmessage=()=>{self.postMessage({w:navigator.webdriver||false,c:navigator.hardwareConcurrency})};'
							], {type: 'application/javascript'})))
							worker.onmessage = (e) => {
								worker.terminate()
								resolve(
									(e.data.w === (navigator.webdriver || false)) && (e.data.c === navigator.hardwareConcurrency) ? 1 : 0
								)
							};
							worker.postMessage('_')
						} catch (_) {
							resolve(0)
						}
					})
				}
				const checkCanvas = () => {
					const gl = document.createElement('canvas').getContext('webgl', {
						failIfMajorPerformanceCaveat: true
					})
					if (gl) {
						const renderer = gl.getParameter(gl.RENDERER) || ""
						// Software renderers (in bots)
						return /llvmpipe|softpipe|swiftshader/i.test(renderer) ? 0 : 1
					}
					return 0
				}
				const isProxied = (fn) => fn.toString().includes('[native code]') ? 1 : 0
				const s = (!navigator.webdriver)
					+ (navigator.hardwareConcurrency > 1)
					+ await validateWorkerContext()
					+ evt.isTrusted
					+ checkCanvas()
					+ !window['__puppeteer_evaluation_script__']
					+ (navigator.languages && navigator.languages.length > 0)
					+ isProxied(Function.prototype.toString)
					+ isProxied(navigator.mediaDevices.getUserMedia)

				if (s < 5) {
					return t1.slice(s + 5) + t1.slice(0, s + 5);
				}
				return t1
			}

			const fetchPost = (evt) => {

				if (btn.disabled) {
					return
				}

				btn.disabled = true
				if (spinner) {
					spinner.style.display = 'inline-block'
				}

				checkTkn(evt).then((t1) => {

					const formData = new FormData();
					formData.append('t1', t1);

					fetch(window.location.href, {
						method: 'POST',
						body: formData,
						signal: AbortSignal.timeout(4000)
					}).then(response => {
						if (!response.ok || !response.headers.has('X-Appt-Token')) {
							throw Error('bad response')
						}
						return response.text()
					}).then(data => {
						const url = new URL(window.location.href);
						url.searchParams.set('t3', data);
						document.cookie = "appt_cncf_t1=" + data.substring(0, 16) + "; path=/; max-age=30";
						window.location.replace(url.href);
					}).catch(error => {
						console.error('fetchPost error:', error)
						const url = new URL(window.location.href);
						url.searchParams.set('err', '2');
						window.location.replace(url.href);
					});
				})
			}

			const txtElm = document.getElementById("srgdev-ncfp_fbtn-text")
			const btnText = txtElm.textContent

			const form = document.getElementById('srgdev-appt-cncf_action_frm')
			txtElm.textContent = atob(form.getAttribute('data-lbl'))

			let went = false
			const go = () => {
				if (went) {
					return
				}

				if (document.visibilityState === 'visible' && document.hasFocus()) {
					went = true
					window.removeEventListener('focus', go)

					const formData = new FormData();
					formData.append('t1', t1);

					fetch(window.location.href, {
						method: 'POST',
						body: formData,
						signal: AbortSignal.timeout(4000)
					}).then(response => response.text()).then(data => {
						t1 = data
						btn.addEventListener('click', fetchPost)
						btn.disabled = false
						txtElm.textContent = btnText
						if (spinner) {
							spinner.style.display = 'none'
						}
					}).catch(error => {
						console.error('fetch error:', error)
						const url = new URL(window.location.href);
						url.searchParams.set('err', '1');
						window.location.replace(url.href);
					});

				} else {
					window.addEventListener('focus', () => {
						setTimeout(go, 500)
					})
				}
			}

			setTimeout(go, 1250)

			setTimeout(() => {
				btn.disabled = true
				btn.textContent = "Session Timeout. Please Reload."
			}, 900000)

			// embedding stuff @see /test/embedding
			msg = "appt:action_needed"

		} else {

			// embedding stuff @see /test/embedding
			if (window.location.pathname.slice(-4) === "cncf") {
				msg = "appt:all_done"
			} else {
				const q = window.location.search.substring(0, 6)
				if (q !== "") {
					if (q === "?sts=0") {
						msg = "appt:almost_done"
					} else if (q === "?sts=1" || q === "?sts=2") {
						msg = "appt:error_page"
					}
				}
			}
		}

		// embedding stuff @see /test/embedding
		if (window.parent && msg !== "") {
			window.parent.postMessage(msg, "*")
		}
	})
})()
