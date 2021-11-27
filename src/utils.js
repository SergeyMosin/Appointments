import {linkTo} from "@nextcloud/router";
import axios from "@nextcloud/axios";
import {showWarning} from "@nextcloud/dialogs"

/**
 * Detects a color from a given string
 *
 * @param {String} color The color to get the real RGB hex string from
 * @returns {string} color detected, "#8768bd" if not
 */
function detectColor(color) {
    if (/^(#)((?:[A-Fa-f0-9]{3}){1,2})$/.test(color)) { // #ff00ff and #f0f
        return color
    } else if (/^((?:[A-Fa-f0-9]{3}){1,2})$/.test(color)) { // ff00ff and f0f
        return '#' + color
    } else if (/^(#)((?:[A-Fa-f0-9]{8}))$/.test(color)) { // #ff00ffff and #f0ff
        return color.substr(0, 7)
    } else if (/^((?:[A-Fa-f0-9]{8}))$/.test(color)) { // ff00ffff and f0ff
        return '#' + color.substr(0, 6)
    }

    return "#8768bd"
}

/**
 * @param {function} getState
 * @param {string} calId
 */
const getTimezone = async (getState, calId) => {

    let res = await getState("get_tz", calId)

    if (res !== null) {

        if (res.toLowerCase() === 'utc') {
            res = "Europe/London"
            showWarning(window.t('appointments', 'Using fallback time zone: {timeZoneName}', {
                timeZoneName: res
            }))
            console.error("can not get user timezone for this calendar using " + res)
        }

        let url = linkTo('appointments', 'ajax/zones.js')
        const tzr = await axios.get(url)
        if (tzr.status === 200) {

            let tzd = tzr.data
            if (typeof tzd === "object"
                && tzd.hasOwnProperty('aliases')
                && tzd.hasOwnProperty('zones')) {

                let tzs = ""
                if (tzd.zones[res] !== undefined) {
                    tzs = tzd.zones[res].ics.join("\r\n")

                } else if (tzd.aliases[res] !== undefined) {
                    let alias = tzd.aliases[res].aliasTo
                    if (tzd.zones[alias] !== undefined) {
                        res = alias
                        tzs = tzd.zones[alias].ics.join("\r\n")
                    }
                }

                return {
                    name: res,
                    data: "BEGIN:VTIMEZONE\r\nTZID:" + res.trim() + "\r\n" + tzs.trim() + "\r\nEND:VTIMEZONE"
                }
            } else {
                throw new Error("Bad tzr.data")
            }
        } else {
            throw new Error("Bad status: " + tzr.status)
        }
    } else {
        throw new Error("Can't get_tz")
    }
}


export {detectColor, getTimezone}