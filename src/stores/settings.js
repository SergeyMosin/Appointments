import SettingsService2 from "../services/SettingsService2";
import {defineStore} from 'pinia'
import {showError} from "@nextcloud/dialogs";
import {normalizeLabel, isDir} from "./settings_utils"

const settingsService = new SettingsService2()

const debounceTimers = []
const oldValues = {}

let currentPageId = ''

export const LOADING_ALL = "__all__"
export const LOADING_DIR = "__dir__"
export const readOnlyProps = {
	talk_formDefLabel: "",
	talk_formDefPlaceholder: "",
	talk_formDefReal: "",
	talk_formDefVirtual: "",
	talk_integration_disabled: false,
	bbb_integration_disabled: false,
	// reminder related props
	bjm: '',
	cliUrl: '',
	defaultLang: '',
	token: ''
}
export const useSettingsStore = defineStore('settings', {
	state: () => ({
		settings: {
			enabled: false,
			label: '',

			organization: '',
			email: '',
			address: '',
			phone: '',

			confirmedRdrUrl: '',
			confirmedRdrId: false,
			confirmedRdrData: false,

			mainCalId: '-1',
			destCalId: '-1',
			nrSrcCalId: '-1',
			nrDstCalId: '-1',
			nrPushRec: true,
			nrRequireCat: false,
			nrAutoFix: false,
			tmmDstCalId: '-1',
			tmmMoreCals: [],
			tmmSubscriptions: [],
			tmmSubscriptionsSync: '0',

			prepTime: '0',
			bufferBefore: 0,
			bufferAfter: 0,
			whenCanceled: 'mark',
			allDayBlock: false,
			titleTemplate: '',
			privatePage: false,
			tsMode: '2',

			icsFile: false,
			skipEVS: false,
			attMod: true,
			attDel: true,
			meReq: false,
			meConfirm: false,
			meCancel: false,
			vldNote: '',
			cnfNote: '',
			icsNote: '',

			formTitle: '',
			nbrWeeks: '1',
			showEmpty: true,
			startFNED: false,
			showWeekends: false,
			time2Cols: false,
			endTime: false,
			hidePhone: false,
			gdpr: '',
			gdprNoChb: false,
			pageTitle: '',
			prefillInputs: 0,
			prefilledType: 0,
			formFinishText: '',
			metaNoIndex: false,
			pageStyle: '',
			useNcTheme: false,
			template_data: [],
			template_info: {
				tzName: '',
				tzData: ''
			},
			fi_html: '',
			fi_json: [],

			secHcapSiteKey: '',
			secHcapSecret: '',
			secHcapEnabled: false,
			secEmailBlacklist: [],

			reminders_data_0_seconds: '0',
			reminders_data_0_actions: false,
			reminders_data_1_seconds: '0',
			reminders_data_1_actions: false,
			reminders_data_2_seconds: '0',
			reminders_data_2_actions: false,
			reminders_moreText: '',
			reminders_friday: false,

			talk_enabled: false,
			talk_delete: false,
			talk_emailText: '',
			talk_lobby: false,
			talk_password: false,
			talk_nameFormat: 0,
			talk_formFieldEnable: false,
			talk_formLabel: '',
			talk_formPlaceholder: '',
			talk_formTxtReal: '',
			talk_formTxtVirtual: '',
			talk_formTxtTypeChange: '',

			bbbEnabled: false,
			bbbDelete: true,
			bbbAutoDelete: true,
			bbbPassword: false,
			bbbFormEnabled: false,

			debugging_mode: 0,
		},
		dirSettings: {
			/** @type {[{title:string, subTitle:string, text:string, url:string}]} */
			dirItems: [],
			privatePage: false,
			pageTitle: '',
			pageStyle: '',
			useNcTheme: false,
		},
		/** @type {[{id: string, name: string, color: string, isReadOnly: boolean, isSubscription: boolean}]} */
		calendars: [],
		k: false,
		loading: {},
	}),
	actions: {
		async getAllSettings(pageId) {

			this._updateLoading(LOADING_ALL, true)

			return settingsService.getSettings(pageId).then(res => {
				const hasData = res !== null
				if (hasData) {
					const data = res.data

					if (isDir(pageId)) {
						for (const prop in this.dirSettings) {
							if (data.settings.hasOwnProperty(prop)) {
								this.dirSettings[prop] = data.settings[prop]
							}
						}
					} else {
						for (const prop in this.settings) {
							if (data.settings.hasOwnProperty(prop)) {
								this.settings[prop] = data.settings[prop]
							}
						}
						this.settings.label = normalizeLabel(this.settings.label)

						if (data.settings.hasOwnProperty('reminders')) {
							Object.assign(this.settings, this._flattenReminders(data.settings['reminders'], '_'))
						}

						this.calendars = data.cals.split(String.fromCharCode(31))
							.map(item => {
								const cal = item.split(String.fromCharCode(30))
								return {
									name: cal[0],
									color: cal[1],
									id: cal[2],
									isReadOnly: cal[3] === '1',
									isSubscription: cal[4] === '1'
								}
							})
						this.k = !!data.k
						this.settings['__ckey'] = ""

						for (const prop in readOnlyProps) {
							if (data.settings[prop]) {
								readOnlyProps[prop] = data.settings[prop]
							}
						}
					}
					currentPageId = pageId
				}
				this._updateLoading(LOADING_ALL, false)
				return hasData
			})
		},

		setOne(key, value, debounce = false, callback = undefined) {
			if (!debounce) {
				this._setOne(key, value).then(res => {
					if (callback) {
						callback(res)
					}
				})
			} else {
				if (debounceTimers[key]) {
					clearTimeout(debounceTimers[key])
				}
				debounceTimers[key] = setTimeout(() => {
					this._setOne(key, value).then(res => {
						if (callback) {
							callback(res)
						}
					})
					delete debounceTimers[key]
				}, 1000)
			}
		},

		/**
		 * @param {String} key
		 * @param value
		 * @returns {Promise<*|T|null>}
		 * @private
		 */
		async _setOne(key, value) {

			if (!currentPageId) {
				showError("error: page id missing")
				return null
			}

			const settings = currentPageId[0] === 'd'
				? this.dirSettings
				: this.settings

			// the oldValue is needed in case the call to backend errors out
			let oldValue
			if (oldValues.hasOwnProperty(key)) {
				// this is probably an input type="text" or a textarea
				// and the oldValues[key] was set in "@focus" we are using ".sync"
				// to set the this.settings[key]
				oldValue = oldValues[key]
				delete oldValues[key]
			} else {
				// the this.settings[key] is NOT ".sync"ed and it holds the oldValue
				oldValue = settings[key]
				settings[key] = value
			}
			if (oldValue === value) {
				return value
			}
			this._updateLoading(key, true)

			let _key
			let _value
			if (key.startsWith('reminders_')) {
				// reminders are special
				_key = 'reminders'
				_value = this._inflateReminders(settings, '_')
			} else {
				_key = key
				_value = value
			}

			return settingsService.setOne(currentPageId, _key, _value).then(res => {
				if (res === null || res.status === 202) {
					settings[key] = oldValue
				}
				this._updateLoading(key, false)
				return res
			})
		},

		/**
		 * @param {String} key
		 * @param {any} value
		 */
		setOldValue(key, value) {
			oldValues[key] = value
		},

		cancelServiceRequest(key = undefined) {
			if (!key) {
				this.loading = {}
			}
			settingsService.cancel(key)
		},

		_updateLoading(prop, isLoading) {
			if (isLoading) {
				this.loading[prop] = true
			} else {
				delete this.loading[prop]
			}
			this.loading = {...this.loading}
		},

		_flattenReminders(data, separator, prefix = 'reminders') {
			if (data === null) {
				return {}
			} else if (typeof data === 'object') {
				const obj = {}
				for (const prop in data) {
					const k = '' + prefix + separator + prop
					const v = data[prop]
					if (typeof v === 'object') {
						Object.assign(obj, this._flattenReminders(v, separator, k));
					} else {
						obj[k] = v
					}
				}
				return obj;
			} else {
				const obj = {};
				obj['' + prefix] = data
				return obj
			}
		},

		_inflateReminders(data, separator) {
			const reminders = {};
			for (const prop in data) {
				if (!prop.startsWith('reminders_')) {
					continue;
				}
				const keys = prop.split(separator)
				const klm = keys.length - 1
				for (let i = 1, obj = reminders; i < keys.length; i++) {
					const key = keys[i]
					if (!obj.hasOwnProperty(key)) {
						// three possibilities here:
						//  1. we can be at value
						//  2. next key is an object
						//  3. next key is an array
						obj[key] = i === klm
							? data[prop] // at value
							: /^\d$/.test(keys[i + 1])
								? [] // numeric key, create array
								: {} // string key, create object
					}
					obj = obj[key]
				}
			}
			return reminders
		}
	},
})
