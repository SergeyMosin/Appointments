<template>
  <div class="tao-cont">
    <h4 v-if="header!==''" class="tao-h4">{{ header }}</h4>
    <div class="pml-cont">
      <label class="slider-label">{{ t('appointments', 'Duration (hours:minutes):') }}</label>
      <span @click="addDuration" class="pml-p">+</span>
      <span @click="removeDuration" class="pml-m">−</span>
    </div>
    <vue-slider
        :min="10"
        :max="durMax"
        :order="false"
        :interval="5"
        :process="false"
        tooltip="always"
        tooltipPlacement="bottom"
        :tooltip-formatter="tooltipFormatter"
        id="appt_dur-select"
        class="appt-slider"
        v-model="durations"></vue-slider>
    <!--  </div>-->
    <label class="select-label" for="srgdev-tao-title">{{ t('appointments', 'Title:') }}</label>
    <input
        :placeholder="t('appointments','Optional')"
        style="margin-bottom: 2em"
        v-model="title"
        id="srgdev-tao-title"
        type="text">

    <button style="margin-right: 1em" class="primary" @click="addAppts">{{
        elm === null
            ? t('appointments', 'Add')
            : t('appointments', 'OK')
      }}
    </button>
    <button v-if="elm!==null" @click="deleteAppt" class="delete-btn">Delete</button>
    <button @click="$emit('close')">{{ t('appointments', 'Cancel') }}</button>

    <a class="icon-info srgdev-appt-info-link help-link" @click="showHelp"></a>
  </div>
</template>

<script>
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/default.css'
import gridMaker from "../grid";

export default {
  name: "TemplateApptOptions",
  components: {
    VueSlider,
  },
  props: {
    elm: {
      type: HTMLDivElement,
      default: null
    },
    cid: {
      type: Number,
      detail: -1
    },
    hasKey: {
      type: Boolean,
      default: false,
    }
  },
  created() {
    this.durMax = 480
  },
  // TODO: add header info
  computed: {
    header() {
      let str = ""
      if (window.Intl && typeof window.Intl === "object") {
        const lang = document.documentElement.hasAttribute('data-locale')
            ? [document.documentElement.getAttribute('data-locale').replaceAll('_', '-'), document.documentElement.lang]
            : [document.documentElement.lang]
        if (this.elm === null) {
          // adding
          if (this.cid > -1) {
            const d = new Date()
            d.setDate(d.getDate() - d.getDay() + 1 + this.cid)
            str = new Intl.DateTimeFormat(lang,
                {weekday: "long"}).format(d)
          }
        } else if (this.elm.uTop !== undefined && this.elm.cID !== undefined) {
          // single appt. edit

          // Start at 8AM same as grid.js
          const SH = 8
          const d = new Date()
          d.setDate(d.getDate() - d.getDay() + 1 + this.elm.cID)
          console.log(this.elm, this.elm.uTop)

          const minTotal = SH * 60 + this.elm.uTop * 5
          const hours = (minTotal / 60) | 0
          d.setHours(hours, minTotal - hours * 60)
          str = new Intl.DateTimeFormat(lang,
              {weekday: "long", hour: "2-digit", minute: "2-digit"}).format(d)
        }
      }
      return str
    }
  },
  data: function () {
    return {
      // first one is needed for a work-around
      durations: [10, 30],
      title: ""
    }
  },
  mounted() {
    if (this.elm !== null) {
      this.durations = [10, ...this.elm.dur]
      this.title = this.elm.title
    }
  },
  methods: {
    addAppts() {
      if (this.elm === null) {
        this.$emit('close')
        this.$emit('tmplAddAppts', {
          dur: [...this.durations.slice(1)].sort((a, b) => a - b),
          title: this.title.trim()
        })
      } else {
        this.$emit('tmplUpdateAppt', {
          dur: [...this.durations.slice(1)].sort((a, b) => a - b),
          title: this.title.trim(),
          cIdx: this.elm.cIdx,
          cID: this.elm.cID
        })
        this.$emit('close')
      }
    },

    tooltipFormatter(v) {
      const h = (v / 60) | 0
      const m = v - h * 60
      return h + ":" + (m > 9 ? "" + m : "0" + m)
    },

    addDuration() {

      if (this.hasKey === false && this.durations.length > 2) {
        this.$emit('close')
        this.$emit('showCModal', this.t('appointments', 'More than two duration choices'))
        return
      }

      if (this.durations.length > 8) return

      const a = [...this.durations, this.durMax].sort((a, b) => a - b)
      // find largest space
      let s = 0
      for (let sp = 0, spn = 0, i = 0, l = a.length - 1; i < l; i++) {
        spn = a[i + 1] - a[i]
        if (spn > sp) {
          sp = spn
          s = i
        }
      }
      this.durations.push((((a[s] + ((a[s + 1] - a[s]) >> 1)) / 5) | 0) * 5)

    },
    removeDuration() {
      if (this.durations.length > 2) {
        this.durations.splice(-1, 1)
      }
    },

    deleteAppt() {
      // reuse event
      this.$emit('tmplUpdateAppt', {
        cIdx: this.elm.cIdx,
        cID: this.elm.cID,
        del: true
      })
      this.$emit('close')
    },

    showHelp() {
      this.$emit('close')
      this.$root.$emit('helpWanted', 'props_tmm')
    }

  },


}
</script>
<style>
.tao-cont .vue-slider-dot:first-child {
  display: none !important;
}
</style>
<style scoped>
.tao-h4 {
  font-size: 100%;
  padding-bottom: .25em;
  border-bottom: 1px solid var(--color-border);
  font-weight: bold;
  margin: -1em 0 1.5em;
}

.help-link {
  left: auto;
  right: 0;
  bottom: 12px;
  position: absolute;
}

.tao-cont {
  min-width: 22em;
  position: relative;
}

.pml-cont {
  position: relative;
}

.pml-m,
.pml-p {
  font-size: 125%;
  position: absolute;
  height: 1em;
  width: 1em;
  line-height: 1em;
  top: 50%;
  margin-top: -.5em;
  text-align: center;
  cursor: pointer;
  color: var(--color-text-light);
}

.pml-m:hover,
.pml-p:hover {
  color: var(--color-main-text);
  transform: scale(1.2);
}

.pml-p {
  right: 1.375em;
}

.pml-m {
  right: 0;
}

.slider-label,
.select-label {
  display: block;
  margin-top: 1em;
  margin-bottom: .25em;
}

.slider-label {
  cursor: default;
}

.appt-slider {
  margin-bottom: 3em;
  box-sizing: content-box;
}

#appt_dur-select {
  min-width: 32em;
}

input {
  display: block;
  width: 100%;
}

.delete-btn {
  margin-right: 1.5em;
}

.delete-btn:hover {
  color: var(--color-error);
}
</style>