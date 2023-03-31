import axios from "@nextcloud/axios";
import {showError} from "@nextcloud/dialogs"
import {CK} from "../use/constants"

const CANCEL_MSG = '__canceled__'

export default class SettingsService2 {
	#cancelSources = {}

	async getPages() {
		return this.#sendRequest('get_pages', '', CK.PAGES).catch(_ => {
			showError(t('appointments', "Can't get pages. Check console"))
			return null
		})
	}

	async addNewPage() {
		return this.#sendRequest('set_pages', '_', CK.PAGES).catch(_ => {
			showError(t('appointments', "Can't add new page. Check console"))
			return null
		})
	}

	async deletePage(pageId) {
		return this.#sendRequest('set_pages', pageId, CK.PAGES).catch(_ => {
			showError(t('appointments', "Can't delete page. Check console"))
			return null
		})
	}

	async getPageUrl(pageId) {
		return this.#sendRequest('get_puburi', pageId, CK.URL).catch(_ => {
			showError(t('appointments', "Error. Check console"))
			return null
		})
	}

	async getSettings(pageId) {
		return this.#sendRequest('get_all', pageId, CK.SETTINGS).catch(_ => {
			showError(t('appointments', "Can't get Settings. Check console"))
			return null
		})
	}

	async getCalendarTimezone(pageId, calId) {
		return this.#sendRequest('get_tz', pageId, CK.TIMEZONE, {calId: calId}).catch(_ => {
			showError(t('appointments', "Can't get Timezone. Check console"))
			return null
		})
	}

	async getCalendarWeek(pageId, t) {
		return this.#sendRequest('get_calweek', pageId, CK.CALWEEK, {t: t}).catch(_ => {
			showError(t('appointments', "Can't get calendar data. Check console"))
			return null
		})
	}

	async addSimpleAppointments(data) {

		const cancelKey = '__cal_data'
		this.cancel(cancelKey)
		const cancelSource = axios.CancelToken.source()
		this.#cancelSources[cancelKey] = cancelSource
		return axios.post('caladd', data, {
			cancelToken: cancelSource.token
		}).then(res => {
			return {
				status: res.status,
				data: res.data
			}
		}).catch(err => {
			if (cancelSource.token.reason &&
				cancelSource.token.reason.code === "ERR_CANCELED" &&
				cancelSource.token.reason.message === CANCEL_MSG) {
				console.debug("cancelled", cancelSource)
			} else {
				showError(t('appointments', "Error: check console"))
			}
			return null
		}).finally(() => {
			delete this.#cancelSources[cancelKey]
		})
	}

	/**
	 * @param {String} pageId
	 * @param {String} key
	 * @param value
	 * @return {Promise<T>}
	 */
	async setOne(pageId, key, value) {
		return this.#sendRequest('set_one', pageId, key, {
			k: key,
			v: value
		}).catch(_ => {
			showError(t('appointments', "Can't apply settings"))
			return null
		})
	}

	getDebugData(action, data) {
		return this.#sendRequest(action, undefined, CK.DEBUG, data,'debug').catch(_ => {
			showError(t('appointments', "error: cannot get debug data"))
			return null
		})
	}


	cancel(cancelKey = undefined) {
		if (cancelKey) {
			/** @type {CancelTokenSource|undefined} */
			const source = this.#cancelSources[cancelKey]
			if (source) {
				source.cancel(CANCEL_MSG)
			}
		} else {
			for (const prop in this.#cancelSources) {
				this.#cancelSources[prop].cancel(CANCEL_MSG)
			}
		}
	}

	/**
	 * @param {String} action
	 * @param {String} pageId
	 * @param {String} cancelKey
	 * @param {Object} data
	 * @param {String} url
	 * @return {Promise<T>}
	 */
	async #sendRequest(action, pageId = "", cancelKey, data = {}, url = 'state') {
		this.cancel(cancelKey)
		const cancelSource = axios.CancelToken.source()
		this.#cancelSources[cancelKey] = cancelSource
		return axios.post(url, {
			a: action,
			p: pageId,
			...data
		}, {
			cancelToken: cancelSource.token
		}).then(res => {
			return {
				status: res.status,
				data: res.data
			}
		}).catch(err => {
			if (cancelSource.token.reason &&
				cancelSource.token.reason.code === "ERR_CANCELED" &&
				cancelSource.token.reason.message === CANCEL_MSG) {
				console.debug("cancelled", cancelSource)
				return null
			}
			console.error(action, err)
			throw "error"
		}).finally(() => {
			delete this.#cancelSources[cancelKey]
		})
	}

}