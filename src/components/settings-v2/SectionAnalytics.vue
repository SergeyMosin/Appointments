<script setup>
import {useSettingsStore} from "../../stores/settings";
import {NcNoteCard} from "@nextcloud/vue"
import ComboCheckbox from "./ComboCheckbox.vue";
import ComboInput from "./ComboInput.vue";

const settingsStore = useSettingsStore()
const settings = settingsStore.settings
</script>

<template>
	<div class="ps-section-wrap">
		<ComboInput
				class="ps-vert-spacing"
				prop-name="anaGtmId"
				placeholder="GTM-XXXXXXX"
				:label="t('appointments', 'Google Tag Manager container ID')"
				:store="settingsStore">
			<template #help>
				{{ t('appointments', 'Loads Google Tag Manager on the public booking form and its finish page. If both a container ID and a Google Analytics 4 ID are set, only Tag Manager is loaded; configure GA4 inside the container.') }}
			</template>
		</ComboInput>
		<ComboInput
				class="ps-vert-spacing"
				prop-name="anaGa4Id"
				placeholder="G-XXXXXXXXXX"
				:label="t('appointments', 'Google Analytics 4 measurement ID')"
				:store="settingsStore">
			<template #help>
				{{ t('appointments', 'Used directly through gtag.js only when no Tag Manager container ID is set.') }}
			</template>
		</ComboInput>
		<ComboInput
				class="ps-vert-spacing"
				prop-name="anaClarityId"
				:label="t('appointments', 'Microsoft Clarity project ID')"
				:store="settingsStore"/>
		<ComboInput
				class="ps-vert-spacing"
				prop-name="anaLinkedInId"
				:label="t('appointments', 'LinkedIn Insight Tag partner ID')"
				:store="settingsStore"/>
		<ComboInput
				class="ps-vert-spacing"
				prop-name="anaLinkedInConvId"
				:disabled="!settings.anaLinkedInId"
				:label="t('appointments', 'LinkedIn conversion ID (optional)')"
				:store="settingsStore">
			<template #help>
				{{ t('appointments', 'When set, the booking_submitted event is also sent to LinkedIn as this conversion.') }}
			</template>
		</ComboInput>
		<ComboInput
				class="ps-vert-spacing"
				prop-name="anaConsentText"
				:placeholder="t('appointments', 'This page uses analytics cookies to measure visits and bookings. They are only set if you accept.')"
				:label="t('appointments', 'Consent bar text')"
				:store="settingsStore">
			<template #help>
				{{ t('appointments', 'Shown with Accept and Decline buttons. Nothing is loaded until the visitor accepts; the choice is remembered in the browser for this page. Leave empty for the default text.') }}
			</template>
		</ComboInput>
		<ComboInput
				class="ps-vert-spacing"
				prop-name="anaPrivacyUrl"
				placeholder="https://example.com/privacy"
				:label="t('appointments', 'Privacy policy URL (optional)')"
				:store="settingsStore"/>
		<ComboCheckbox
				class="ps-vert-spacing"
				prop-name="anaTestMode"
				:label="t('appointments', 'Test mode: load trackers without asking for consent')"
				:store="settingsStore">
			<template #help>
				{{ t('appointments', 'Skips the consent bar and loads the trackers right away so tags can be checked (Tag Manager preview, Clarity live view).') }}
			</template>
		</ComboCheckbox>
		<NcNoteCard
				v-if="settings.anaTestMode"
				type="warning"
				:heading="t('appointments', 'Test mode is on')">
			{{ t('appointments', 'Trackers load for every visitor without consent. Do not leave test mode on for a public page.') }}
		</NcNoteCard>
		<NcNoteCard type="info">
			{{ t('appointments', 'A booking_submitted event is sent from the "check your email" page shown right after the form is submitted, once per booking: a dataLayer event with Tag Manager, a gtag event with GA4, a Clarity custom event, and a LinkedIn conversion if a conversion ID is set. Embedded forms do not load analytics.') }}
		</NcNoteCard>
	</div>
</template>
