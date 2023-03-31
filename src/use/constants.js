const MODAL = {
	NONE: 0,
	INFO: 1,
	CONTRIBUTION: 2,
	ERROR: 3,
}
Object.freeze(MODAL)

const TS_MODE = {
	SIMPLE: '0',
	EXTERNAL: '1',
	TEMPLATE: '2',
}
Object.freeze(TS_MODE)

const CONTEXT = {
	PREVIEW: 1,
	EDITOR: 2,
}
Object.freeze(CONTEXT)

const CK = {
	PAGES: '_p',
	URL: '_u',
	SETTINGS: '_s',
	TIMEZONE: '_t',
	CALWEEK: '_w',
	DEBUG: '_d',
}
Object.freeze(CK)

const INDEX = {
	NONE: -1,
	NEW: -2,
}
Object.freeze(INDEX)


export {
	MODAL,
	TS_MODE,
	CONTEXT,
	CK,
	INDEX
}
