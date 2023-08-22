<template>
  <SlideBar :title="t('appointments','Public Page Settings')"
            :subtitle="t('appointments','Control what your visitors see')" icon="icon-appt-go-back" @close="close">
    <template slot="main-area">
      <div v-show="isLoading===true" class="sb_loading_cont">
        <span class="icon-loading sb_loading_icon_cont"></span>
        <span class="sb_loading_text">{{ t('appointments', 'Loading') }}</span>
      </div>
      <div
          v-show="isLoading===false"
          :class="{'sb_disable':isSending}"
          class="pps-main-cont">
        <label class="pps-txt-label" for="srgdev-appt_pps-form-title">{{ t('appointments', 'Form Title') }}:</label>
        <input
            class="pps-text-input"
            v-model="ppsInfo.formTitle"
            id="srgdev-appt_pps-form-title"
            type="text"
            :placeholder="t('appointments','Book Your Appointment')">
        <label
            class="pps-label"
            for="srgdev-appt_pps-week-sel">
          {{ t('appointments', 'Show appointments for next') }}:</label>
        <select
            class="pps-input"
            v-model="ppsInfo.nbrWeeks"
            id="srgdev-appt_pps-week-sel">
          <option value="1">{{ t('appointments', 'One Week') }}</option>
          <option value="2">{{ t('appointments', 'Two Weeks') }}</option>
          <option value="3">{{ t('appointments', 'Three Weeks') }}</option>
          <option value="4">{{ t('appointments', 'Four Weeks') }}</option>
          <option value="5">{{ t('appointments', 'Five Weeks') }}</option>
          <option value="8">{{ t('appointments', 'Eight Weeks') }}</option>
          <option value="12">{{ t('appointments', 'Twelve Weeks')
            }}</option>
          <option value="18">{{ t('appointments', 'Eighteen Weeks')
            }}</option>
					<option value="24">{{ t('appointments', 'Twenty Four Weeks')
						}}</option>
					<option value="32">{{ t('appointments', 'Thirty Two Weeks')
						}}</option>
					<option value="40">{{ t('appointments', 'Forty Weeks')
						}}</option>
					<option value="48">{{ t('appointments', 'Forty Eight Weeks')
						}}</option>
        </select>
        <div class="srgdev-appt-sb-chb-cont"><input
            v-model="ppsInfo.showEmpty"
            type="checkbox"
            id="srgdev-appt_pps-show-empty"
            class="checkbox">
          <label for="srgdev-appt_pps-show-empty">{{ t('appointments', 'Show Empty Days') }}</label></div>
        <div class="pps-indent"
             v-show="ppsInfo.showEmpty===true">
          <div style="margin-top: .25em" class="srgdev-appt-sb-chb-cont"><input
              v-model="ppsInfo.startFNED"
              type="checkbox"
              id="srgdev-appt_pps-start-mon"
              class="checkbox"><label
              for="srgdev-appt_pps-start-mon">{{ t('appointments', 'Start on current day instead of Monday') }}</label>
          </div>
          <div class="srgdev-appt-sb-chb-cont"><input
              v-model="ppsInfo.showWeekends"
              type="checkbox"
              id="srgdev-appt_pps-show-weekends"
              class="checkbox"><label
              for="srgdev-appt_pps-show-weekends">{{ t('appointments', 'Show Empty Weekends') }}</label></div>
        </div>
        <div class="srgdev-appt-sb-chb-cont"><input
            v-model="ppsInfo.time2Cols"
            type="checkbox"
            :disabled="ppsInfo.endTime===true"
            id="srgdev-appt_pps-time-cols"
            class="checkbox"><label
            for="srgdev-appt_pps-time-cols">{{ t('appointments', 'Show time in two columns') }}</label></div>
        <div class="srgdev-appt-sb-chb-cont"><input
            v-model="ppsInfo.endTime"
            type="checkbox"
            @change="function() {
                          if(ppsInfo.endTime===true && ppsInfo.time2Cols===true){
                              ppsInfo.time2Cols=false
                          }
                        }"
            id="srgdev-appt_pps-end-time"
            class="checkbox"><label for="srgdev-appt_pps-end-time">{{ t('appointments', 'Show end time') }}</label>
        </div>
        <br>
        <ApptAccordion
            :title="t('appointments','Advanced Settings')"
            :open="false">
          <template slot="content">
            <div class="srgdev-appt-info-lcont">
              <label class="pps-txt-label" for="srgdev-appt_pps-gdpr">{{ t('appointments', 'GDPR Compliance') }}</label><a
                class="icon-info srgdev-appt-info-link"
                @click="$root.$emit('helpWanted','gdpr')"></a>
            </div>
            <input
                class="pps-text-input"
                style="margin-bottom: .125em"
                v-model="ppsInfo.gdpr"
                id="srgdev-appt_pps-gdpr"
                type="text"
                :placeholder="t('appointments','See Tutorial …')">
            <input
                v-model="ppsInfo.gdprNoChb"
                type="checkbox"
                id="srgdev-appt_pps-gdpr-chb"
                class="checkbox rem-disable-inner"><label
              for="srgdev-appt_pps-gdpr-chb"
              style="margin-left: -3px;"
              class="srgdev-appt-sb-label-inline">{{
              t('appointments', 'GDPR text only (no checkbox)')
            }}</label>
            <div style="padding-top: .25em"
                 class="srgdev-appt-sb-chb-cont"><input
                v-model="ppsInfo.hidePhone"
                type="checkbox"
                id="srgdev-appt_pps-hide-phone"
                class="checkbox"><label
                for="srgdev-appt_pps-hide-phone">{{ t('appointments', 'Hide phone number field') }}</label></div>
            <div class="srgdev-appt-sb-chb-cont"><input
                v-model="ppsInfo.showTZ"
                type="checkbox"
                id="srgdev-appt_pps-show-tz"
                class="checkbox"><label for="srgdev-appt_pps-show-tz">{{ t('appointments', 'Show timezone') }}</label>
            </div>
            <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont"><input
                v-model="ppsInfo.metaNoIndex"
                type="checkbox"
                id="srgdev-appt_pps-meta-noindex"
                class="checkbox"><label
                for="srgdev-appt_pps-meta-noindex">{{ t('appointments', 'Add {taginfo} tag', {taginfo: '/noindex/ meta'}) }}</label><a
                class="icon-info srgdev-appt-info-link"
                target="_blank"
                href="https://support.google.com/webmasters/answer/93710?hl=en"></a></div>
            <label class="pps-txt-label"
                   for="srgdev-appt_pps-page-title">{{ t('appointments', 'Page Header Title:') }}</label>
            <input
                v-model="ppsInfo.pageTitle"
                class="pps-text-input"
                id="srgdev-appt_pps-page-title"
                type="text">
            <label class="pps-txt-label"
                   for="srgdev-appt_pps-page-stitle">{{ t('appointments', 'Page Header Subtitle:') }}</label>
            <input
                v-model="ppsInfo.pageSubTitle"
                class="pps-text-input"
                id="srgdev-appt_pps-page-stitle"
                type="text">
            <div class="srgdev-appt-info-lcont">
              <label class="pps-txt-label"
                     for="srgdev-appt_pps-style">{{ t('appointments', 'Style Override:') }}</label><a
                class="icon-info srgdev-appt-info-link"
                @click="$root.$emit('helpWanted','style')"></a>
            </div>
            <textarea
                v-model="ppsInfo.pageStyle"
                class="srgdev-appt-sb-textarea"
                id="srgdev-appt_pps-style"
                style="width: 96%;"
                placeholder="&lt;style&gt;...&lt;/style&gt;">
                        </textarea>
            <div class="appt-stn-ext-link">
              <span @click="$emit('gotoToFid')" class="appt-stn-ext-link_span">Form Designer (beta) &raquo;</span>
            </div>
          </template>
        </ApptAccordion>
        <button
            @click="apply"
            class="primary pps-genbtn"
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
  name: "PublicPageSettings",
  components: {
    SlideBar,
    ApptAccordion
  },
  mounted: function () {
    this.isLoading = true
    this.start()
  },
  inject: ['getState', 'setState'],
  props: {
    title: '',
    subtitle: '',
  },
  data: function () {
    return {
      isLoading: true,
      isSending: false,
      ppsInfo: {
        formTitle: "",
        nbrWeeks: "1",
        showEmpty: true,
        startFNED: false,
        showWeekends: false,
        time2Cols: false,
        endTime: false,
        gdpr: "",
        gdprNoChb: false,
        whenCanceled: "mark",
        hidePhone: false,
        showTZ: false,
        pageTitle: "",
        pageSubTitle: "",
        metaNoIndex: false,
        pageStyle: ""
      }

    }
  },
  methods: {
    async start() {
      this.isLoading = true
      try {
        this.ppsInfo = await this.getState("get_pps", "")
        this.isLoading = false
      } catch (e) {
        this.isLoading = false
        console.log(e)
        showError(this.t('appointments', "Can not request data"))
      }
    },
    apply() {
      this.isSending = true
      this.setState('set_pps', this.ppsInfo).then(() => {
        this.isSending = false
      })
    },
    close() {
      this.$emit('close')
    }
  }
}
</script>

<style scoped>
.pps-main-cont {
  text-align: left;
  padding-left: 4%;
  min-width: 270px;
}

.pps-txt-label,
.pps-label {
  display: block;
}

.pps-txt-label {
  margin-top: 1em;
}

.pps-input {
  margin-top: 0;
  display: block;
  min-width: 60%;
  margin-bottom: 1em;
}

.pps-text-input {
  display: block;
  margin: 0 0 1em 0;
  width: 96%;
}

.pps-indent {
  padding-left: 2em;
  margin-bottom: 1em;
}

.pps-genbtn {
  margin-top: 3em;
  /*width: 60%;*/
  padding-left: 3em;
  padding-right: 3em;
}

.srgdev-appt-info-link {
  right: 4%;
}
</style>
