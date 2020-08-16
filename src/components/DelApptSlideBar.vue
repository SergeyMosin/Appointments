<template>
  <SlideBar :title="curPageData.label" :subtitle="t('appointments','Remove Old Appointments')" icon="icon-appt-go-back" @close="close">
    <template slot="main-area">
      <div v-show="isLoading===true" class="sb_loading_cont">
        <span class="icon-loading sb_loading_icon_cont"></span>
        <span class="sb_loading_text">{{t('appointments','Loading')}}</span>
      </div>
      <div v-show="isLoading===false" class="srgdev-appt-sb-main-cont">
        <div class="srgdev-appt-sb-narrow">
          <label for="appt_tsb-rem-slider">{{ t('appointments', 'Scheduled before') }}:</label>
          <vue-slider
              v-model="rsValue"
              :marks="rsMarks"
              :process="true"
              :included="true"
              :lazy="true"
              tooltip="none"
              @change="checkRsMin"
              id="appt_tsb-rem-slider"
              class="appt-slider"></vue-slider>
          <input type="radio"
                 value="empty"
                 v-model="remType"
                 id="appt_tsb-rem-empty"
                 class="radio"
                 checked="checked">
          <label for="appt_tsb-rem-empty">{{ t('appointments', 'Remove empty slots only') }}</label><br>
          <input type="radio"
                 value="both"
                 v-model="remType"
                 id="appt_tsb-rem-both"
                 class="radio">
          <label for="appt_tsb-rem-both">{{ t('appointments', 'Remove empty and booked') }}</label><br>
          <button
              @click="removeOld"
              class="primary srgdev-appt-sb-genbtn">{{ t('appointments', 'Start') }}
          </button>
        </div>
      </div>
    </template>
  </SlideBar>
</template>

<script>
import SlideBar from "./SlideBar.vue"
import axios from '@nextcloud/axios'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/default.css'


export default {
  name: "AddApptSlideBar",
  components: {
    SlideBar,
    VueSlider,
  },
  props: {
    curPageData: {},
    title: '',
    subtitle: '',
  },
  inject: [
    'getState'
  ],

  mounted: function () {
    this.isLoading=true
    this.start()
  },

  computed: {
    rsMarks: function () {
      const options = {month: 'short', day: '2-digit'};
      let d = new Date()
      d.setTime(Date.now() - 86400000)
      const y = d.toLocaleString(undefined, options)
      d.setTime(d.getTime() - 86400000 * 6)
      const w = d.toLocaleString(undefined, options)
      return {
        0: '-∞',
        58: w,
        100: y,
      }
    },
  },


  data: function () {
    return {
      isLoading:true,
      calInfo: undefined,
      rsValue: 58,
      remType: "empty",

      roaData: {
        str: "",
        pageId: undefined
      },
    }
  },

  methods: {

    async start() {
      this.isLoading=true
      try {
        const data = this.curPageData
        this.calInfo = await this.getState(data.action, data.key)
        this.isLoading=false
      } catch (e) {
        this.isLoading=false
        console.log(e)
        OC.Notification.showTemporary(this.t('appointments', "Can not request data"), {timeout: 4, type: 'error'})
      }
    },

    removeOld() {

      const d = {
        ri: {type: this.remType, before: this.rsValue === "100" ? 1 : 7},
        pageId: this.curPageData.pageId
      }
      let str
      try {
        str = JSON.stringify(d.ri)
      } catch (e) {
        console.log(e)
        OC.Notification.showTemporary(this.t('appointments', "Can not request data"), {timeout: 4, type: 'error'})
        return
      }

      this.$emit('openGM', 2)
      this.$emit('updateGM', {
        generalModalLoadingTxt: this.t('appointments', 'Gathering calendar information') + "..."
      })

      this.roaData.pageId = d.pageId
      this.roaData.str = ""

      axios.post('calgetweek', {
        t: str,
        p: d.pageId
      }).then(response => {
        if (response.status === 200) {
          const ua = response.data.split("|")

          let txt = ""
          if (ua[0] !== "0") {

            const dt = new Date()
            dt.setTime(ua[1] * 1000)

            let dts = dt.toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'long',
              day: 'numeric'
            })
            if (d.ri.type === "empty") {
              txt = this.t('appointments', 'Remove empty appointment slots created before {fullDate} ?', {
                fullDate: dts
              })
            } else {
              txt = this.t('appointments', 'Remove empty slots and booked appointments created before {fullDate} ?', {
                fullDate: dts
              })
            }
            this.roaData.str = str
          }

          let att = ""
          if (ua[0] !== "0" && d.ri.type === "both" && this.calInfo.destCalId !== undefined
              && this.calInfo.destCalId !== "-1") {
            att = " [ " + this.t('appointments', 'two calendars affected') + " ]"
          }

          this.$emit('updateGM', {
            generalModalTxt: [
              txt,
              this.t('appointments', 'Number of expired appointments/slots: ') + ua[0] + att
            ],
            generalModalLoadingTxt: "",
            generalModalActionCallback: this.removeOldAppointments
          })
        }
      }).catch(error => {
        this.$emit('closeGM')
        console.log(error)
        OCP.Toast.error(this.t('appointments', 'Can not get calendar data') + "\xa0\xa0\xa0\xa0")
      })
    },

    removeOldAppointments() {

      if (this.roaData.str === "" || this.roaData.pageId === undefined) {
        OC.Notification.showTemporary('Can not remove appointments: bad info', {timeout: 4, type: 'error'})
      }

      if (!confirm(this.t('appointments', 'This action can NOT be undone. Continue?'))) return;

      this.$emit('openGM', 2)
      this.$emit('updateGM', {
        generalModalLoadingTxt: this.t('appointments', 'Removing Appointment Slots') + "..."
      })

      const errTxt = this.t('appointments', 'Can not delete old appointments/slots') + "\xa0\xa0\xa0\xa0"

      const str = this.roaData.str.slice(0, -1) + ',"delete":true}';
      const pageId = this.roaData.pageId

      this.roaData.str = ""
      this.roaData.pageId = undefined

      axios.post('calgetweek', {
        t: str,
        p: pageId
      }).then(response => {
        if (response.status === 200) {
          const ua = response.data.split("|")
          if (ua[0] !== "0") {
            const dt = new Date()
            dt.setTime(ua[1] * 1000)

            let txt
            let dts = dt.toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'long',
              day: 'numeric'
            })

            if (str.indexOf("empty") > -1) {
              txt = this.t('appointments', 'All empty appointment slots created before {fullDate} are removed', {
                fullDate: dts
              })
            } else {
              txt = this.t('appointments', 'All empty slots and booked appointments created before {fullDate} are removed', {
                fullDate: dts
              })
            }

            this.$emit('updateGM', {
              generalModalTxt: ["", txt]
            })

          } else {
            OCP.Toast.error(errTxt)
          }
          this.$emit('updateGM', {
            generalModalLoadingTxt: ""
          })
        }
      }).catch(error => {
        this.$emit('closeGM')
        console.log(error)
        OCP.Toast.error(errTxt)
      })
    },

    checkRsMin() {
      if (+this.rsValue < 58) this.rsValue = "58"
    },
    close() {
      this.$emit('close')
    }
  }
}
</script>


<style scoped>
.srgdev-appt-sb-narrow {
  width: 85%;
  margin: 0 0 0 2%;
}

.appt-slider {
  margin-bottom: 3em;
}
</style>
