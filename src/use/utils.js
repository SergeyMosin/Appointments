import {linkTo} from "@nextcloud/router";
import SettingsService2 from "../services/SettingsService2";
import axios from "@nextcloud/axios";
import {showError} from "@nextcloud/dialogs";

export async function getTimezone(pageId, calId) {

	let userTz = 'Europe/London' // default(fallback)
	const service = new SettingsService2()
	return service.getCalendarTimezone(pageId, calId).then(res => {
		if (res !== null && res.data && res.data.toLowerCase() !== 'utc') {
			// we have a user timezone
			userTz = res.data.trim()
		}
		return axios.get(linkTo('appointments', 'ajax/zones.js'))
	}).then(res => {
		if (res.status === 200) {
			let tzd = res.data
			if (typeof tzd === "object"
				&& tzd.hasOwnProperty('aliases')
				&& tzd.hasOwnProperty('zones')) {

				let tzs = ""
				if (tzd.zones[userTz] !== undefined) {
					tzs = tzd.zones[userTz].ics.join("\r\n")

				} else if (tzd.aliases[userTz] !== undefined) {
					let alias = tzd.aliases[userTz].aliasTo
					if (tzd.zones[alias] !== undefined) {
						userTz = alias
						tzs = tzd.zones[alias].ics.join("\r\n")
					}
				}
				return {
					name: userTz,
					data: "BEGIN:VTIMEZONE\r\nTZID:" + userTz + "\r\n" + tzs.trim() + "\r\nEND:VTIMEZONE"
				}
			} else {
				throw new Error("bad tzr.data")
			}
		} else {
			throw new Error("bad status: " + res.status)
		}
	}).catch(err => {
		console.error('getTimezone error:', err)
		return null
	})
}

export function parsePageUrls(urlsData) {
	const arr = urlsData.split(String.fromCharCode(31))
	if (arr.length < 2) {
		console.error('bad urlsData:', urlsData)
		showError('error: can not parse page URL')
		return null;
	} else {
		return arr
	}
}