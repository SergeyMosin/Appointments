const assert = require('assert').strict
const fs = require('fs')
const vm = require('vm')
const path = require('path')

class Element {
	constructor() {
		this.children = []
		this.style = {}
		this.offsetHeight = 5
	}

	appendChild(child) {
		this.children.push(...(child.fragment ? child.children : [child]))
		return child
	}

	removeChild(child) {
		this.children.splice(this.children.indexOf(child), 1)
	}

	addEventListener() {}

	get offsetTop() { return parseFloat(this.style.top) || 0 }
	get firstElementChild() { return this.children[0] }
	get lastElementChild() { return this.children[this.children.length - 1] }
}

const document = {
	documentElement: {lang: 'en', hasAttribute: () => false},
	createElement: () => new Element(),
	createDocumentFragment: () => Object.assign(new Element(), {fragment: true}),
	createTextNode: text => ({textContent: text}),
}

const source = fs.readFileSync(path.join(__dirname, '../src/grid.js'), 'utf8')
const grid = vm.runInNewContext(
	source.replace('export default new _apptGridMaker()', '_apptGridMaker()'),
	{document, window: {Intl}, Intl, Date, Math, Number},
	{filename: 'grid.js'}
)
const outer = new Element()
outer.appendChild(new Element())
const container = new Element()
container.parentElement = outer
grid.setup(container, 1)
const column = container.children[1].children[0]

grid.addAppt(0, 30, 1, 0, null)
assert.equal(column.children[0].uTop, 96)

for (const [input, expected] of [
	['00:00', 0], ['07:15', 87], ['08:00', 96], ['18:45', 225],
	['23:55', 287], ['27:13', 96], ['09:02', 96]
]) {
	grid.setStartTime(input)
	grid.resetAllColumns()
	if (expected === 287) {
		grid.scrollGridToTopElm()
		assert.equal(outer.scrollTop, expected * 7)
		continue
	}
	grid.addAppt(0, 30, 1, 0, null)
	assert.equal(column.children[column.children.length - 1].uTop, expected, input)
	assert.equal(outer.scrollTop, expected * 7, input)
}

console.log('Grid start time: default and 7 cases passed')
