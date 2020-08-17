<template>
<SlideBar :title="title" :subtitle="t('appointments','Multiple public pages available')" @close="close">
<template slot="main-area">
  <div class="srgdev-appt-sb-main-cont">
    <ApptIconLabel
        :text="t('appointments','Main page')"
        icon="icon-appt-public-page"/>
      <div
          @click="pageSelected('p0')"
          role="button"
          class="srgdev-sqr-btn">
        <span class="srgdev-sqr-btn_text">{{page0.label!==""?page0.label:t('appointments','Public Page')}}</span><span class="icon-confirm srgdev-sqr-btn_icon"></span>
      </div>
    <ApptIconLabel
        :text="t('appointments','Additional pages')"
        icon="icon-appt-public-pages"/>
      <div v-for="page in morePages"
           @click="pageSelected(page.pageId)"
           role="button"
           class="srgdev-sqr-btn">
        <span class="srgdev-sqr-btn_text">{{page.label}}</span><span class="icon-confirm srgdev-sqr-btn_icon"></span>
      </div>
  </div>
</template>
</SlideBar>
</template>

<script>
import SlideBar from "./SlideBar.vue"
import ApptIconLabel from "./ApptIconLabel";

export default {
  name: "PagePickerSlideBar",
  components: {
    ApptIconLabel,
    SlideBar,
  },
  props:{
    title:'',
    subtitle:'',
    page0:Object,
    morePages:Array
  },
  methods: {
    pageSelected(pageId){
      this.$emit('pageSelected',pageId)
    },
    close(){
      this.$emit('close')
    }
  }
}
</script>
