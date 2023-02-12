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
              :min="5"
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
        // 1 (Mon) = default/fallback, or 0 (Sun) or 6 (Sat)
        firstDayOfWeek: window.firstDay === 0
            ? 0
            : (window.firstDay === 6 ? 6 : 1)
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
      const d = this.getStartOfWeek(new Date())
      // because of daylight savings
      d.setHours(1, 30, 0, 0)
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
            const endDate = new Date(date.getTime())
            endDate.setDate(endDate.getDate() + 6);
            if (window.Intl && typeof window.Intl === "object") {
              let f = new Intl.DateTimeFormat([],
                  {month: "short", day: "2-digit",})
              return f.format(date) + ' - ' + f.format(endDate)
            } else {
              return date.toLocaleDateString() + ' - ' + (endDate).toLocaleDateString()
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

      d.setHours(0, 0, 0, 0)

      // this.lang.formatLocale.firstDayOfWeek can be:
      //  0: Sunday
      //  1: Monday
      //  6: Saturday
      const fdw = this.lang.formatLocale.firstDayOfWeek

      //  fdw=0 : 0 1 2 3 4 5 6 | adjust: d.getDay()
      //  fdw=1 : 1 2 3 4 5 6 0 | adjust: (d.getDay() + 6) % 7
      //  fdw=6 : 6 0 1 2 3 4 5 | adjust: (d.getDay() + 1) % 7
      //  delta : 0 1 2 3 4 5 6

      const deltaDays = (d.getDay() + (7 - fdw)) % 7

      const nd = new Date(d.getTime())
      nd.setDate(nd.getDate() - deltaDays)
      return nd
    },
    compNotBefore(d) {
      d.setHours(1, 30, 0, 0)
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
