<script setup>
import ApptAccordion from "../ApptAccordion.vue";
import IconCheckAll from "vue-material-design-icons/CheckAll.vue";
import IconCopy from "vue-material-design-icons/ContentCopy.vue";
import IconOpen from "vue-material-design-icons/OpenInNew.vue";
import {
	NcButton,
	NcLoadingIcon,
	NcModal
} from "@nextcloud/vue";
import SettingsService2 from "../../services/SettingsService2";
import {onMounted, ref} from "vue";
import {parsePageUrls} from "../../use/utils";

const settingsService = new SettingsService2()

const props = defineProps({
	pageData: Object
})
//
const emit = defineEmits(['update:pageData', 'show-warning'])

const pageURLs = ref([])
const showCopyCheck = ref(false)

onMounted(() => {
	if (props.pageData) {
		settingsService.getPageUrl(props.pageData.pageId).then(res => {
			if (res !== null) {
				if (res.data.message) {
					emit('show-warning', res.data)
				} else {
					const urls = parsePageUrls(res.data)
					if (urls !== null) {
						pageURLs.value = urls
					}
				}
			} else {
				// error, settingsService will show a toast
				// and we just close this modal
				handleUpdateShow(false)
			}
		})
	}
})

const handleUpdateShow = (evt) => {
	if (evt === false) {
		settingsService.cancel()
		emit('update:pageData', null)
	}
}

const handleVisitURL = () => {
	window.open(pageURLs.value[0], "_blank");
}

let copyCheckTimeout = -1
const handleCopyUrl = () => {

	const showCopyOkIcon = () => {
		showCopyCheck.value = true
		if (copyCheckTimeout !== -1) {
			clearTimeout(copyCheckTimeout)
		}
		copyCheckTimeout = setTimeout(() => {
			showCopyCheck.value = false
			clearTimeout(copyCheckTimeout)
		}, 500)
	}

	const text = pageURLs.value[0]
	if (navigator.clipboard) {
		navigator.clipboard.writeText(text).then(() => {
			showCopyOkIcon()
		}, (err) => {
			console.error('copy error:', err);
		});
	} else {
		// fallback
		let textArea = document.createElement("textarea");
		textArea.value = text;

		// Avoid scrolling to bottom
		textArea.style.top = "0";
		textArea.style.left = "0";
		textArea.style.position = "fixed";

		textArea.style.width = '2em';
		textArea.style.height = '2em';

		textArea.style.padding = 0;

		textArea.style.border = 'none';
		textArea.style.outline = 'none';
		textArea.style.boxShadow = 'none';

		textArea.style.background = 'transparent';

		document.body.appendChild(textArea);
		textArea.focus();
		textArea.select();

		try {
			document.execCommand('copy');
			showCopyOkIcon()
		} catch (err) {
			console.error('copy error:', err)
		}
		document.body.removeChild(textArea);
	}
}


</script>

<template>
	<NcModal
			class="srgdev-appt-modal-container"
			:show="props.pageData!==null"
			size="large"
			@update:show="handleUpdateShow">
		<div class="srgdev-appt-modal_content">
			<div class="srgdev-appt-modal-header">
				{{ t('appointments', 'Page URL') + (props.pageData && props.pageData.label ? (" - " + props.pageData.label) : "") }}
			</div>
			<div v-if="pageURLs.length===0">
				<NcLoadingIcon :size="64"/>
			</div>
			<div v-else>
				<div
						class="srgdev-appt-modal-lbl"
						style="user-select: text; cursor: text;">
					{{ pageURLs[0] }}
				</div>
				<ApptAccordion
						v-if="pageURLs[1]!==''"
						:title="t('appointments','Show iframe/embeddable')"
						:open="false"
						style="display: inline-block">
					<template #content>
						<div
								class="srgdev-appt-modal-lbl_dim"
								style="user-select: text; cursor: text;">
							{{ pageURLs[1] }}
						</div>
					</template>
				</ApptAccordion>
				<div>
					<NcButton
							class="srgdev-appt-modal-btn"
							:aria-label="t('appointments','Copy')"
							type="tertiary"
							@click="handleCopyUrl">
						<template #icon>
							<IconCopy v-if="showCopyCheck===false" :size="20"/>
							<IconCheckAll v-else :size="20"/>
						</template>
						{{ t('appointments', 'Copy') }}
					</NcButton>
					<NcButton
							class="srgdev-appt-modal-btn"
							:aria-label="t('appointments','Visit')"
							type="tertiary"
							@click="handleVisitURL">
						<template #icon>
							<IconOpen :size="20"/>
						</template>
						{{ t('appointments', 'Visit') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<style scoped>
.srgdev-appt-modal-container >>> .modal-container {
	width: auto;
}
</style>