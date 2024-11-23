<script setup>
import {
	NcButton,
	NcModal,
	NcTextField
} from "@nextcloud/vue";
import LabelAccordion from "../LabelAccordion.vue";
import VueSlider from "vue-slider-component";
import {reactive} from "vue";
import {MODAL} from "../../use/constants";

const DUR_MAX = 480

const emit = defineEmits(['close-modal', 'tmpl-add-appts', 'tmpl-update-appt', 'show-info-modal'])
const props = defineProps({
	data: {
		type: Object,
		required: true,
		// elm: HTMLDivElement | null,
		// cid: Number,
		// gridShift: Number,
		// k: Boolean,
	},
})

const state = reactive({
	count: 8,
	durations: [30],
	title: '',
})

if (props.data.elm !== null) {
	// editing an appointment
	state.durations = [...props.data.elm.dur]
	state.title = props.data.elm.title
}


const header = (() => {
	let str = ""
	if (window.Intl && typeof window.Intl === "object") {
		const lang = document.documentElement.hasAttribute('data-locale')
				? [document.documentElement.getAttribute('data-locale').replaceAll('_', '-'), document.documentElement.lang]
				: [document.documentElement.lang]
		if (props.data.elm === null) {
			// adding
			if (props.data.cid > -1) {
				const d = new Date()
				d.setDate(d.getDate() - d.getDay() + 1 - props.data.gridShift + props.data.cid)
				str = new Intl.DateTimeFormat(lang,
						{weekday: "long"}).format(d)
			}
		} else if (props.data.elm.uTop !== undefined && props.data.elm.cID !== undefined) {
			// single appt. edit

			const d = new Date()
			d.setDate(d.getDate() - d.getDay() + 1 - props.data.gridShift + props.data.elm.cID)

			const SH = 0 // same as grid.js
			const minTotal = SH * 60 + props.data.elm.uTop * 5
			const hours = (minTotal / 60) | 0
			d.setHours(hours, minTotal - hours * 60)
			str = new Intl.DateTimeFormat(lang,
					{weekday: "long", hour: "2-digit", minute: "2-digit"}).format(d)
		}
	}
	return str
})()


const handleUpdateShow = (evt) => {
	if (evt === false) {
		emit('close-modal')
	}
}

const addDurationHandler = () => {

	if (!Array.isArray(state.durations)) {
		state.durations = [state.durations]
	}

	if (props.data.k === false && state.durations.length > 1) {
		emit('show-info-modal', {
			type: MODAL.CONTRIBUTION,
			message: t('appointments', 'More than two duration choices')
		})
		return
	}

	if (state.durations.length > 7) return

	const a = [...state.durations, DUR_MAX].sort((a, b) => a - b)
	// find the largest space
	let s = 0
	for (let sp = 0, spn = 0, i = 0, l = a.length - 1; i < l; i++) {
		spn = a[i + 1] - a[i]
		if (spn > sp) {
			sp = spn
			s = i
		}
	}
	state.durations.push((((a[s] + ((a[s + 1] - a[s]) >> 1)) / 5) | 0) * 5)

}
const removeDurationHandler = () => {
	if (Array.isArray(state.durations) && state.durations.length > 1) {
		state.durations.splice(-1, 1)
	}
}

const durationsTooltipFormatter = (v) => {
	const h = (v / 60) | 0
	const m = v - h * 60
	return h + ":" + (m > 9 ? "" + m : "0" + m)
}

const handlePrimaryClick = () => {
	if (props.data.elm === null) {
		emit('tmpl-add-appts', {
			count: state.count,
			dur: Array.isArray(state.durations)
					? state.durations.sort((a, b) => a - b)
					: [state.durations],
			title: state.title,
			cid: props.data.cid
		})
	} else {
		emit('tmpl-update-appt', {
			dur: Array.isArray(state.durations)
					? state.durations.sort((a, b) => a - b)
					: [state.durations],
			title: state.title,
			cIdx: props.data.elm.cIdx,
			cID: props.data.elm.cID
		})
		emit('close-modal')
	}
}

const handleDeleteClick = () => {
	// reuse event
	emit('tmpl-update-appt', {
		cIdx: props.data.elm.cIdx,
		cID: props.data.elm.cID,
		del: true
	})
	emit('close-modal')
}

const primaryButtonText = props.data.elm === null
		? t('appointments', 'Add')
		: t('appointments', 'OK')

</script>

<template>
	<NcModal
			style="z-index: 10002"
			labelId="srgdev_appts_editor_name"
			:setReturnFocus="false"
			:show="data !== null"
			@update:show="handleUpdateShow">
		<h4 id="srgdev_appts_editor_name" v-if="header!==''" class="tao-h4">{{ header }}</h4>
		<div class="srgdev-appt-modal-wrap" style="text-align: left">
			<template v-if="props.data.elm===null">
				<LabelAccordion
						:label="t('appointments', 'Number of Appointments')"
						for="atam-slider-count"/>
				<vue-slider
						:min="1"
						:max="32"
						tooltip="always"
						tooltipPlacement="bottom"
						id="atam-slider-count"
						class="ps-slider appt-slider"
						v-model="state.count"/>
			</template>
			<div class="pml-cont">
				<label class="slider-label">{{ t('appointments', 'Duration (hours:minutes)') }}</label>
				<span class="pml-p" @click="addDurationHandler">+</span>
				<span class="pml-m" @click="removeDurationHandler">−</span>
			</div>
			<vue-slider
					:min="5"
					:max="DUR_MAX"
					:order="false"
					:interval="5"
					:process="false"
					tooltip="always"
					tooltipPlacement="bottom"
					:tooltip-formatter="durationsTooltipFormatter"
					class="ps-slider appt-slider"
					v-model="state.durations"/>
			<LabelAccordion
					:label="t('appointments', 'Title')"
					for="atam-input-title"/>
			<NcTextField
					id="atam-input-title"
					placeholder="Optional"
					:value.sync="state.title"
					:label-outside="true"/>
			<div class="actions-cont">
				<NcButton
						class="actions-cont-button"
						:aria-label="primaryButtonText"
						type="primary"
						@click="handlePrimaryClick">
					{{ primaryButtonText }}
				</NcButton>
				<NcButton
						v-if="props.data.elm!==null"
						class="actions-cont-button"
						:aria-label="t('appointments', 'Delete')"
						@click="handleDeleteClick">
					{{ t('appointments', 'Delete') }}
				</NcButton>
				<NcButton
						class="actions-cont-button"
						:aria-label="t('appointments', 'Cancel')"
						@click="handleUpdateShow(false)">
					{{ t('appointments', 'Cancel') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<style scoped>

.tao-h4 {
	font-size: 100%;
	padding: 0 0 1em 2em;
	border-bottom: 1px solid var(--color-border);
	font-weight: bold;
	margin: 1em 0;
}

.appt-slider {
	margin-bottom: 3em;
	margin-right: 0;
	box-sizing: content-box;
}

.pml-cont {
	position: relative;
}

.pml-m,
.pml-p {
	font-size: 125%;
	position: absolute;
	height: 1em;
	width: 1em;
	line-height: 1em;
	top: 50%;
	margin-top: -.5em;
	text-align: center;
	cursor: pointer;
	color: var(--color-text-light);
}

.pml-m:hover,
.pml-p:hover {
	color: var(--color-main-text);
	transform: scale(1.2);
}

.pml-p {
	right: 1.375em;
}

.pml-m {
	right: 0;
}

.actions-cont {
	display: flex;
	flex-direction: row;
	margin-top: 2em;
}

.actions-cont-button {
	margin-right: 1em;
	min-width: 5em;
}
</style>