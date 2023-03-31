import SettingsService2 from "../services/SettingsService2";
import {defineStore} from 'pinia'
import {showError} from "@nextcloud/dialogs"
import {normalizeLabel} from "./settings_utils"

const settingsService = new SettingsService2()
const NOT_LOADING = ""
const LOADING_ANY = "any"

export const usePagesStore = defineStore('pages', {
	state: () => ({
		/** @type {[{id: string, label: string, type: string, enabled: boolean}]} */
		pages: [],
		/** @type {{id: string, label: string, hasData: boolean, showInNavigation: boolean}} */
		dirPage: {},
		loading: NOT_LOADING
	}),
	actions: {
		getAllPages(loading = LOADING_ANY) {
			this.loading = loading
			settingsService.getPages().then(res => {
				this.pages = []
				this.dirPage = {
					id: 'd0',
					label: t('appointments', 'Directory Page'),
					hasData: false,
					showInNavigation: false
				}
				if (res !== null) {
					for (let i = 0, data = res.data, l = data.length; i < l; i++) {
						const item = data[i]
						if (item.type === 'page') {
							item.label = normalizeLabel(item.label)
							this.pages.push(item)
						} else if (item.type === 'dir') {
							this.dirPage.id = item.id
							this.dirPage.hasData = item.hasData
						}
					}
					this.dirPage.showInNavigation = this.pages.length > 0
				}
				this.loading = NOT_LOADING
			})
		},

		async toggleEnabled(pageId, loading = LOADING_ANY) {
			const page = findPage(pageId, this.pages)
			if (!page) {
				return null
			}
			this.loading = loading
			return settingsService.setOne(pageId, 'enabled', !page.enabled).then(res => {
				if (res !== null && res.status !== 202) {
					page.enabled = !page.enabled
				}
				this.loading = NOT_LOADING
				return res
			})
		},

		updatePageLabel(pageId, label, loading = LOADING_ANY) {
			const page = findPage(pageId, this.pages)
			if (!page) {
				return
			}
			this.loading = loading
			const oldLabel = label
			page.label = label
			settingsService.setOne(pageId, 'label', label).then(res => {
				if (res === null) {
					page.label = oldLabel
				}
				this.loading = NOT_LOADING
			})
		},

		async addNewPage(loading = LOADING_ANY) {
			this.loading = loading
			return settingsService.addNewPage().then(res => {
				if (res !== null && res.status !== 202) {
					this.getAllPages(loading)
				} else {
					this.loading = NOT_LOADING
				}
				return res
			})
		},

		async deletePage(pageId, loading = LOADING_ANY) {
			this.loading = loading
			return settingsService.deletePage(pageId).then(res => {
				if (res !== null) {
					if (res.status === 202 && res.data.message) {
						showError(res.data.message)
					}
					this.getAllPages(loading)
				} else {
					this.loading = NOT_LOADING
				}
				return res
			})
		},

		async getPageUrl(pageId, loading = LOADING_ANY) {
			this.loading = loading
			return settingsService.getPageUrl(pageId).then(res => {
				this.loading = NOT_LOADING
				return res
			})
		},

		getPageById(pageId) {
			return findPage(pageId, this.pages)
		},

		cancelAllRequest() {
			settingsService.cancel()
		}

	}
})

const findPage = (pageId, pages) => {
	const page = pages.find((item) => {
		if (item.id === pageId) {
			return item
		}
	})
	if (!page) {
		showError(t('appointments', 'error: page not found'))
		return null
	}
	return page
}