<template>
  <SlideBar :title="t('appointments','Advanced Settings')"
            :subtitle="t('appointments','These settings affect ALL pages')" icon="icon-appt-go-back" @close="close">
    <template slot="main-area">
      <div v-show="isLoading===true" class="sb_loading_cont">
        <span class="icon-loading sb_loading_icon_cont"></span>
        <span class="sb_loading_text">{{ t('appointments', 'Loading') }}</span>
      </div>
      <div
          v-show="isLoading===false"
          :class="{'sb_disable':isSending}"
          class="srgdev-appt-sb-main-cont"
          style="padding-left: 1%">
        <ApptIconLabel
            :text="t('appointments','Time Slot Settings')"
            icon="icon-appt-timeslot-settings"/>
        <div class="srgdev-appt-sb-indent_small" style="margin-bottom: 1.25em;">
          <label
              class="tsb-label"
              for="appt_tsb-appt-prep-time">
            {{ t('appointments', 'Minimum lead time') }}:</label>
          <select
              v-model="calInfo.prepTime"
              class="tsb-input"
              id="appt_tsb-appt-prep-time">
            <option value="0">{{ t('appointments', 'No lead time') }}</option>
            <option value="15">{{ t('appointments', '15 minutes') }}</option>
            <option value="30">{{ t('appointments', '30 minutes') }}</option>
            <option value="60">{{ t('appointments', '1 hour') }}</option>
            <option value="120">{{ t('appointments', '2 hours') }}</option>
            <option value="240">{{ t('appointments', '4 hours') }}</option>
            <option value="480">{{ t('appointments', '8 hours') }}</option>
            <option value="720">{{ t('appointments', '12 hours') }}</option>
            <option value="1440">{{ t('appointments', '1 day') }}</option>
            <option value="2880">{{ t('appointments', '2 days') }}</option>
            <option value="5760">{{ t('appointments', '4 days') }}</option>
          </select>
          <label
              class="tsb-label"
              for="appt_tsb-appt-reset">
            {{ t('appointments', 'When Attendee Cancels') }}:</label>
          <select
              v-model="calInfo.whenCanceled"
              class="tsb-input"
              id="appt_tsb-appt-reset">
            <option value="mark">{{ t('appointments', 'Mark the appointment as canceled') }}</option>
            <option value="reset">{{ t('appointments', 'Reset (make the timeslot available)') }}</option>
          </select>
          <div style="margin-top: 1.2em">
            <input
                v-model="calInfo.allDayBlock"
                type="checkbox"
                id="appt_tsb-allday-block"
                class="checkbox"><label style="margin-left: -3px;" class="srgdev-appt-sb-label-inline"
                                        for="appt_tsb-allday-block">{{ t('appointments', 'Include all day events in conflict check') }}</label>
          </div>
          <div class="srgdev-appt-info-lcont">
            <label class="tsb-txt-label"
                   for="appt_tsb-title-template">
              {{ t('appointments', 'Title Template') }}:</label><a
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','tmm_title_template')"></a>
          </div>
          <input
              class="tsb-input"
              style="margin: 0"
              v-model="calInfo.titleTemplate"
              id="appt_tsb-title-template"
              type="text"
              placeholder="%N">
        </div>
        <ApptIconLabel
            class="toggler" :class="{'toggler--closed':sections[0]===0}"
            @click.native="toggleSection(0)"
            :text="t('appointments','Weekly Template Settings')"
            icon="icon-sched-mode-wt"/>
        <div v-show="sections[0]===1"
             class="srgdev-appt-sb-indent_small">
          <div class="srgdev-appt-info-lcont">
            <label
                class="tsb-label"
                for="appt_tsb-appt-sub-refresh">
              {{ t('appointments', 'Subscriptions Sync Interval') }}:</label><a
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','tmm_subs_sync')"></a>
          </div>
          <select
              v-model="calInfo.tmmSubscriptionsSync"
              class="tsb-input"
              id="appt_tsb-appt-sub-refresh">
            <option value="0">{{ t('appointments', 'Nextcloud Only Sync') }}</option>
            <option value="60">{{ t('appointments', '1 Hour') }}</option>
            <option value="120">{{ t('appointments', '2 Hours') }}</option>
            <option value="240">{{ t('appointments', '4 Hours') }}</option>
            <option value="480">{{ t('appointments', '8 Hours') }}</option>
            <option value="720">{{ t('appointments', '12 Hours') }}</option>
            <option value="1440">{{ t('appointments', '1 day') }}</option>
          </select>
        </div>
        <ApptIconLabel
            class="toggler" :class="{'toggler--closed':sections[0]===0}"
            @click.native="toggleSection(1)"
            :text="t('appointments','External Mode Settings')"
            icon="icon-sched-mode"/>
        <div v-show="sections[1]===1"
             class="srgdev-appt-sb-indent_small">
          <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont" style="margin-top: 1em"><input
              type="checkbox"
              v-model="calInfo.nrPushRec"
              id="appt_tsb-push-recur"
              class="checkbox"><label for="appt_tsb-push-recur">{{ t('appointments', 'Optimize recurrence') }}</label><a
              class="icon-info srgdev-appt-info-link"
              style="right: 9%"
              @click="$root.$emit('helpWanted','push_rec_nr')"></a>
          </div>
          <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont" style="margin-top: 1.25em"><input
              type="checkbox"
              v-model="calInfo.nrRequireCat"
              id="appt_tsb-require-cat"
              class="checkbox"><label
              for="appt_tsb-require-cat">{{ t('appointments', 'Require "Appointment" category') }}</label><a
              class="icon-info srgdev-appt-info-link"
              style="right: 9%"
              @click="$root.$emit('helpWanted','require_cat_nr')"></a>
          </div>
          <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont" style="margin-top: 1.25em"><input
              type="checkbox"
              v-model="calInfo.nrAutoFix"
              id="appt_tsb-nr-auto-fix"
              class="checkbox"><label
              for="appt_tsb-nr-auto-fix">{{ t('appointments', 'Auto-fix "Source" timeslots') }}</label><a
              class="icon-info srgdev-appt-info-link"
              style="right: 9%"
              @click="$root.$emit('helpWanted','auto_fix_nr')"></a>
          </div>
        </div>
        <ApptIconLabel
            class="toggler" :class="{'toggler--closed':sections[1]===0}"
            @click.native="toggleSection(2)"
            :text="t('appointments','Debugging')"
            icon="icon-category-monitoring"/>
        <div v-if="sections[2]===1"
             class="srgdev-appt-sb-indent">
          <Debugging/>
        </div>
        <button
            @click="apply"
            class="primary srgdev-appt-sb-genbtn"
            :class="{'appt-btn-loading':isSending}">{{ t('appointments', 'Apply') }}
        </button>
      </div>
    </template>
  </SlideBar>
</template>

<script>
import SlideBar from "../SlideBar.vue"
import ApptIconLabel from "../ApptIconLabel";
import {showError} from "@nextcloud/dialogs"
import Debugging from "./Debugging";

export default {
  name: "AdvancedSettings",
  components: {
    Debugging,
    ApptIconLabel,
    SlideBar
  },
  props: {
    title: '',
    subtitle: '',
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
      sections: [0, 0, 0],
      calInfo: {
        prepTime: "0",
        whenCanceled: "mark",
        allDayBlock: false,
        nrPushRec: true,
        nrRequireCat: false,
        nrAutoFix: false,
        tmmSubscriptionsSync: "0",
        titleTemplate: "",
      },
    }
  },
  methods: {
    async start() {
      this.isLoading = true
      try {
        this.calInfo = await this.getState("get_cls", "")
        this.isLoading = false
      } catch (e) {
        this.isLoading = false
        console.log(e)
        showError(this.t('appointments', "Can not request data"))
      }
    },
    toggleSection(s) {
      this.$set(this.sections, s, this.sections[s] ^ 1)
    },
    apply() {
      this.isSending = true
      this.setState('set_cls', this.calInfo).then(() => {
        this.isSending = false
      })
    },
    close() {
      this.$emit('close')
    }
  }
}
</script>

<style lang="scss" scoped>
.tsb-label {
  display: block;
}

.toggler {
  cursor: pointer;
}

.toggler--closed {
  margin-bottom: .625em;
}


.tsb-txt-label,
.tsb-label {
  display: block;
}

.tsb-txt-label {
  margin-top: 1em;
}

.tsb-input {
  margin-top: 0;
  display: block;
  min-width: 80%;
  margin-bottom: 1em;
  color: var(--color-text-lighter);
}
</style>
