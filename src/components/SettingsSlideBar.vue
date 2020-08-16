<template>
  <SlideBar :title="t('appointments','Additional Settings')" :subtitle="t('appointments','Customize and configure the app')" @close="close">
    <template slot="main-area">
      <AppNavigationItem
          :title="t('appointments','Customize Public Page')"
          @click="$emit('gotoPPS')"
          icon="icon-category-customization"/>
      <AppNavigationItem
          :title="t('appointments','Email Settings')"
          @click="$emit('gotoEML')"
          icon="icon-mail"/>
      <AppNavigationItem
          :title="t('appointments','Advanced Settings')"
          @click="$emit('gotoADV')"
          icon="icon-settings"/>
    </template>
  </SlideBar>
</template>

<script>
import SlideBar from "./SlideBar.vue"
import {
  AppNavigationItem,
} from '@nextcloud/vue'
export default {
  name: "SettingsSlideBar",
  components: {
    SlideBar,
    AppNavigationItem,
  },
  props:{
    title:'',
    subtitle:'',
  },
  methods: {
    close(){
      this.$emit('close')
    }
  }
}
</script>
