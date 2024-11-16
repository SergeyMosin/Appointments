<script setup>
import {useSettingsStore, readOnlyProps} from "../../stores/settings";
import ComboInput from "./ComboInput.vue";
import ComboCheckbox from "./ComboCheckbox.vue";
import ComboSelect from "./ComboSelect.vue";
import IconAdvanced from "vue-material-design-icons/TuneVerticalVariant.vue"
import IconOpen from "vue-material-design-icons/OpenInNew.vue"
import LabelAccordion from "../LabelAccordion.vue";
import SectionPageFormEditor from "./SectionPageFormEditor.vue";
import {
	NcActions,
	NcActionLink
} from "@nextcloud/vue"
import {ref} from "vue"

const settingsStore = useSettingsStore()
const settings = settingsStore.settings

const availableWeeksOptions = [
	{value: "1", label: t('appointments', 'One Week')},
	{value: "2", label: t('appointments', 'Two Weeks')},
	{value: "3", label: t('appointments', 'Three Weeks')},
	{value: "4", label: t('appointments', 'Four Weeks')},
	{value: "5", label: t('appointments', 'Five Weeks')},
	{value: "8", label: t('appointments', 'Eight Weeks')},
	{value: "12", label: t('appointments', 'Twelve Weeks')},
	{value: "18", label: t('appointments', 'Eighteen Weeks')},
	{value: "24", label: t('appointments', 'Twenty Four Weeks')},
	{value: "32", label: t('appointments', 'Thirty Two Weeks')},
	{value: "40", label: t('appointments', 'Forty Weeks')},
	{value: "48", label: t('appointments', 'Forty Eight Weeks')},
]

const prefillInputsOptions = [
	{value: 0, label: t('appointments', 'Disabled')},
	{value: 1, label: t('appointments', 'From Query String')},
	// TODO: user profile...
	// {value: 2, label: t('appointments', 'From User Profile (if logged-in)')},
	// {value: 3, label: t('appointments', 'Query String or User Profile')},
]
const prefilledTypeOptions = [
	{value: 0, label: t('appointments', 'Regular Inputs (default)')},
	{value: 1, label: t('appointments', 'Readonly / Plain Text')},
	{value: 2, label: t('appointments', 'Hide Prefilled Inputs')},
]

// this is a hacky way of doing this
// TODO: switch to getPageUrl
const previewLink = ref('#')
const handlePreviewClick = () => {
	const testToken = '3b719b44-8ec9-41e9-b161-00fb1515b1ed'
	previewLink.value = window.location.origin
			+ window.location.pathname.replace(/\/*$/, '') + '/pub/' + readOnlyProps.token + '/'
			+ (settings.skipEVS
					? 'cncf?d=2' + testToken
					: 'form?sts=0&d=' + testToken);
}

</script>

<template>
	<div class="ps-section-wrap">
		<ComboInput
				prop-name="formTitle"
				:label="t('appointments', 'Form Title')"
				:store="settingsStore"/>

		<ComboSelect
				prop-name="nbrWeeks"
				default-value="1"
				:label="t('appointments', 'Show appointments for next')"
				:store="settingsStore"
				:options="availableWeeksOptions"/>

		<ComboCheckbox
				prop-name="useNcTheme"
				:label="t('appointments', 'Auto Style')"
				:store="settingsStore"/>

		<ComboCheckbox
				prop-name="showEmpty"
				:label="t('appointments', 'Show Empty Days')"
				:store="settingsStore"/>
		<div v-if="settings.showEmpty===true" class="srgdev-appt-sb-indent">
			<ComboCheckbox
					prop-name="startFNED"
					:label="t('appointments', 'Start on current day instead of Monday')"
					:store="settingsStore"/>
			<ComboCheckbox
					prop-name="showWeekends"
					:label="t('appointments', 'Show Empty Weekends')"
					:store="settingsStore"/>
		</div>

		<ComboCheckbox
				prop-name="time2Cols"
				:disabled="settings.endTime===true"
				:indeterminate="settings.endTime===true"
				:label="t('appointments', 'Show time in two columns')"
				:store="settingsStore"/>
		<ComboCheckbox
				prop-name="endTime"
				:label="t('appointments', 'Show end time')"
				:store="settingsStore"/>

		<ComboCheckbox
				prop-name="hidePhone"
				:label="t('appointments', 'Hide phone number field')"
				:store="settingsStore"/>

		<LabelAccordion
				:label="t('appointments', 'Advanced Form Settings')"
				:accordion="true">
			<template #accordionIcon>
				<IconAdvanced :size="24"/>
			</template>

			<ComboInput
					type="textarea"
					prop-name="gdpr"
					:label="t('appointments', 'GDPR Compliance')"
					:store="settingsStore">
				<template #help>
					{{ t('appointments', 'Any text in the "GDPR Compliance" field will trigger the display of the "GDPR" checkbox. The checkbox can be hidden when the "GDPR text only (no checkbox)" option is selected. A checkbox with plain text (no HTML) or any HTML/links without a checkbox will work as is.') }}
					<br><br>
					{{ t('appointments', 'However, if you need to include both the checkbox and HTML or a link to your privacy policy you should separate it from the "label" element, and the "for" attribute of the "label" MUST be set to "appt_gdpr_id." For example:') }}
					<code class="srgdev-appt-hs-code">&lt;label for=&quot;appt_gdpr_id&quot;&gt;Some text &lt;/label&gt;
						&lt;a href=&quot;PRIVACY_POLCY_URL&quot;&gt;Privacy Policy&lt;/a&gt;
						&lt;label for=&quot;appt_gdpr_id&quot;&gt; some more text.&lt;/label&gt;
					</code>
				</template>
			</ComboInput>
			<ComboCheckbox
					style="margin-top: -1em; margin-bottom: .75em"
					prop-name="gdprNoChb"
					:label="t('appointments', 'GDPR text only (no checkbox)')"
					:store="settingsStore"/>

			<ComboInput
					prop-name="pageTitle"
					:label="t('appointments', 'Page Header Title')"
					:store="settingsStore"/>

			<ComboInput
					type="textarea"
					prop-name="formFinishText"
					:label="t('appointments', 'Additional Form Submitted Page Text')"
					:store="settingsStore">
				<template #help>
					{{ t('appointments', 'Allowed HTML tags: {tags}', {tags: "div, p, span, br"}) }}
				</template>
			</ComboInput>
			<NcActions type="tertiary"
								 :forceName="true"
								 style="margin-top: -1em; margin-bottom: 1em">
				<NcActionLink
						target="_blank"
						@click="handlePreviewClick"
						:href="previewLink">
					<template #icon>
						<IconOpen :size="20"/>
					</template>
					Preview Form Submitted Page
				</NcActionLink>
			</NcActions>

			<ComboSelect
					class="ps-wide-select"
					prop-name="prefillInputs"
					:default-value=0
					:label="t('appointments', 'Allow Prefilled Inputs')"
					:store="settingsStore"
					:options="prefillInputsOptions">
				<template #help>
					{{ t('appointments', 'You can pre-fill fields in the form by adding a URL query parameter, example:') }}
					<pre style="tab-size: 2"><code class="srgdev-appt-hs-code" style="white-space: pre;font-size: 90%">https://your.domain.com/page_url<span style="font-weight: bold">?name=John Smith&email=atendee@email.com</span></code></pre>
				</template>
			</ComboSelect>

			<ComboSelect
					class="ps-wide-select"
					prop-name="prefilledType"
					:default-value=0
					:label="t('appointments', 'Prefilled Inputs Appearance')"
					:store="settingsStore"
					:options="prefilledTypeOptions"/>

			<ComboInput
					type="textarea"
					prop-name="pageStyle"
					:label="t('appointments', 'Style Override')"
					placeholder="&lt;style&gt;...&lt;/style&gt;"
					:store="settingsStore">
				<template #help>
					{{ t('appointments', 'Insert custom "style" element to override default page style. Try something like this for example:') }}
					<pre style="tab-size: 2"><code class="srgdev-appt-hs-code" style="white-space: pre;font-size: 90%">&lt;style&gt;
	#header{
		background: transparent !important;
	}
	#content{
		background: linear-gradient(to bottom, #ff00cc, #333399) !important;
	}
	#body-public #content {
		min-height: 100%;
	}
	form{
		background:whitesmoke;box-shadow: 3px 3px 25px 0px rgba(0,0,0,0.75);
	}
	.srgdev-ncfp-form-header{
		border-bottom: 3px solid #961AB1;
	}
&lt;/style&gt;</code></pre>
				</template>
			</ComboInput>

			<SectionPageFormEditor
					:store="settingsStore"/>

			<ComboCheckbox
					prop-name="metaNoIndex"
					:label="t('appointments', 'Add {taginfo} tag', {taginfo: '/noindex/ meta'})"
					:store="settingsStore"/>
		</LabelAccordion>


	</div>
</template>

<style scoped>

</style>