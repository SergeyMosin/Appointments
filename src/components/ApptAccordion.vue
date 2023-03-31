<template>
  <div>
    <div
        :class="[isOpen ? 'icon-triangle-s appt-accordion-bold-title' : 'icon-triangle-e', 'appt-accordion-title']"
        @click="toggle"><a v-if="help!==''"
                           class="icon-info srgdev-appt-info-link"
                           :style="helpStyle"
                           @click="$root.$emit('helpWanted',help)"></a>
      {{ title }}
    </div>
    <div
        class="appt-accordion-content"
        v-show="isOpen">
      <slot name="content"/>
    </div>
  </div>
</template>

<script>
export default {
  name: "ApptAccordion",
  props: {
    title: {
      type: String,
      default: '',
      required: true
    },
    open: {
      type: Boolean,
      default: false
    },
    help: {
      type: String,
      default: '',
    },
    helpStyle: {
      type: String,
      default: '',
    }
  },
  data: function () {
    return {
      isOpen: this.open
    }
  },
  methods: {
    toggle() {
      this.isOpen = !this.isOpen
    },
  }

}
</script>

<style scoped>
.appt-accordion-title {
  margin-bottom: .25em;
  cursor: pointer;
  background-position: left center;
  padding-left: 2.25em;
  background-size: 2em;
  margin-left: -.5em;
  position: relative;
}
.appt-accordion-bold-title{
  font-weight: bold;
}

.appt-accordion-content {
  padding-left: 1.75em;
}
</style>