<script setup>
import {useSettingsStore} from "../../stores/settings";
import ComboCheckbox from "./ComboCheckbox.vue";
import {
	NcNoteCard,
	NcActions,
	NcActionInput,
	NcUserBubble
} from "@nextcloud/vue"
import ComboInput from "./ComboInput.vue";
import LabelAccordion from "../LabelAccordion.vue";
import IconPlus from "vue-material-design-icons/Plus.vue";
import {ref} from 'vue'

const settingsStore = useSettingsStore()
const settings = settingsStore.settings

const badEmail = ref('')
const emailInputOpen = ref(false)

const addBadEmail = () => {
	if(settingsStore.loading['secEmailBlacklist']===true){
		return
	}
	const temp = [...settings.secEmailBlacklist]
	if (badEmail.value.match(/^.+@.+\.[a-zA-Z]{2,}$/) && !temp.includes(badEmail.value)) {
		temp.unshift(badEmail.value)
		settingsStore.setOne('secEmailBlacklist', temp)
		badEmail.value = ''
		emailInputOpen.value = false;
	}
}

const handleDeleteBadEmail = (email) => {
	if(settingsStore.loading['secEmailBlacklist']===true){
		return
	}
	const temp = settings.secEmailBlacklist.filter((elm) => elm !== email)
	if (temp.length !== settings.secEmailBlacklist.length) {
		settingsStore.setOne('secEmailBlacklist', temp)
	}
}

</script>

<template>
	<div class="ps-section-wrap">
		<ComboCheckbox
				prop-name="privatePage"
				:label="t('appointments', 'Private Page (visitors must be logged-in)')"
				:store="settingsStore"/>

		<ComboCheckbox
				class="ps-vert-spacing"
				prop-name="secHcapEnabled"
				:label="t('appointments', 'Enable hCaptcha')"
				:store="settingsStore">
		</ComboCheckbox>
		<div class="srgdev-appt-sb-indent" v-if="settings.secHcapEnabled">
			<ComboInput
					prop-name="secHcapSiteKey"
					:disabled="!settings.secHcapEnabled"
					:label="t('appointments', 'hCaptcha site key')"
					:store="settingsStore"/>
			<ComboInput
					prop-name="secHcapSecret"
					type="password"
					:disabled="!settings.secHcapEnabled"
					:label="t('appointments', 'hCaptcha secret')"
					:store="settingsStore"/>
			<div style="margin-bottom: 2em">
				{{ t('appointments', 'An hCaptcha account is required, more info') }}
				<a target="_blank" class="srgdev-appt-hs-link" href="https://www.hcaptcha.com/">https://www.hcaptcha.com/</a><br>
				<NcNoteCard
						:heading="t('appointments', 'Important')"
						type="info">
					{{ t('appointments', 'JavaScript code and/or cookies from hcaptcha.com will be placed into your booking form. You are responsible for ensuring compliance with privacy regulations in your jurisdiction.') }}
					<a class="srgdev-appt-hs-link" href="https://docs.hcaptcha.com/faq#should-i-update-my-privacy-policy-when-enabling-hcaptcha">https://docs.hcaptcha.com/faq#should-i-update-my-privacy-policy-when-enabling-hcaptcha</a>
				</NcNoteCard>
			</div>
		</div>

		<LabelAccordion
				class="ps-vert-spacing"
				:label="t('appointments','Blocked email addresses:')">
			<template #helpPopover>
				{{ t('appointments', 'Email addresses on this list cannot be used to book appointments. To block an entire domain, use a wildcard like this: *@bad-domain.tld') }}
			</template>
		</LabelAccordion>
		<ul class="bad-email-list"
				v-if="settings.secEmailBlacklist.length!==0">
			<NcUserBubble v-for="email in settings.secEmailBlacklist"
										:key="email"
										style="margin: .25em"
										:margin="5"
										:size="30"
										:display-name="email">
				<template #name>
					<a href="#"
						 title="Delete"
						 class="icon-close"
						 @click="()=>{handleDeleteBadEmail(email)}"/>
				</template>
			</NcUserBubble>
		</ul>
		<NcActions :menu-name="t('appointments','Add')"
							 :open="emailInputOpen"
							 :manualOpen="true"
							 @click="()=>{emailInputOpen = !emailInputOpen}"
							 @update:open="(isOpen)=>emailInputOpen=isOpen"
							 :disabled="settingsStore.loading['secEmailBlacklist'] === true">
			<template #icon>
				<IconPlus :size="20"/>
			</template>
			<NcActionInput
					@submit="addBadEmail"
					v-model="badEmail"
					:label-outside="false"
					:label="t('appointments','Enter email adddress')"/>
		</NcActions>
	</div>
</template>

<style scoped>
.bad-email-list {
	max-height: 16em;
	overflow: auto;
	margin-top: 1em;
	margin-bottom: 1em;
}

.icon-close {
	display: block;
	height: 100%;
}
</style>
