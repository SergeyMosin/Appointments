<template>
    <SlideBar :title="this.title" :subtitle="subtitle" @close="function() {
        resetAppt()
        $emit('close')
    }">
        <template slot="main-area">
            <div class="appt-gen-wrap">
            <label class="datepicker-label">Dates:</label>
            <DatePicker
                    :disabled-date="compNotBefore"
                    :appendToBody="false"
                    :popup-style="datePickerPopupStyle"
                    placeholder="Select Dates"
                    v-model="apptWeek"
                    :lang="lang"
                    @input="setToStartOfWeek"
                    :format="weekFormat"
                    type="week"></DatePicker>
            <label class="datepicker-label">Start Time:</label>
            <DatePicker
                    :appendToBody="false"
                    :popup-style="datePickerPopupStyle"
                    placeholder="Select Time"
                    v-model="apptStart"
                    :lang="lang"
                    :editable="false"
                    :format="getTimeFormat()"
                    :time-picker-options="{start: '09:00', step:'00:30' , end: '17:00', format: getTimeFormat() }"
                    type="time"></DatePicker>
                <label for="appt-dur-select" class="select-label">Appointment Duration:</label>
                <Multiselect
                        v-model="apptDur"
                        :value="apptDur"
                        :options="apptDurOpts"
                        :searchable="false"
                        :allowEmpty="false"
                        placeholder="Select Time"
                        openDirection="below"
                        track-by="min"
                        id="appt-dur-select"
                        class="dur-select"
                        label="label"/>
                <label for="appt-dur-select2" class="select-label">Appointments per Day:</label>
                <Multiselect
                        v-model="apptNbr"
                        :value="apptNbr"
                        :options="apptNbrOpts"
                        :searchable="false"
                        :allowEmpty="false"
                        openDirection="above"
                        placeholder="Select Number"
                        id="appt-dur-select2"
                        class="dur-select"/>
            <div class="checkbox-wrap">
            <input type="checkbox" id="srgdev-appt-cb1" class="checkbox"
                   checked="checked" v-model="apptLunch">
            <label for="srgdev-appt-cb1">Add Launch Break</label><br>
            </div>
            <button @click="goApptGen" :disabled="apptWeek===null || apptStart===null" class="primary appt-genbtn">
                Start
            </button>
            </div>
        </template>
    </SlideBar>
</template>

<script>
    import SlideBar from "./SlideBar.vue"
    import DatePicker from 'vue2-datepicker'
    import '../../css/datepicker.css';
    import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

    export default {
        name: "ScheduleSlideBar",
        components: {
            SlideBar,
            Multiselect,
            DatePicker},
        props:{
            title:'',
            subtitle:'',
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
        data() {
            return {
                /** @type {Date} */
                apptWeek:null,
                /** @type {Date} */
                apptStart:null,

                apptDurOpts:[
                    {min:30,label:"30 Min"},
                    {min:45,label:"45 Min"},
                    {min:60,label:"1 Hour"}
                ],
                apptNbr:'5',
                apptNbrOpts:['2','3','4','5','6','7','8','9','10','11','12','13','14','15'],

                apptDur:{min:60,label:"1 Hour"},
                apptLunch:true,


                datePickerPopupStyle:{top:"100%",left:0},
                weekFormat: {
                    // Date to String
                    stringify: (date,fmt) => {
                        // console.log(fmt)
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
                // console.log()
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
                this.apptStart=null
                this.apptNbr='5'
                this.apptDur={min:60,label:"1 Hour"}
                this.apptLunch=true
            },
            goApptGen(){
                let r={
                    week:(this.apptWeek.getTime()),
                    startTime:this.apptStart.getHours()*60+this.apptStart.getMinutes(),
                    dur:this.apptDur.min,
                    nbr:+this.apptNbr,
                    lunch:this.apptLunch
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
    }
    .datepicker-label,
    .select-label{
        display: block;
        margin-top: 1em;
    }
    .select-label{
        margin-bottom: .25em;
    }
    .checkbox-wrap{
        margin-top: 1.25em;
    }
    .appt-genbtn{
        min-width: 80%;
        margin: 3em auto 0;
        display: block;
    }
    .dur-select{
        width: 100%;
    }
</style>