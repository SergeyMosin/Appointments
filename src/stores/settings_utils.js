/**
 * @param {String} label
 * @returns {String}
 */
const normalizeLabel = (label) => label ? label : t('appointments', 'Public Page')

const isDir = (pageId) => pageId[0] === 'd'

// class FocusInterceptor {
// 	#noCallbackBlur = false
//
// 	/**
// 	 * @param {Function} focusCb
// 	 * @param {Function} blurCb
// 	 * @param {Function|undefined} interceptor
// 	 **/
// 	constructor(focusCb, blurCb, interceptor = undefined) {
// 		this.interceptor = interceptor
// 		this.focusCb = focus
// 		this.blurCb = blur
// 	}
//
// 	/**
// 	 * @param {FocusEvent} evt
// 	 * @param {Object} data
// 	 */
// 	focus = (evt, data) => {
// 		if (this.interceptor) {
// 			const intercepted = this.interceptor(evt, data)
// 			if (intercepted) {
// 				this.#noCallbackBlur = true
// 				evt.stopPropagation()
// 				evt.preventDefault()
// 				evt.currentTarget.blur()
// 				return
// 			}
// 		}
// 		this.focusCb()
// 	}
//
// 	blur = () => {
// 		if (this.#noCallbackBlur) {
// 			this.#noCallbackBlur = false
// 			return
// 		}
// 		this.blurCb()
// 	}
// }

export {
	normalizeLabel,
	isDir
	// FocusInterceptor
}

