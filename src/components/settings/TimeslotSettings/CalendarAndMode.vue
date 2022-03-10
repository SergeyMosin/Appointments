<template>
  <SlideBar :title="t('appointments','Calendars and Schedule')"
            :subtitle="t('appointments','Manage appointments and calendar settings')" @close="close">
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
        <template v-if="calInfo.tsMode==='0'">
          <div v-show="realCalIDs.substr(0,2)!=='-1'" style="margin: 0 0 2em -.5em">
            <ApptIconButton
                @click="gotoEvt('gotoAddAppt')"
                :text="t('appointments','Add Appointment Slots')"
                icon="icon-add"/>
            <ApptIconButton
                @click="gotoEvt('gotoDelAppt')"
                :text="t('appointments','Remove Old Appointments')"
                icon="icon-delete"/>
          </div>
          <div class="srgdev-appt-info-lcont">
            <label
                class="tsb-label"
                for="appt_tsb-main-cal-id">
              {{ t('appointments', 'Main calendar') }}:</label><a
              style="right: 9%"
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','maincal')"></a>
          </div>
          <select
              v-model="calInfo.mainCalId"
              class="tsb-input"
              id="appt_tsb-main-cal-id">
            <option value="-1">{{ t('appointments', 'Calendar Required') }}</option>
            <option v-for="cal in cals" :value="cal.id">{{ cal.name }}</option>
          </select>
          <div class="srgdev-appt-info-lcont">
            <label
                class="tsb-label"
                for="appt_tsb-dest-cal-id">
              {{ t('appointments', 'Calendar for booked appointments') }}:</label><a
              style="right: 9%"
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','destcal')"></a>
          </div>
          <select
              v-model="calInfo.destCalId"
              class="tsb-input"
              id="appt_tsb-dest-cal-id">
            <option value="-1">{{ t('appointments', 'Use Main calendar') }}</option>
            <option v-for="cal in cals" :value="cal.id">{{ cal.name }}</option>
          </select>
        </template>
        <template v-else-if="calInfo.tsMode==='1'">
          <div class="srgdev-appt-info-lcont">
            <label
                class="tsb-label"
                for="appt_tsb-srcm2-cal-id">
              {{ t('appointments', 'Source Calendar (Free Slots)') }}:</label><a
              style="right: 9%"
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','sourcecal_nr')"></a>
          </div>
          <select
              v-model="calInfo.nrSrcCalId"
              class="tsb-input"
              id="appt_tsb-srcm2-cal-id">
            <option value="-1">{{ t('appointments', 'Calendar Required') }}</option>
            <option v-for="cal in cals" :value="cal.id">{{ cal.name }}</option>
          </select>
          <div class="srgdev-appt-info-lcont">
            <label
                class="tsb-label"
                for="appt_tsb-destm2-cal-id">
              {{ t('appointments', 'Destination Calendar (Booked)') }}:</label><a
              style="right: 9%"
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','destcal_nr')"></a>
          </div>
          <select
              v-model="calInfo.nrDstCalId"
              class="tsb-input"
              id="appt_tsb-destm2-cal-id">
            <option value="-1">{{ t('appointments', 'Calendar Required') }}</option>
            <option v-for="cal in cals" :value="cal.id">{{ cal.name }}</option>
          </select>
        </template>
        <template v-else-if="calInfo.tsMode==='2'">
          <ApptIconButton
              :disabled="calInfo.tmmDstCalId==='-1'"
              @click="handleEditTemplate"
              :text="t('appointments','Edit Template')"
              icon="icon-edit"/>
          <div class="srgdev-appt-info-lcont">
            <label
                class="tsb-label"
                for="appt_tsb-dest-tmm-cal-id">
              {{ t('appointments', 'Destination Calendar (Booked)') }}:</label><a
              style="right: 9%"
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','destcal_tmm')"></a>
          </div>
          <select
              v-model="calInfo.tmmDstCalId"
              @change="removeFromTMM(calInfo.tmmDstCalId)"
              class="tsb-input"
              id="appt_tsb-dest-tmm-cal-id">
            <option value="-1">{{ t('appointments', 'Calendar Required') }}</option>
            <option v-for="cal in cals" v-if="cal.isReadOnly==='0'" :value="cal.id">{{ cal.name }}</option>
          </select>
          <ApptAccordion
              :title="t('appointments', 'Check for conflicts in…')"
              help="conflicts_tmm"
              help-style="right:9%"
              style="margin-bottom: 1em"
              :open="false">
            <template slot="content">
              <div v-for="cal in cals" v-show="cal.id!==calInfo.tmmDstCalId">
                <input
                    type="checkbox"
                    :value="cal"
                    v-model="calendarsAndSubscriptions"
                    @click="handleMoreCals"
                    :id="'srgdev-appt_tmm_more_'+cal.id"
                    class="checkbox"><label class="srgdev-appt-sb-label-inline"
                                            :class="{'label-subscription':cal.isSubscription==='1'}"
                                            :for="'srgdev-appt_tmm_more_'+cal.id">{{ cal.name }}</label>
              </div>
            </template>
          </ApptAccordion>
        </template>
        <ApptAccordion
            v-if="calInfo.tsMode!=='0'"
            :title="t('appointments', 'Booked Appointment Buffers')"
            help="buffers"
            help-style="right:9%"
            style="margin-bottom: 1em"
            :open="false">
          <template slot="content">
            <label for="appt_buffer-before" style="display:block; margin-top: .5em"
                   class="select-label">{{ t('appointments', 'Before:') }}</label>
            <vue-slider
                :min="0"
                :max="120"
                :interval="5"
                tooltip="always"
                tooltipPlacement="right"
                :tooltip-formatter="'{value} '+minute"
                id="appt_buffer-before"
                class="appt-buffer-slider"
                style="margin-bottom: .75em"
                v-model="calInfo.bufferBefore"></vue-slider>
            <label for="appt_buffer-after" class="select-label">{{ t('appointments', 'After:') }}</label>
            <vue-slider
                :min="0"
                :max="120"
                :interval="5"
                tooltip="always"
                tooltipPlacement="right"
                :tooltip-formatter="'{value} '+minute"
                id="appt_buffer-after"
                class="appt-buffer-slider"
                v-model="calInfo.bufferBefore"></vue-slider>
          </template>
        </ApptAccordion>
        <div style="margin-top: 2em" class="srgdev-appt-info-lcont">
          <label
              class="tsb-label"
              for="appt_tsb-ts-mode">
            {{ t('appointments', 'Time slot mode') }}:</label><a
            style="right: 9%"
            class="icon-info srgdev-appt-info-link"
            @click="$root.$emit('helpWanted','ts_mode')"></a>
        </div>
        <select
            v-model="calInfo.tsMode"
            class="tsb-input"
            @change="tsModeChanged"
            id="appt_tsb-ts-mode">
          <option value="2">{{ t('appointments', 'Weekly Template') }}</option>
          <option value="0">{{ t('appointments', 'Simple') }}</option>
          <option value="1">{{ t('appointments', 'External') }}</option>
        </select>
        <div>
          <input
              type="checkbox"
              v-model="calInfo.privatePage"
              id="srgdev-appt_tmm_private_page"
              class="checkbox">
          <label class="srgdev-appt-sb-label-inline"
                 for="srgdev-appt_tmm_private_page">
            {{ t('appointments', 'Private (visitors must be logged-in)') }}
          </label>
        </div>
        <div class="tsb-adv-settings-link">
          <span @click="gotoEvt('gotoAdvStn')"
                class="tsb-adv-settings-link_span">{{ t('appointments', 'Advanced Settings') }} &raquo;</span>
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
import SlideBar from "../../SlideBar.vue"
import ApptIconButton from "../../ApptIconButton";
import ApptAccordion from "../../ApptAccordion.vue";
import {showError, showWarning} from "@nextcloud/dialogs"
import {getTimezone} from "../../../utils";

import {
  ActionButton,
  Actions,
} from '@nextcloud/vue'

import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/default.css'

import axios from '@nextcloud/axios'
import ApptIconLabel from "../../ApptIconLabel";

export default {

  name: "CalendarAndMode",
  components: {
    ApptIconLabel,
    SlideBar,
    ApptIconButton,
    VueSlider,
    Actions,
    ActionButton,
    ApptAccordion
  },
  props: {
    curPageData: Object,
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

      // TRANSLATORS "Min" is short for Minute(s)
      minute: this.t('appointments', "Min"),

      calInfo: {
        mainCalId: "-1",
        destCalId: "-1",
        nrSrcCalId: "-1",
        nrDstCalId: "-1",
        tmmDstCalId: "-1",
        tmmMoreCals: [],
        tmmSubscriptions: [],
        bufferBefore: 0,
        bufferAfter: 0,
        privatePage: false,
        tsMode: "2",
      },
      realCalIDs: "-1-1",
      realTmmId: "-1",

      tzName: "",
      tzData: "",

      calendarsAndSubscriptions: [],

      cals: [],
      hasKey: false,
    };
  },

  methods: {

    async start() {
      this.isLoading = true
      try {
        const data = this.curPageData
        this.calInfo = await this.getState("get_" + data.stateAction, data.pageId)
        this.setRealIds()
      } catch (e) {
        this.isLoading = false
        console.log(e)
        showError(this.t('appointments', "Can not request data"))
        return
      }

      this.cals.splice(0, this.cals.length)
      try {
        const res = await axios.get('callist?mode=' + this.calInfo.tsMode)
        const cals = res.data.split(String.fromCharCode(31))
        for (let i = 0, cal, d, l = cals.length; i < l; i++) {
          cal = cals[i].split(String.fromCharCode(30))
          d = {
            name: cal[0],
            id: cal[2],
            isReadOnly: cal[3],
            isSubscription: cal[4] || '0'
          }
          this.cals.push(d)
          if ((d.isSubscription === '0'
                  && this.calInfo.tmmMoreCals.indexOf(d.id) !== -1)
              || (d.isSubscription === '1'
                  && this.calInfo.tmmSubscriptions.indexOf(d.id) !== -1)) {
            this.calendarsAndSubscriptions.push(d)
          }
        }
      } catch (e) {
        this.isLoading = false
        console.log(e)
        showError(this.t('appointments', "Can not load calendars"))
        return
      }

      this.isLoading = false

      this.getState("get_k").then(k => {
        this.hasKey = k !== ""
      })
    },

    handleMoreCals(evt) {
      if (this.hasKey === false && this.calendarsAndSubscriptions.length > 1) {
        if (evt.currentTarget.checked === true) {
          this.$emit('showCModal', this.t('appointments', "More than 2 additional calendars."))
          evt.preventDefault()
          return false
        }
      }
    },

    async handleEditTemplate() {
      if (this.realTmmId !== this.calInfo.tmmDstCalId) {
        showWarning(this.t('appointments', "Please apply calendar changes first"))
        return
      }

      this.isLoading = true
      try {
        const d = await getTimezone(this.getState, this.calInfo.tmmDstCalId)
        this.tzName = d.name
        this.tzData = d.data

        // sync timezones
        const ttzRes = await this.getState("get_t_tz", this.curPageData.pageId)
        if (ttzRes.tzName !== this.tzName) {
          this.calInfo.tzData = this.tzData
          this.calInfo.tzName = this.tzName
          // noinspection ES6MissingAwait
          this.setState(
              "set_" + this.curPageData.stateAction,
              this.calInfo,
              this.curPageData.pageId, {
                noToast: true,
                noFormData: true
              })
        }

        this.isLoading = false
      } catch (e) {
        this.isLoading = false
        console.error("Can't get timezone")
        console.log(e)
        showError(this.t('appointments', "Can't load time zones"))
        return
      }

      this.$emit('editTemplate', {
        pageId: this.curPageData.pageId,
        tzName: this.tzName,
        tzData: this.tzData
      })
      this.close()
    },

    tsModeChanged() {
      this.apply(true)
      this.$emit("showModal", [
        this.t('appointments', 'Warning'),
        this.t('appointments', 'Time slot mode has changed. Public page is going offline …'),
        this.start])
    },

    apply(tsModeChanged) {

      // No need to check when ts mode is being changed
      if (tsModeChanged !== true) {
        if (this.calInfo.tsMode === "0") {
          if (this.calInfo.mainCalId === '-1') {
            this.$emit("showModal", [
              this.t('appointments', 'Error'),
              this.t('appointments', 'Main calendar is required')])
            return
          } else {
            if (this.calInfo.mainCalId === this.calInfo.destCalId) {
              this.calInfo.destCalId = "-1"
            }
          }
        } else if (this.calInfo.tsMode === "1") {
          if (this.calInfo.nrSrcCalId === '-1') {
            this.$emit("showModal", [
              this.t('appointments', 'Error'),
              this.t('appointments', 'Source calendar is required')])
            return
          } else if (this.calInfo.nrDstCalId === '-1') {
            this.$emit("showModal", [
              this.t('appointments', 'Error'),
              this.t('appointments', 'Destination calendar is required')])
            return
          } else if (this.calInfo.nrSrcCalId === this.calInfo.nrDstCalId) {
            this.$emit("showModal", [
              this.t('appointments', 'Error'),
              this.t('appointments', 'Source and Destination calendars must be different')])
            return
          }
        } else if (this.calInfo.tsMode === '2') {
          this.calInfo.tmmMoreCals.splice(0, this.calInfo.tmmMoreCals.length)
          this.calInfo.tmmSubscriptions.splice(0, this.calInfo.tmmSubscriptions.length)
          this.calendarsAndSubscriptions.forEach(cs => {
            if (cs['isSubscription'] === '0') {
              this.calInfo.tmmMoreCals.push(cs['id'])
            } else {
              this.calInfo.tmmSubscriptions.push(cs['id'])
            }
          })
        }
      }

      this.isSending = true
      this.setState(
          "set_" + this.curPageData.stateAction,
          this.calInfo,
          this.curPageData.pageId).then(() => {
        if (tsModeChanged) {
          // reload pages when tsModeChanged
          this.$emit("reloadPages")
        }
        this.setRealIds()
        this.isSending = false
      })
    },

    setRealIds() {
      if (this.calInfo.tsMode === '0') {
        this.realCalIDs = this.calInfo.mainCalId.toString() + this.calInfo.destCalId.toString()
      } else if (this.calInfo.tsMode === '2') {
        this.realTmmId = this.calInfo.tmmDstCalId
      }
    },

    gotoEvt(evt) {
      if (evt !== 'gotoAdvStn' &&
          this.realCalIDs !== this.calInfo.mainCalId.toString() + this.calInfo.destCalId.toString()
      ) {
        showWarning(this.t('appointments', "Please apply calendar changes first"))
      } else {
        this.$emit(evt, this.curPageData.pageId)
      }
    },

    removeFromTMM(calId) {
      this.calendarsAndSubscriptions = this.calendarsAndSubscriptions.filter(cs => {
        const isSub = cs['isSubscription'] === '1'
        return isSub || (!isSub && cs['id'] !== calId)
      })
    },

    close() {
      this.$emit('close')
    },
  }
}
</script>
<style scoped>
.tsb-label {
  display: block;
}

.tsb-input {
  margin-top: 0;
  display: block;
  min-width: 80%;
  margin-bottom: 1em;
  color: var(--color-text-lighter);
}

.tsb-adv-settings-link {
  margin: 1em 0 0
}

.tsb-adv-settings-link_span {
  font-size: 90%;
  cursor: pointer;
  color: var(--color-text-lighter);
}

.tsb-adv-settings-link_span:hover {
  text-decoration: underline;
  color: var(--color-main-text)
}

.label-subscription {
  opacity: .7;
}

.label-subscription:after {
  content: '↗';
  margin-left: .5em;
  vertical-align: top;
  font-size: 75%;
}

.appt-buffer-slider {
  margin: .25em 5.5em 1.5em 0;
  box-sizing: content-box;
}

</style>
