/**
 * Detects a color from a given string
 *
 * @param {String} color The color to get the real RGB hex string from
 * @returns {string} color detected, "#8768bd" if not
 */
export function detectColor(color) {
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
