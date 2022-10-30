#! /usr/local/bin/node

// borrowed from: https://github.com/nextcloud/server/blob/master/core/src/icons.js

/* eslint-disable quote-props */
/* eslint-disable node/no-unpublished-import */
const path = require('path')
const fs = require('fs')
const sass = require('sass')


const outputFile = path.join(__dirname, 'scss', '_icons.css')

const colors = {
    dark: '000',
    white: 'fff',
    yellow: 'FC0',
    red: 'e9322d',
    orange: 'eca700',
    green: '46ba61',
    grey: '969696',
}

const variables = {}
const icons = {

    'appt-public-page': path.join(__dirname, 'img', 'appt-public-page.svg'),
    'appt-public-pages': path.join(__dirname, 'img', 'appt-public-pages.svg'),
    'appt-go-back': path.join(__dirname, 'img', 'appt-go-back.svg'),
    'appt-calendar-clock': path.join(__dirname, 'img', 'appt-calendar-clock.svg'),
    'appt-timeslot-settings': path.join(__dirname, 'img', 'appt-timeslot-settings.svg'),
    'appt-calendar': path.join(__dirname, 'img', 'appt-calendar.svg'),
    'appt-key': path.join(__dirname, 'img', 'appt-key.svg'),
    'sched-mode': path.join(__dirname, 'img', 'sched-mode.svg'),
    'sched-mode-wt': path.join(__dirname, 'img', 'sched-mode-wt.svg'),
    'appt-reminder': path.join(__dirname, 'img', 'appt-reminder.svg'),
    'appt-private-mode-page': path.join(__dirname, 'img', 'appt-private-mode-page.svg'),

    // 'add': path.join(__dirname, '../img', 'actions', 'add.svg'),
    // 'address': path.join(__dirname, '../img', 'actions', 'address.svg'),
    // 'alert-outline': path.join(__dirname, '../img', 'actions', 'alert-outline.svg'),
}

const iconsColor = {}

// use this to define aliases to existing icons
// key is the css selector, value is the variable
const iconsAliases = {}

const colorSvg = function (svg = '', color = '000') {
    if (!color.match(/^[0-9a-f]{3,6}$/i)) {
        // Prevent not-sane colors from being written into the SVG
        console.warn(color, 'does not match the required format')
        color = '000'
    }

    // add fill (fill is not present on black elements)
    const fillRe = /<((circle|rect|path)((?!fill)[a-z0-9 =".\-#():;,])+)\/>/gmi
    svg = svg.replace(fillRe, '<$1 fill="#' + color + '"/>')

    // replace any fill or stroke colors
    svg = svg.replace(/stroke="#([a-z0-9]{3,6})"/gmi, 'stroke="#' + color + '"')
    svg = svg.replace(/fill="#([a-z0-9]{3,6})"/gmi, 'fill="#' + color + '"')

    return svg
}

const generateVariablesAliases = function (invert = false) {
    let css = ''
    Object.keys(variables).forEach(variable => {
        if (variable.indexOf('original-') !== -1) {
            let finalVariable = variable.replace('original-', '')
            if (invert) {
                finalVariable = finalVariable.replace('white', 'tempwhite')
                    .replace('dark', 'white')
                    .replace('tempwhite', 'dark')
            }
            css += `${finalVariable}: var(${variable});`
        }
    })
    return css
}

const formatIcon = function (icon, invert = false) {
    const color1 = invert ? 'white' : 'dark'
    const color2 = invert ? 'dark' : 'white'
    return `
	.icon-${icon},
	.icon-${icon}-dark {
		background-image: var(--icon-${icon}-${color1});
	}
	.icon-${icon}-white,
	.icon-${icon}.icon-white {
		background-image: var(--icon-${icon}-${color2});
	}`
}
const formatIconColor = function (icon) {
    const {color} = iconsColor[icon]
    return `
	.icon-${icon} {
		background-image: var(--icon-${icon}-${color});
	}`
}
const formatAlias = function (alias, invert = false) {
    let icon = iconsAliases[alias]
    if (invert) {
        icon = icon.replace('white', 'tempwhite')
            .replace('dark', 'white')
            .replace('tempwhite', 'dark')
    }
    return `
	.${alias} {
		background-image: var(--${icon})
	}`
}

let css = ''
Object.keys(icons).forEach(icon => {
    const path = icons[icon]

    const svg = fs.readFileSync(path, 'utf8')
    const darkSvg = colorSvg(svg, '000000')
    const whiteSvg = colorSvg(svg, 'ffffff')

    // console.log("dark:",darkSvg)
    // console.log("white:",whiteSvg)

    variables[`--original-icon-${icon}-dark`] = Buffer.from(darkSvg, 'utf-8').toString('base64')
    variables[`--original-icon-${icon}-white`] = Buffer.from(whiteSvg, 'utf-8').toString('base64')
})

Object.keys(iconsColor).forEach(icon => {
    const {path, color} = iconsColor[icon]

    const svg = fs.readFileSync(path, 'utf8')
    const coloredSvg = colorSvg(svg, colors[color])
    variables[`--icon-${icon}-${color}`] = Buffer.from(coloredSvg, 'utf-8').toString('base64')
})

// ICONS VARIABLES LIST
css += ':root {'
Object.keys(variables).forEach(variable => {
    const data = variables[variable]
    css += `${variable}: url(data:image/svg+xml;base64,${data});`
})
css += '}'

// DEFAULT THEME
css += 'body {'
css += generateVariablesAliases()
Object.keys(icons).forEach(icon => {
    css += formatIcon(icon)
})
Object.keys(iconsColor).forEach(icon => {
    css += formatIconColor(icon)
})
Object.keys(iconsAliases).forEach(alias => {
    css += formatAlias(alias)
})
css += '}'

// DARK THEME MEDIA QUERY
css += '@media (prefers-color-scheme: dark) { body {'
css += generateVariablesAliases(true)
css += '}}'

// DARK THEME
css += '[data-themes*=light] {'
css += generateVariablesAliases()
css += '}'

// DARK THEME
css += '[data-themes*=dark] {'
css += generateVariablesAliases(true)
css += '}'

// WRITE CSS
// console.log("css:",sass.compileString(css).css)
fs.writeFileSync(outputFile, sass.compileString(css, {style: "expanded"}).css)
