<template>
<div class="tao-cont">
<!--  <h4 v-if="elm!==null" class="tao-h4">{{header}}</h4>-->
  <div class="pml-cont">
    <label class="slider-label">{{t('appointments','Duration (minutes):')}}</label>
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
        :tooltip-formatter="'{value}'"
        id="appt_dur-select"
        class="appt-slider"
        v-model="durations"></vue-slider>
<!--  </div>-->
  <label class="select-label" for="srgdev-tao-title">{{t('appointments','Title:')}}</label>
  <input
      :placeholder="t('appointments','Optional')"
      style="margin-bottom: 2em"
      v-model="title"
      id="srgdev-tao-title"
      type="text">

  <button style="margin-right: 1em" class="primary" @click="addAppts">{{
      elm===null
          ?t('appointments', 'Add')
          :t('appointments', 'OK')}}
  </button>
  <button v-if="elm!==null" @click="deleteAppt" class="delete-btn">Delete</button>
  <button @click="$emit('close')">{{ t('appointments', 'Cancel') }}</button>

  <a class="icon-info srgdev-appt-info-link help-link" @click="showHelp"></a>
</div>
</template>

<script>
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/default.css'

export default {
name: "TemplateApptOptions",
  components: {
    VueSlider,
  },
  props:{
    elm:{
      type:HTMLDivElement,
      default:null
    }
  },
  created(){
    this.durMax=150
  },
  // TODO: add header info
  // computed:{
  //   header(){
  //     return this.elm===null?null:this.elm.firstElementChild.textContent
  //   }
  // },
  data: function () {
    return {
      // first one is needed for a work-around
      durations:[10,30],
      title:""
    }
  },
  mounted(){
    if(this.elm!==null) {
      this.durations = [10, ...this.elm.dur]
      this.title = this.elm.title
    }
  },
  methods:{
    addAppts(){
      if(this.elm===null) {
        this.$emit('close')
        this.$emit('tmplAddAppts', {
          dur: [...this.durations.slice(1)].sort((a, b) => a - b),
          title: this.title.trim()
        })
      }else{
        this.$emit('tmplUpdateAppt', {
          dur: [...this.durations.slice(1)].sort((a, b) => a - b),
          title: this.title.trim(),
          cIdx:this.elm.cIdx,
          cID:this.elm.cID
        })
        this.$emit('close')
      }
    },

    addDuration(){
      // TODO: contributors message
      if(this.durations.length>3) return

      const a=[...this.durations,this.durMax].sort((a,b)=>a-b)
      // find largest space
      let s=0
      for(let sp=0,spn=0,i=0,l=a.length-1;i<l;i++){
        spn=a[i+1]-a[i]
        if(spn>sp){
          sp=spn
          s=i
        }
      }
      this.durations.push((((a[s]+((a[s+1]-a[s])>>1))/5)|0)*5)

    },
    removeDuration(){
      if(this.durations.length>2){
        this.durations.splice(-1,1)
      }
    },

    deleteAppt(){
      // reuse event
      this.$emit('tmplUpdateAppt', {
        cIdx:this.elm.cIdx,
        cID:this.elm.cID,
        del:true
      })
      this.$emit('close')
    },

    showHelp(){
      //TODO: help
      // $root.$emit('helpWanted',help)
    }

  },


}
</script>
<style>
.tao-cont .vue-slider-dot:first-child{
  display: none !important;
}
</style>
<style scoped>
.tao-h4{
  padding-bottom: .5em;
  border-bottom: 1px solid var(--color-border);
  font-weight: bold;
}
.help-link{
  left: auto;
  right: 0;
  bottom: 12px;
  position: absolute;
}
.tao-cont{
  min-width: 22em;
  position: relative;
}
.pml-cont{
  position: relative;
}
.pml-m,
.pml-p{
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
.pml-p:hover{
  color: var(--color-main-text);
  transform: scale(1.2);
}
.pml-p{
  right: 1.375em;
}
.pml-m{
  right: 0;
}
.slider-label,
.select-label{
  display: block;
  margin-top: 1em;
  margin-bottom: .25em;
}
.slider-label{
  cursor: default;
}
.appt-slider{
  margin-bottom: 3em;
}
input{
  display: block;
  width: 100%;
}
.delete-btn{
  margin-right: 1.5em;
}
.delete-btn:hover{
  color: var(--color-error);
}
</style>