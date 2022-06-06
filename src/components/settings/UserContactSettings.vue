<template>
  <SlideBar :title="t('appointments','Your Contact Information')"
            :subtitle="t('appointments','Form header and event organizer settings')" @close="close">
    <template slot="main-area">
      <div v-show="isLoading===true" class="sb_loading_cont">
        <span class="icon-loading sb_loading_icon_cont"></span>
        <span class="sb_loading_text">{{ t('appointments', 'Loading') }}</span>
      </div>
      <div
          v-show="isLoading===false"
          :class="{'sb_disable':isSending}"
          class="srgdev-appt-sb-main-cont">
        <h2 v-show="curPageData.pageCount>1"
            class="srgdev-appt-sb-lbl-header">{{ curPageData.label }}</h2>
        <label
            class="srgdev-appt-sb-label"
            for="srgdev-appt_uci-org-name">
          {{ t('appointments', 'Name:') }}</label>
        <input
            :placeholder="ph_org"
            v-model="uciInfo.organization"
            class="srgdev-appt-sb-input-text"
            id="srgdev-appt_uci-org-name"
            type="text">
        <template v-if="curPageData.pageId==='p0'">
          <div class="srgdev-appt-info-lcont">
            <label class="srgdev-appt-sb-label"
                   for="srgdev-appt_uci-org-email">{{ t('appointments', 'Email:') }}</label><a style="right: 4%"
                                                                                               class="icon-info srgdev-appt-info-link"
                                                                                               @click="$root.$emit('helpWanted','emaildef')"><span>{{
              uciInfo.useDefaultEmail === 'yes' ? 'useDefaultEmail=yes' : ''
            }}</span></a>
          </div>
          <input
              v-model="uciInfo.email"
              class="srgdev-appt-sb-input-text"
              id="srgdev-appt_uci-org-email"
              type="email">
        </template>
        <label
            class="srgdev-appt-sb-label"
            for="srgdev-appt_uci-org-address">
          {{ t('appointments', 'Location:') }}</label>
        <textarea
            :placeholder="ph_addr"
            v-model="uciInfo.address"
            class="srgdev-appt-sb-textarea"
            id="srgdev-appt_uci-org-address"
            style="overflow: auto;resize: none"
        ></textarea>
        <template v-if="curPageData.pageId!=='p0'">
          <label
              class="srgdev-appt-sb-label"
              for="srgdev-appt_uci-form-title">{{ t('appointments', 'Form Title') }}:</label>
          <input
              class="srgdev-appt-sb-input-text"
              v-model="uciInfo.formTitle"
              id="srgdev-appt_uci-form-title"
              type="text"
              :placeholder="t('appointments','Book Your Appointment')">
        </template>
        <label
            class="srgdev-appt-sb-label"
            for="srgdev-appt_uci-org-phone">
          {{ t('appointments', 'Phone:') }}</label>
        <input
            v-model="uciInfo.phone"
            :placeholder="ph_phn"
            class="srgdev-appt-sb-input-text"
            id="srgdev-appt_uci-org-phone"
            style="max-width: 20em"
            type="tel">
        <div v-if="curPageData.pageId!=='p0'" style="color: gray; margin-bottom: 1em">
          {{ t('appointments', 'Email:') + " " + uciInfo.email }}
        </div>
        <ApptAccordion
            :title="t('appointments','Advanced Settings')"
            :open="false">
          <template slot="content">
            <div style="margin-top: 1em">
              <div class="srgdev-appt-info-lcont">
                <label class="srgdev-appt-sb-label"
                       for="srgdev-appt_uci-rdr-url">
                  {{ t('appointments', 'Redirect Confirmed URL:') }}
                </label>
                <a style="right: 4%" class="icon-info srgdev-appt-info-link"
                   @click="$root.$emit('helpWanted','confirmedUrl')"></a>
              </div>
              <input
                  v-model="uciInfo.confirmedRdrUrl"
                  class="srgdev-appt-sb-input-text"
                  style="margin-bottom: .2em"
                  id="srgdev-appt_uci-rdr-url">
              <input
                  v-model="uciInfo.confirmedRdrId"
                  type="checkbox"
                  id="srgdev-appt_uci-rdr-id"
                  class="checkbox">
              <label
                  for="srgdev-appt_uci-rdr-id"
                  style="margin-left: -3px;"
                  class="srgdev-appt-sb-label-inline">{{
                  t('appointments', 'Generate ID')
                }}</label><br>
              <input
                  v-model="uciInfo.confirmedRdrData"
                  type="checkbox"
                  id="srgdev-appt_uci-rdr-data"
                  class="checkbox">
              <label
                  for="srgdev-appt_uci-rdr-data"
                  style="margin-left: -3px;"
                  class="srgdev-appt-sb-label-inline">{{
                  t('appointments', 'Include Form Data')
                }}</label>
            </div>
          </template>
        </ApptAccordion>
        <button
            @click="apply"
            :disabled="curPageData.pageId==='p0' && (uciInfo.email==='' || uciInfo.organization==='' || uciInfo.address==='')"
            class="primary srgdev-appt-sb-genbtn"
            :class="{'appt-btn-loading':isSending}">{{ t('appointments', 'Apply') }}
        </button>
      </div>
    </template>
  </SlideBar>
</template>

<script>
import SlideBar from "../SlideBar.vue"
import ApptAccordion from "../ApptAccordion.vue";
import {showError} from "@nextcloud/dialogs"

export default {
  name: "UserContactSettings",
  components: {
    SlideBar,
    ApptAccordion
  },
  props: {
    title: '',
    subtitle: '',
    curPageData: Object,
  },
  mounted: function () {
    this.isLoading = true
    this.start()
  },
  inject: ['getState', 'setState'],
  data: function () {
    return {
      isLoading: true,
      isSending: false,
      uciInfo: {
        organization: "",
        email: "",
        address: "",
        phone: "",
        useDefaultEmail: "yes",

        confirmedRdrUrl: "",
        confirmedRdrId: false,
        confirmedRdrData: false,

        // Secondary pages only (same as ppsInfo.formTitle for the main)
        formTitle: "",
      },
      ph_org: "",
      ph_addr: "",
      ph_phn: "",
    }
  },
  methods: {

    async start() {
      this.isLoading = true
      const data = this.curPageData

      // if requesting not main page the _uciInfo will be used as placeholders because...
      // ... EMPTY SECONDARY uciInfo DEFAULTS TO MAIN uciInfo
      let _uciInfo
      try {
        _uciInfo = await this.getState("get_uci")
      } catch (e) {
        this.isLoading = false
        console.log(e)
        showError(this.t('appointments', "Can not request data"))
        return
      }
      if (data.pageId === 'p0') {
        this.uciInfo = _uciInfo
        this.isLoading = false
        return
      }


      // Secondary page...

      this.ph_org = _uciInfo.organization
      this.ph_addr = _uciInfo.address
      this.ph_phn = _uciInfo.phone
      try {
        this.uciInfo = await this.getState(
            "get_" + data.uciAction, data.pageId)
        this.$set(this.uciInfo,
            'email', _uciInfo.email)
        this.$set(this.uciInfo,
            'useDefaultEmail', _uciInfo.useDefaultEmail)
        this.isLoading = false
      } catch (e) {
        this.isLoading = false
        console.log(e)
        showError(this.t('appointments', "Can not request data"))
      }
    },

    apply() {
      this.isSending = true
      this.setState(
          'set_' + this.curPageData.uciAction,
          this.uciInfo,
          this.curPageData.pageId)
          .then(() => {
            this.isSending = false
          })
    },
    close() {
      this.$emit('close')
    }
  }
}
</script>
