<script setup>
import {ref, onMounted} from 'vue'

import IconCog from "vue-material-design-icons/Cog.vue";
import IconEarth from "vue-material-design-icons/Earth.vue"
import IconEarthOff from "vue-material-design-icons/EarthOff.vue"
import IconLink from "vue-material-design-icons/LinkVariant.vue";
import IconPencil from "vue-material-design-icons/Pencil.vue";
import IconPlus from "vue-material-design-icons/Plus.vue";
import IconWeb from "vue-material-design-icons/Web.vue";
import IconWebOff from "vue-material-design-icons/WebOff.vue";
import IconDelete from "vue-material-design-icons/Delete.vue";
import IconDirectory from "vue-material-design-icons/CardSearchOutline.vue";
import {
	NcActionButton,
	NcActionInput,
	NcAppNavigation,
	NcAppNavigationItem,
	NcAppNavigationNew,
	NcLoadingIcon
} from "@nextcloud/vue";
import {usePagesStore} from "../stores/pages";
import PageUrlModal from "./modals/PageUrlModal.vue";
import {MODAL} from "../use/constants";

const LOADING_PAGES = "p"
const LOADING_LABEL = "l"
const LOADING_DIR = "d"
const LOADING_SHARE = "s"

const emit = defineEmits(['show-page', 'show-settings', 'show-settings-modal'])

const showPageUrlData = ref(null)

const pagesStore = usePagesStore()

onMounted(() => {
	pagesStore.getAllPages(LOADING_PAGES)
})

const handleShowPageUrl = (pageId, label) => {
	showPageUrlData.value = {
		pageId: pageId,
		label: label
	}
}
const handleLabelSubmit = (pageId, evt) => {
	if (pageId && evt.target) {
		const input = evt.target.querySelector('input[type="text"]')
		if (input) {
			pagesStore.updatePageLabel(pageId, input.value, LOADING_LABEL)
		}
	}
}

const handleShowPage = (pageId) => {
	if (!pagesStore.loading) {
		emit('show-page', pageId)
	}
}

const handleDeletePage = (pageId) => {
	if (!confirm(t('appointments', 'Delete action cannot be undone. Proceed?'))) {
		return
	}
	pagesStore.deletePage(pageId, pageId).then(res => {
		if (res !== null) {
			emit('show-page', '-1')
		}
	})
}

const handleAddNewPage = () => {
	pagesStore.addNewPage(LOADING_PAGES).then(res => {
		if (res.status === 202) {
			emit('show-settings-modal', {
				type: MODAL.CONTRIBUTION,
				message: res.data.message
			})
		}
	})
}

const handleEnablePage = (pageId) => {
	pagesStore.toggleEnabled(pageId, pageId).then(res => {
		emit('show-page', pageId)
		if (res.status === 202) {
			emit('show-settings-modal', {
				type: res.data.type,
				message: res.data.message
			})
		}
	})
}

const handleActionsMenu = (pageId, evt) => {
	if (evt === true) {
		emit('show-page', pageId, false)
	}
}

</script>

<template>
	<NcAppNavigation>
		<template #list>
			<NcAppNavigationItem
					v-for="page in pagesStore.pages"
					:key="page.id"
					:name="page.label"
					:loading="pagesStore.loading===page.id"
					@update:menuOpen="(evt)=>{handleActionsMenu(page.id,evt)}"
					@click="handleShowPage(page.id)">
				<template #icon>
					<IconEarth v-if="pagesStore.loading!==page.id && page.enabled" :size="20"/>
					<IconEarthOff v-if="pagesStore.loading!==page.id && !page.enabled" :size="20"/>
				</template>
				<template #actions>
					<NcActionButton
							:disabled="!!pagesStore.loading"
							@click="handleEnablePage(page.id)"
							:close-after-click="true">
						<template #icon>
							<IconWebOff v-if="page.enabled" :size="20"/>
							<IconWeb v-else :size="20"/>
						</template>
						{{
							page.enabled
									? t('appointments', 'Stop sharing (disable)')
									: t('appointments', 'Publish online (enable)')
						}}
					</NcActionButton>
					<NcActionButton
							@click="handleShowPageUrl(page.id,page.label)"
							:close-after-click="true">
						<template #icon>
							<IconLink :size="20"/>
						</template>
						{{ t('appointments', 'Show URL/link') }}
					</NcActionButton>
					<NcActionInput
							:disabled="!!pagesStore.loading"
							class="app-nav-actions-input"
							@submit="(evt)=>{handleLabelSubmit(page.id, evt)}"
							:value="page.label">
						<template #icon>
							<NcLoadingIcon v-if="pagesStore.loading===LOADING_LABEL" :size="20"/>
							<IconPencil v-else :size="20"/>
						</template>
					</NcActionInput>
					<NcActionButton
							:disabled="!!pagesStore.loading"
							@click="emit('show-settings',page.id)"
							:close-after-click="true">
						<template #icon>
							<IconCog :size="20"/>
						</template>
						{{ t('appointments', 'Settings') }}
					</NcActionButton>
					<NcActionButton
							:disabled="!!pagesStore.loading"
							@click="handleDeletePage(page.id)"
							:close-after-click="true">
						<template #icon>
							<IconDelete :size="20"/>
						</template>
						{{ t('appointments', 'Delete Page') }}
					</NcActionButton>
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
					v-if="pagesStore.dirPage.showInNavigation"
					:name="pagesStore.dirPage.label"
					:loading="pagesStore.loading===LOADING_DIR"
					@update:menuOpen="(evt)=>{handleActionsMenu(pagesStore.dirPage.id,evt)}"
					@click="handleShowPage(pagesStore.dirPage.id)">
				<template #icon>
					<IconDirectory :size="24"/>
				</template>
				<template #actions>
					<NcActionButton
							@click="handleShowPageUrl(pagesStore.dirPage.id, pagesStore.dirPage.label)"
							:close-after-click="true">
						<template #icon>
							<IconLink :size="20"/>
						</template>
						{{ t('appointments', 'Show URL/link') }}
					</NcActionButton>
					<NcActionButton
							:disabled="!!pagesStore.loading"
							@click="emit('show-settings',pagesStore.dirPage.id)"
							:close-after-click="true">
						<template #icon>
							<IconCog :size="20"/>
						</template>
						{{ t('appointments', 'Settings') }}
					</NcActionButton>
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationNew
					:disabled="!!pagesStore.loading"
					class="app-nav-new-item"
					:text="t('appointments', 'Add New Page')"
					@click="handleAddNewPage">
				<template #icon>
					<NcLoadingIcon v-if="pagesStore.loading===LOADING_PAGES" :size="20"/>
					<IconPlus v-else :size="20"/>
				</template>
			</NcAppNavigationNew>
		</template>
		<PageUrlModal
				v-if="showPageUrlData!==null"
				:page-data.sync="showPageUrlData"
				@show-warning="(data)=>{showPageUrlData=null;emit('show-settings-modal',data)}"
		/>
	</NcAppNavigation>
</template>

<style scoped>

.app-nav-new-item {
	opacity: .8;
}

.app-nav-new-item:hover,
.app-nav-new-item:active {
	opacity: 1;
}

/* always show three dots menu */
>>> .app-navigation-entry__utils .action-item.app-navigation-entry__actions {
	display: inline-block;
}

.app-nav-actions-input {
	max-width: 16em;
}
</style>