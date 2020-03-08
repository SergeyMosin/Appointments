<template>
    <SlideBar :title="t('appointments','Public Page Settings')" :subtitle="t('appointments','Control what your visitors see')" @close="close">
        <template slot="main-area">
            <div class="pps-main-cont">
                <label
                        class="pps-label"
                        for="srgdev-appt_pps-week-sel">
                    {{t('appointments','Show appointments for next')}}</label>
                <select
                        class="pps-input"
                        v-model="ppsInfo.nbrWeeks"
                        id="srgdev-appt_pps-week-sel">
                    <option value="1">{{t('appointments','One Week')}}</option>
                    <option value="2">{{t('appointments','Two Weeks')}}</option>
                    <option value="3">{{t('appointments','Three Weeks')}}</option>
                    <option value="4">{{t('appointments','Four Weeks')}}</option>
                    <option value="5">{{t('appointments','Five Weeks')}}</option>
                </select>
                <input
                        v-model="ppsInfo.showEmpty"
                        type="checkbox"
                        id="srgdev-appt_pps-show-empty"
                        class="checkbox">
                <label for="srgdev-appt_pps-show-empty">{{t('appointments','Show Empty Days')}}</label><br>
                <div class="pps-indent"
                        v-show="ppsInfo.showEmpty===true">
                    <input
                            v-model="ppsInfo.startFNED"
                            type="checkbox"
                            id="srgdev-appt_pps-start-mon"
                            class="checkbox"><label for="srgdev-appt_pps-start-mon">{{t('appointments','Start on current day instead of Monday')}}</label><br>
                    <input
                            v-model="ppsInfo.showWeekends"
                            type="checkbox"
                            id="srgdev-appt_pps-show-weekends"
                            class="checkbox"><label for="srgdev-appt_pps-show-weekends">{{t('appointments','Show Empty Weekends')}}</label><br>
                </div>
                <input
                        v-model="ppsInfo.time2Cols"
                        type="checkbox"
                        id="srgdev-appt_pps-time-cols"
                        class="checkbox"><label for="srgdev-appt_pps-time-cols">{{t('appointments','Show time in two columns')}}</label><br>
                <button
                        @click="applyPPS"
                        class="primary pps-genbtn">{{t('appointments','Apply')}}
                </button>

            </div>
        </template>
    </SlideBar>
</template>

<script>
    import SlideBar from "./SlideBar.vue"

    export default {
        name: "FormStnSlideBar",
        components: {
            SlideBar
        },
        props:{
            title:'',
            subtitle:'',
            ppsInfo:{type: Object}
        },
        methods: {
            applyPPS(){
                this.$emit('ppsApply',this.ppsInfo)
            },
            close(){
                this.$emit('close')
            }
        }
    }
</script>

<style scoped>
    .pps-main-cont{
        text-align: left;
        padding-left: 4%;
        min-width: 270px;
    }
    .pps-label{
        display: block;
    }
    .pps-input{
        display: block;
        min-width: 60%;
        margin-bottom: 1em;
    }
    .pps-indent{
        padding-left: 2em;
        margin-bottom: 1em;
    }
    .pps-genbtn{
        margin-top: 3em;
        /*width: 60%;*/
        padding-left: 3em;
        padding-right: 3em;
    }
</style>