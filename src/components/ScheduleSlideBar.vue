<template>
    <SlideBar :title="this.title" :subtitle="subtitle" @close="function() {
        resetAppt()
        $emit('back')
    }">
        <template slot="main-area">
            <div class="appt-gen-wrap">
                <label class="datepicker-label">{{t('appointments','Select Dates:')}}</label>
                <DatePicker
                        :editable="false"
                        :disabled-date="compNotBefore"
                        :appendToBody="false"
                        :popup-style="datePickerPopupStyle"
                        :placeholder="t('appointments','Select Dates')"
                        v-model="apptWeek"
                        :lang="lang"
                        @input="setToStartOfWeek"
                        :format="weekFormat"
                        type="week"></DatePicker>
                <label for="appt_dur-select" class="select-label">{{t('appointments','Appointment Duration:')}}</label>
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
                <div class="srgdev-appt-info-lcont">
                <label for="appt_tz-select" class="select-label">{{t('appointments','Time zone:')}}</label>
                    <a
                            class="icon-info srgdev-appt-info-link"
                            @click="$root.$emit('helpWanted','timezone')"><span>Please read</span></a>
                </div>
                <select v-model="apptTZ" id="appt_tz-select" class="appt-select">
                    <option value="L">Local (floating)</option>
                    <option value="C">{{tzName}}</option>
                </select>
                <button
                        @click="goApptGen"
                        :disabled="apptWeek===null"
                        class="primary appt-genbtn">{{t('appointments','Start')}}
                </button>
            </div>
        </template>
    </SlideBar>
</template>

<script>
    import SlideBar from "./SlideBar.vue"
    import DatePicker from 'vue2-datepicker'
    import '../../css/datepicker.css';

    import VueSlider from 'vue-slider-component'
    import 'vue-slider-component/theme/default.css'

    export default {
        name: "ScheduleSlideBar",
        components: {
            SlideBar,
            VueSlider,
            DatePicker},
        props:{
            title:'',
            subtitle:'',
            tzName:'',
            tzData:''
        },
        computed:{
            lang: function(){
                let days=undefined
                let months=undefined
                const formatLocale={
                    firstDayOfWeek:window.firstDay||0
                }
                if(window.Intl && typeof window.Intl === "object"){
                    days=[]
                    let d=new Date(1970,1,1)
                    let f = new Intl.DateTimeFormat([],
                        {weekday: "short",})
                    for(let i=1;i<8;i++){
                        d.setDate(i)
                        days[i-1]=f.format(d)
                    }
                    f = new Intl.DateTimeFormat([],
                        {month: "short",})
                    d.setDate(1)
                    months=[]
                    for(let i=0;i<12;i++){
                        d.setMonth(i)
                        months[i]=f.format(d)
                    }
                    formatLocale.monthsShort=months
                }
                return {days:days,formatLocale:formatLocale}
            },
            notBeforeDate(){
                let d=new Date()
                d.setHours(0)
                d.setMinutes(0)
                d.setTime(this.getStartOfWeek(d).getTime()-90000000)
                return d
            }
        },
        watch: {
            tzName(val){
                this.apptTZ = val === 'UTC' ? "L" : "C";
            }
        },

        data() {
            return {
                /** @type {Date} */
                apptWeek:null,

                apptDur:30,

                apptTZ:"C",

                datePickerPopupStyle:{
                    top:"75%",
                    left:"50%",
                    transform: "translate(-50%,0)"
                },
                weekFormat: {
                    // Date to String
                    stringify: (date,fmt) => {

                        if(date){
                            const ts=date.getTime() + 5 * 86400000;
                            if(window.Intl && typeof window.Intl === "object") {
                                let f = new Intl.DateTimeFormat([],
                                    {month: "short", day: "2-digit",})
                                return f.format(date) + ' - ' + f.format(new Date(ts))
                            }else{
                                return date.toLocaleDateString()+' - '+(new Date(ts)).toLocaleDateString()
                            }
                        }else return ''
                    }
                },
            }
        },
        methods: {
            getTimeFormat(){
                let date = new Date(0);
                if(date.toLocaleTimeString().indexOf("PM")===-1){
                    return 'HH:mm'
                }else{
                    return 'hh:mm A'
                }
            },
            setToStartOfWeek(){
                if(this.apptWeek!==null) {
                    this.apptWeek=this.getStartOfWeek(this.apptWeek)
                }
            },
            getStartOfWeek(d){

                let gd=d.getDay()
                if (this.lang.formatLocale.firstDayOfWeek === 1) {
                    // Sunday (0) is last
                    if(gd===0) gd=6
                    else gd--
                }else{
                    gd--
                }
                return new Date(d.getTime() - gd*86400000)
            },
            compNotBefore(d){
                return d<this.notBeforeDate
            },
            resetAppt(){
                this.apptWeek=null
                this.apptDur=30
            },
            goApptGen(){
                let r={
                    tz: this.apptTZ==="C"?this.tzData:"L",
                    week:(this.apptWeek.getTime()),
                    dur:this.apptDur,
                }
                this.resetAppt()
                this.$emit("close")
                this.$emit("agDataReady",r)
            }
        }
    }
</script>

<style scoped>
    .appt-gen-wrap{
        text-align: left;
        display: inline-block;
        margin-top: -20px;
    }
    .datepicker-label,
    .select-label{
        display: block;
        margin-top: 1em;
    }
    .datepicker-label{
        margin-top: 0;
    }
    .select-label{
        margin-bottom: .25em;
    }
    .appt-slider{
        margin-bottom: 3em;
        box-sizing: content-box;
    }
    .appt-select {
        margin: 0;
        width: 100%;
        padding: 0 0 0 .25em;
    }
    .appt-genbtn{
        min-width: 80%;
        margin: 2.5em auto 0;
        display: block;
    }
</style>
