<template>
  <SlideBar :title="curPageData.label" :subtitle="t('appointments','Add Appointment Slots')" icon="icon-appt-go-back"
            @close="close">
    <template slot="main-area">
      <div v-show="isLoading===true" class="sb_loading_cont">
        <span class="icon-loading sb_loading_icon_cont"></span>
        <span class="sb_loading_text">{{ t('appointments', 'Loading') }}</span>
      </div>
      <div v-show="isLoading===false" class="srgdev-appt-sb-main-cont">
        <div class="srgdev-appt-sb-narrow">
          <label class="datepicker-label">{{ t('appointments', 'Select Dates:') }}</label>
          <DatePicker
              style="width: 100%"
              :editable="false"
              :disabled-date="compNotBefore"
              :appendToBody="false"
              :popup-style="datePickerPopupStyle"
              :placeholder="t('appointments','Select Dates')"
              v-model="apptWeek"
              :lang="lang"
              @input="setToStartOfWeek"
              :formatter="weekFormat"
              type="week"></DatePicker>
          <div class="srgdev-appt-info-lcont srgdev-appt-tz-cont">
            {{ t('appointments', 'Time zone:') + ' ' + tzName }}
          </div>
          <label for="appt_dur-select" class="select-label">{{ t('appointments', 'Appointment Duration:') }}</label>
          <vue-slider
              :min="10"
              :max="120"
              :interval="5"
              tooltip="always"
              tooltipPlacement="bottom"
              :tooltip-formatter="'{value} Min'"
              id="appt_dur-select"
              class="appt-slider"
              v-model="apptDur"></vue-slider>
          <button
              @click="goApptGen"
              :disabled="apptWeek===null"
              style="margin-top: 5em"
              class="primary srgdev-appt-sb-genbtn">{{ t('appointments', 'Start') }}
          </button>
        </div>
      </div>
    </template>
  </SlideBar>
</template>

<script>
import SlideBar from "../../SlideBar.vue"
import {getTimezone} from "../../../utils";

import DatePicker from 'vue2-datepicker'
import '../../../../css/datepicker.css';

import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/default.css'
import {showError} from "@nextcloud/dialogs"


export default {
  name: "SimpleAddAppointments",
  components: {
    SlideBar,
    VueSlider,
    DatePicker
  },
  props: {
    curPageData: {},
    isGridReady: {
      type: Boolean,
      default: false
    },
    title: '',
    subtitle: '',
  },
  inject: [
    'getState'
  ],
  mounted: function () {
    this.isLoading = true
    this.start()
  },
  computed: {
    lang: function () {
      let days = undefined
      let months = undefined
      const formatLocale = {
        firstDayOfWeek: window.firstDay || 0
      }
      if (window.Intl && typeof window.Intl === "object") {
        days = []
        let d = new Date(1970, 1, 1)
        let f = new Intl.DateTimeFormat([],
            {weekday: "short",})
        for (let i = 1; i < 8; i++) {
          d.setDate(i)
          days[i - 1] = f.format(d)
        }
        f = new Intl.DateTimeFormat([],
            {month: "short",})
        d.setDate(1)
        months = []
        for (let i = 0; i < 12; i++) {
          d.setMonth(i)
          months[i] = f.format(d)
        }
        formatLocale.monthsShort = months
      }
      return {days: days, formatLocale: formatLocale}
    },
    notBeforeDate() {
      let d = new Date()
      d.setHours(0)
      d.setMinutes(0)
      d.setTime(this.getStartOfWeek(d).getTime() - 90000000)
      return d
    }
  },

  data: function () {
    return {

      isLoading: true,
      tzName: '',
      tzData: '',

      calInfo: {},

      apptWeek: null,

      apptDur: 30,

      datePickerPopupStyle: {
        top: "75%",
        left: "50%",
        transform: "translate(-50%,0)"
      },
      weekFormat: {
        // Date to String
        stringify: (date, fmt) => {

          if (date) {
            const ts = date.getTime() + 6 * 86400000;
            if (window.Intl && typeof window.Intl === "object") {
              let f = new Intl.DateTimeFormat([],
                  {month: "short", day: "2-digit",})
              return f.format(date) + ' - ' + f.format(new Date(ts))
            } else {
              return date.toLocaleDateString() + ' - ' + (new Date(ts)).toLocaleDateString()
            }
          } else return ''
        }
      },
    }
  },

  methods: {

    async start() {
      this.isLoading = true

      if (!this.isGridReady) {
        this.$emit('setupGrid')
      }

      try {
        const data = this.curPageData
        this.calInfo = await this.getState("get_" + data.stateAction, data.pageId)
      } catch (e) {
        console.log(e)
        this.isLoading = false
        showError(this.t('appointments', "Can not request data"))
        return
      }

      // TODO: get adn display calendar name

      this.tzName = "UTC"
      this.tzData = "UTC"

      try {
        const d = await getTimezone(this.getState, this.calInfo['mainCalId'])
        this.tzName = d.name
        this.tzData = d.data
        this.isLoading = false
      } catch (e) {
        this.isLoading = false
        console.error("Can't get time zone")
        console.log(e)
        showError(this.t('appointments', "Can't load time zones"))
      }
    },

    getTimeFormat() {
      let date = new Date(0);
      if (date.toLocaleTimeString().indexOf("PM") === -1) {
        return 'HH:mm'
      } else {
        return 'hh:mm A'
      }
    },
    setToStartOfWeek() {
      if (this.apptWeek !== null) {
        this.apptWeek = this.getStartOfWeek(this.apptWeek)
      }
    },
    getStartOfWeek(d) {

      let gd = d.getDay()
      if (this.lang.formatLocale.firstDayOfWeek === 1) {
        // Sunday (0) is last
        if (gd === 0) gd = 6
        else gd--
      } else {
        gd--
      }
      return new Date(d.getTime() - gd * 86400000)
    },
    compNotBefore(d) {
      return d < this.notBeforeDate
    },
    resetAppt() {
      this.apptWeek = null
      this.apptDur = 30
    },
    goApptGen() {
      this.close(true)
      let r = {
        tz: this.tzData,
        week: (this.apptWeek.getTime()),
        dur: this.apptDur,
        pageId: this.curPageData.pageId,
        calColor: this.calInfo['curCal_color'],
        calName: this.calInfo['curCal_name']
      }
      this.resetAppt()
      this.$emit("agDataReady", r)
    },

    /**
     * @param hard - hard close will close the slidebar instead of going back to the "parent"
     */
    close(hard) {
      this.$emit('close', hard)
    }
  }
}
</script>


<style scoped>
.srgdev-appt-sb-narrow {
  width: 85%;
  margin: 0 0 0 2%;
}

.datepicker-label,
.select-label {
  display: block;
  margin-top: 1em;
}

.datepicker-label {
  margin-top: 0;
}

.select-label {
  margin-bottom: .25em;
}

.appt-slider {
  margin-bottom: 3em;
  box-sizing: content-box;
}

.srgdev-appt-tz-cont {
  color: var(--color-text-lighter);
  font-size: 85%;
  line-height: 1.1;
}
</style>
