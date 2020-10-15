<template>
<div class="appt_icon_label_cont">
  <span :class="['aib_icon_wrap',icon]"></span><span class="aib_text_span">{{text}}</span>
</div>
</template>
<script>
export default {
  name: "ApptIconLabel",
  props: {
    text: {
      type: String,
      default: '',
      required: true
    },
    icon: {
      type: String,
      default: ''
    },
  }
}
</script>

<style scoped lang="scss">
.appt_icon_label_cont {
  margin: .25em 0;
  .aib_icon_wrap,
  .aib_text_span {
    display: inline-block;
    vertical-align: middle;
  }

  .aib_icon_wrap {
    width: 1.5em;
    height: 1.5em;
    opacity: .6;
    cursor: inherit;
  }
  .aib_text_span {
    margin-left: .75em;
    color: var(--color-text-light);
    height: 2em;
    line-height: 2em;
    cursor: inherit;
  }
}
</style>