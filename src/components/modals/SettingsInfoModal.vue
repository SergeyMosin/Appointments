<script setup>
import {
	NcModal,
	NcButton,
	NcPopover
} from "@nextcloud/vue";
import IconInfo from "vue-material-design-icons/InformationOutline.vue";
import {MODAL} from "../../use/constants";

const emit = defineEmits(['hide-settings-modal'])
const props = defineProps({
	data: {
		type: Object,
		required: true
	}
})

const handleUpdateShow = (evt) => {
	if (evt === false) {
		emit('hide-settings-modal')
	}
}

</script>

<template>
	<NcModal
			style="z-index: 10002"
			:enableSwipe="false"
			:outTransition="false"
			:show="data.type!==MODAL.NONE"
			@update:show="handleUpdateShow">
		<div class="srgdev-appt-modal-wrap">
			<template v-if="data.type===MODAL.CONTRIBUTION">
				<div class="srgdev-appt-modal-header">
					{{ t('appointments', "Contributor only feature") }}
				</div>
				{{ data.message }}
				<NcPopover class="popover-wrapper">
					<template #trigger>
						<NcButton class="popover-trigger">
							<template #icon>
								<IconInfo :size="20"/>
							</template>
							{{ t('appointments', "More Information") }}
						</NcButton>
					</template>
					<template>
						<div class="popover-content">
							Contributor features can be unlocked by obtaining a
							<strong>contributor key</strong> in any of the following ways:
							<ul class="popover-list">
								<li>Contribute any amount to this app development or sponsor a feature over at the
									<a class="srgdev-appt-hs-link" target="_blank" href="https://www.srgdev.com/gh-support/nextcloudapps">Funding Page</a>.
								</li>
								<li>Contribute code via a pull request on
									<a class="srgdev-appt-hs-link" target="_blank" href="https://github.com/SergeyMosin/Appointments">GitHub</a>
								</li>
								<li>If you are a member of the Nexcloud team on transifex.com please
									<a class="srgdev-appt-hs-link" target="_blank" href="https://www.srgdev.com/contact.html#cnt_ancr">contact me directly</a>.
								</li>
								<li>Contact me if none of the above methods work for you.</li>
							</ul>
						</div>
					</template>
				</NcPopover>
			</template>
			<template v-if="data.type===MODAL.INFO">
				<div class="srgdev-appt-modal-header">
					{{ t('appointments', "Information/Warning") }}
				</div>
				{{ data.message }}
			</template>
			<template v-if="data.type===MODAL.ERROR">
				<div class="srgdev-appt-modal-header srgdev-appt-modal-header_error">
					{{ t('appointments', "Error") }}
				</div>
				{{ data.message }}
			</template>
		</div>
	</NcModal>
</template>

<style scoped>
.popover-wrapper {
	margin-top: 2em;
}

.popover-trigger {
	margin: 0 auto;
}

.popover-content {
	margin: 1em;
}

.popover-list {
	margin-top: .5em;
	margin-left: .5em;
	list-style: disc inside;
}

</style>