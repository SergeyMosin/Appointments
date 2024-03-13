<script setup>
import LabelAccordion from "../LabelAccordion.vue";
import {computed, onMounted, ref} from "vue";
import {showWarning} from "@nextcloud/dialogs";

const propName = 'fi_json'

const props = defineProps({
	store: {
		type: Object,
		required: true
	}
})

const model = ref('')
const fiJsonToModel = () => {
	try {
		const data = props.store.settings[propName]
		// "name" prop is internal
		if (Array.isArray(data)) {
			// multiple extra fields
			data.forEach(item => {
				delete item.name
			})
		} else {
			// single field
			delete data.name
		}
		model.value = JSON.stringify(data, null, 2) || ''
	} catch (e) {
		showWarning('warning: bad form JSON Object')
		console.error(e)
		model.value = ''
	}
}

onMounted(() => {
	fiJsonToModel()
})

const isLoading = computed(() => props.store.loading[propName] === true)

const handleFocus = () => {
	if (model.value === '') {
		fiJsonToModel()
	}
}
const handleBlur = () => {
	let fiJson
	try {
		if (model.value.trim() === '') {
			fiJson = []
		} else {
			fiJson = JSON.parse(model.value)
		}
	} catch (e) {
		showWarning('warning: can not parse JSON Object')
		console.error(e)
		return
	}
	props.store.setOne(propName, fiJson)
}

</script>

<template>
	<div>
		<LabelAccordion
				:label="t('appointments','Extra Fields (JSON Object)')"
				:loading="isLoading">
			<template #helpPopover>
				{{ t('appointments', 'Advanced feature, more info at: ') }}
				<a href="https://github.com/SergeyMosin/Appointments/issues/24#issuecomment-721103321" target="_blank" class="srgdev-appt-hs-link">https://github.com/SergeyMosin/Appointments/issues/24#issuecomment-721103321</a>
			</template>
		</LabelAccordion>
		<textarea
				placeholder="[{...}]"
				class="ps-textarea ps-vert-spacing"
				v-model="model"
				@focus="handleFocus"
				@blur="handleBlur"/>
	</div>
</template>

<style scoped>

</style>