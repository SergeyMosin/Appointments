<script>
let idCounter = 0
</script>

<script setup>
import {NcSelect} from "@nextcloud/vue";
import NcEllipsisedOption from "@nextcloud/vue/dist/Components/NcEllipsisedOption.js";
import {computed, nextTick, ref, useAttrs} from "vue";

const props = defineProps({
	selectedValue: [Number, String],
	placeholderLabel: String,
	required: Boolean
})
const attrs = useAttrs()
const select = ref(null)
const valueId = `ps-select-value-${++idCounter}`

const selectedOption = computed(() => attrs.options.find((item) => item.value === props.selectedValue) || null)
const placeholder = computed(() => props.placeholderLabel || t('appointments', 'Select One Option'))

// Dropping the focus handler stops the list from opening when the field is merely tabbed to; Down/Enter/Space and mouse still open it
const withoutFocus = ({focus, ...events}) => events

// The dropdown is appended to <body>; moving it into the enclosing dialog keeps focus inside the dialog while it is open
const handleOpen = () => nextTick(() => {
	const el = select.value.$el
	const dialog = el.closest('[role="dialog"]')
	const menuId = el.querySelector('.vs__search').getAttribute('aria-owns')
	const menu = document.querySelector(`#${CSS.escape(menuId)}.vs__dropdown-menu`)
	if (dialog && menu && menu.parentElement !== dialog) {
		dialog.appendChild(menu)
	}
})

</script>

<template>
	<NcSelect
			ref="select"
			v-bind="$attrs"
			v-on="$listeners"
			@open="handleOpen"
			:value="selectedOption"
			:placeholder="placeholder"
			:required="required"
			:labelOutside="true"
			:searchable="false"
			class="ps-nc-select-internal"
			:clearable="false">
		<template #option="option">
			<span aria-hidden="true"><NcEllipsisedOption :name="String(option.label)"/></span>
			<span class="hidden-visually">{{ option.label }}</span>
		</template>
		<template #selected-option="option">
			<span aria-hidden="true"><NcEllipsisedOption :name="String(option.label)"/></span>
		</template>
		<template #search="{attributes, events}">
			<input
					class="vs__search"
					dir="auto"
					:required="required && !selectedOption"
					v-bind="attributes"
					v-on="withoutFocus(events)"
					:aria-describedby="valueId">
			<span :id="valueId" class="hidden-visually">{{ selectedOption ? selectedOption.label : '' }}</span>
		</template>
	</NcSelect>
</template>

<style scoped>
.ps-nc-select-internal.v-select,
.ps-nc-select-internal.v-select >>> .vs__dropdown-toggle {
	height: 36px;
	min-height: 36px;
}

.ps-nc-select-internal.v-select >>> .vs__selected-options,
.ps-nc-select-internal.v-select >>> .vs__selected {
	height: 32px;
	min-height: 32px;
	padding-bottom: 0;
}

.ps-nc-select-internal.v-select >>> .vs__search {
	height: 32px !important;
	margin: 0;
}

.ps-nc-select-internal.v-select >>> .vs__selected {
	margin-top: 0;
}

.ps-nc-select-internal.v-select >>> .vs__search {
	pointer-events: none;
}
</style>