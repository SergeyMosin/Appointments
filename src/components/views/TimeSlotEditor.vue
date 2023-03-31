<script setup>
import {MODAL, TS_MODE} from "../../use/constants";
import {
	NcActions,
	NcActionButton,
	NcActionInput,
	NcButton,
	NcLoadingIcon
} from "@nextcloud/vue";
import IconPlus from "vue-material-design-icons/Plus.vue";
import IconDelete from "vue-material-design-icons/Delete.vue";
import IconCopyNext from "vue-material-design-icons/PageNextOutline.vue";
import gridMaker from "../../grid.js"
import {onMounted, reactive, nextTick, ref} from 'vue'
import {useSettingsStore} from "../../stores/settings";
import WeeklyApptsEditorModal from "../modals/WeeklyApptsEditorModal.vue";
import SettingsInfoModal from "../modals/SettingsInfoModal.vue";
import SettingsService2 from "../../services/SettingsService2";
import {showError, showSuccess} from "@nextcloud/dialogs";

const COL_COUNT = 7

const emit = defineEmits(['hide-timeslot-editor'])

const store = useSettingsStore()
const settings = store.settings

const grid_cont = ref(null)

/**
 * data is only set for Simple editor (in weekly template mode data is {})
 * @type {Readonly<ExtractPropTypes<{data: {
 *  tz:String,
 *  week:Number,
 *  dur:Number,
 *  pageId:String,
 *  calColor:String,
 *  calName:String
 * }}>>}
 */
const props = defineProps({
	data: [Object, null]
})

const gridMode = settings.tsMode === TS_MODE.TEMPLATE
		? gridMaker.MODE_TEMPLATE
		: gridMaker.MODE_SIMPLE

// calculate grid shift for template, because a week can start on a different
// day in different countries, in template data array 0 = Monday,
// so when the week start on:
//  Monday:   gridShift = 0
//  Sunday:   gridShift = 1
//  Saturday: gridShift = 2
//
// https://github.com/nextcloud/server/blob/62d83123d1272d2d042ec294c6d65cd79bbc05ca/lib/private/Template/JSConfigHelper.php#L251
const gridShift = window.firstDay === 0 ? 1 : window.firstDay === 6 ? 2 : 0

gridMaker.resetAllColumns()
gridMaker.setMode(gridMode)

const getStartDate = () => {
	if (gridMode === gridMaker.MODE_TEMPLATE) {
		// startDate must be 00:00 on a Monday
		const startDate = new Date()

		startDate.setHours(0, 0, 0)
		let day = startDate.getDay()
		if (day === 0) {
			day++
		} else {
			day = 1 - day
		}
		startDate.setDate(startDate.getDate() + day)

		if (gridShift !== 0) {
			// startDate is Monday 00:00:00 in grid mode
			// we need to adjust because a week can start on Mon, Sun or Sat
			startDate.setDate(startDate.getDate() - gridShift)
		}

		return startDate
	} else {
		// MODE_SIMPLE
		return new Date(props.data.week)
	}
}

const editor = reactive({
	/** @type {{ts:number,txt:string,w:string,n:string,hasAppts:boolean}[]} */
	header: gridMaker.makeHeader(getStartDate(), COL_COUNT),
	menuIndex: -1,
	simpleLen: 0,
	simpleTs: 0,
	modalData: null,
	infoModal: {
		type: MODAL.NONE,
		data: '',
	},
	loading: false
})

onMounted(() => {

	gridMaker.setup(grid_cont.value, COL_COUNT, 'srgdev-appt-grd-')

	if (gridMode === gridMaker.MODE_TEMPLATE) {
		gridMaker.addPastAppts(settings.template_data, null, gridShift)
		//activate non-empty columns
		settings.template_data.forEach((c, i) => {
			const iMod = (i + gridShift) % COL_COUNT
			if (editor.header[iMod] !== undefined) {
				editor.header[iMod].hasAppts = c.length > 0
			}
		})
	} else {

		editor.loading = true

		const startDate = getStartDate()
		const pd = startDate.getDate() + "-" + (startDate.getMonth() + 1) + "-" + startDate.getFullYear()

		const service = new SettingsService2()
		service.getCalendarWeek(props.data.pageId, pd).then((res) => {
			if (res === null) {
				state.error = t('appointments', 'error: cannot load calendar data');
			} else {
				gridMaker.addPastAppts(pd + String.fromCharCode(31) + res.data, props.data.calColor)
			}
			editor.loading = false
		})
	}
	nextTick(gridMaker.scrollGridToTopElm)
})

const close = () => {
	emit('hide-timeslot-editor')
}

const handleAddToCalendar = () => {
	const tsa = gridMaker.getStarEnds(props.data.week, props.data.tz === 'UTC')
	new SettingsService2().addSimpleAppointments({
		d: tsa.join(','),
		tz: props.data.tz,
		p: props.data.pageId
	}).then(res => {
		if (res === null) {
			showError(t('appointments', "Can't add appointments to calendar"))
		} else {
			showSuccess(t('appointments', "Appointments added to {calendarName} calendar", {calendarName: props.data.calName}))
			close()
		}
	})
}

const saveTemplate = () => {
	store.setOne('template_data', gridMaker.getTemplateData(gridShift), false,
			(d) => {
				if (d !== null) {
					emit('hide-timeslot-editor')
				}
			})
}

const editSingleAppt = (e) => {
	editor.modalData = {
		elm: e.detail,
		gridShift: gridShift,
		k: store.k
	}
}

const handleTemplateApptsEditor = (cid) => {
	editor.menuIndex = -1
	editor.modalData = {
		gridShift: gridShift,
		elm: null,
		cid: cid,
		k: store.k
	}
}

const handleTmplAddAppts = (evt) => {
	editor.modalData = null
	const count = (isNaN(evt.count) || evt.count < 1) ? 1 : evt.count
	gridMaker.addAppt(0, evt.dur[0], count, evt.cid, evt)
	editor.header[evt.cid].hasAppts = true
}

const handleSimpleAddAppts = (index) => {
	const hd = editor.header[index]
	const n = parseInt(hd.n)
	if (isNaN(n) || n < 1) {
		showError(t('appointments', 'Invalid number of appointments'))
		return
	}
	editor.menuIndex = -1
	const dur = isNaN(props.data.dur) || props.data.dur < 5 ? 5 : props.data.dur
	gridMaker.addAppt(0, dur, n, index, props.data.calColor)
	hd.hasAppts = true
}

const gridApptsDel = (index) => {
	editor.menuIndex = -1
	gridMaker.resetColumn(index)
	editor.header[index].hasAppts = false
}

const gridApptsCopy = (index) => {
	editor.menuIndex = -1
	const color = props.data && props.data.calColor ? props.data.calColor : null
	gridMaker.cloneColumns(index, index + 1, color)
	editor.header[index + 1].hasAppts = true
}

</script>

<template>
	<div class="srgdev-appt-grid-flex">
		<div v-show="gridMode===gridMaker.MODE_SIMPLE"
				 class="srgdev-appt-cal-view-btns">
			<button @click="handleAddToCalendar" class="primary">
				{{ t('appointments', 'Add to Calendar') }}
			</button>
			<button @click="close">
				{{ t('appointments', 'Discard') }}
			</button>
		</div>
		<div v-show="gridMode===gridMaker.MODE_TEMPLATE"
				 class="editor-button-cont-flex">
			<NcButton
					style="margin-right: 1em;overflow: hidden"
					class="editor-button"
					type="primary"
					:disabled="store.loading['template_data']===true"
					@click="saveTemplate()">
				<template #icon v-if="store.loading['template_data']===true">
					<NcLoadingIcon :size="20"/>
				</template>
				{{ t('appointments', 'Save') }}
			</NcButton>
			<NcButton
					style="margin-right: 3em"
					class="editor-button"
					@click="close">
				{{ t('appointments', 'Cancel') }}
			</NcButton>
			<div class="editor-hint">
				{{ t('appointments', 'Hint: right-click on an appointment to edit.') }}<br>
				{{ t('appointments', 'Time zone') }}: {{ settings.template_info.tzName }}
			</div>
		</div>
		<div v-if="editor.loading===true" class="editor-loading">
			Loading...
		</div>
		<div class="srgdev-appt-grid-flex-lower"
				 :class="{'editor-hidden':editor.loading===true}">
			<ul class="srgdev-appt-grid-header">
				<li v-for="(hi, index) in editor.header"
						class="srgdev-appt-gh-li"
						:style="{width:hi.w}">
					<div class="srgdev-appt-gh-txt">{{ hi.txt }}</div>
					<NcActions
							menuAlign="right"
							:open="editor.menuIndex===index"
							@open="editor.menuIndex=index"
							class="srgdev-appt-gh-act1">
						<NcActionInput
								v-if="gridMode===gridMaker.MODE_SIMPLE"
								:value.sync="hi.n"
								@submit="handleSimpleAddAppts(index)"
								class="srgdev-appt-gh-act-inp"
								type="number">
							<template #icon>
								<IconPlus :size="20"/>
							</template>
						</NcActionInput>
						<NcActionButton
								v-else
								:closeAfterClick="true"
								@click="handleTemplateApptsEditor(index)">
							<template #icon>
								<IconPlus :size="20"/>
							</template>
							{{ t('appointments', 'Add Appointments') }}
						</NcActionButton>
						<NcActionButton
								:disabled="!hi.hasAppts"
								:closeAfterClick="true"
								@click="gridApptsDel(index)">
							<template #icon>
								<IconDelete :size="20"/>
							</template>
							{{ t('appointments', 'Remove All') }}
						</NcActionButton>
						<NcActionButton
								:disabled="!hi.hasAppts"
								:closeAfterClick="true"
								v-if="index!==editor.header.length-1"
								@click="gridApptsCopy(index)">
							<template #icon>
								<IconCopyNext :size="20"/>
							</template>
							{{ t('appointments', 'Copy to Next') }}
						</NcActionButton>
					</NcActions>
				</li>
			</ul>
			<div @gridContext="editSingleAppt" ref="grid_cont" class="srgdev-appt-grid-cont"></div>
		</div>
		<WeeklyApptsEditorModal
				v-if="editor.modalData!==null"
				:data="editor.modalData"
				@tmpl-add-appts="handleTmplAddAppts"
				@tmpl-update-appt="(evt)=>{gridMaker.updateAppt(evt)}"
				@show-info-modal="(evt)=>{editor.infoModal=evt}"
				@close-modal="editor.modalData=null"/>
		<SettingsInfoModal
				v-if="editor.infoModal.type!==MODAL.NONE"
				:data="editor.infoModal"
				@hide-settings-modal="()=>{editor.infoModal.type=MODAL.NONE}"/>
	</div>
</template>

<style scoped>

.editor-loading {
	text-align: center;
	font-size: 120%
}

.editor-hidden {
	visibility: hidden
}

.editor-button-cont-flex {
	margin: 1em 0;
	display: flex;
	justify-content: center;
}

.editor-button {
	display: inline-flex;
}

.editor-hint {
	position: absolute;
	flex: 1;
	right: 2em;
	top: 1.5em;
	font-style: italic;
	font-size: 75%;
	color: var(--color-text-light);
	text-align: right;
	line-height: normal;
}
</style>

