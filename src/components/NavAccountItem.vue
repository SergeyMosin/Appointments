<template>
    <ApptIconButton
            :key="curCal.name"
            :text="curCal.name"
            :icon="curCal.rIcon===''?curCal.icon:'srgdev-icon-override'"
            :style="curCal.rIcon"
            :icon-scale="false"
            :loading="curCal.isCalLoading">
        <NcActions menuAlign="right" @open="getCalendars" forceMenu slot="actions">
            <NcActionButton
                    v-for="(cal,index) in calendars"
                    @click="setCalendarFromIndex(index)"
                    :key="cal.name"
                    :icon="cal.icon"
                    :title="cal.name"
                    :disabled="cal.url === ''"
                    class="srgdev_com_circle"
                    closeAfterClick>
            </NcActionButton>
        </NcActions>
    </ApptIconButton>
</template>

<script>
    // noinspection ES6CheckImport
    import{
        NcActionButton,
        NcActions,
     } from '@nextcloud/vue'
    import axios from '@nextcloud/axios'
    import {detectColor} from "../utils.js";
    import ApptIconButton from "./ApptIconButton";


    export default {
        name: 'NavAccountItem',
        props:[
            'curCal'
        ],
        data: function() {
            return {
                /** @type {{icon:string,name:string,url:string}[]} */
                calendars:[],
            };
        },
        components: {
            ApptIconButton,
            NcActionButton,
            NcActions,
        },
        methods: {
            getCalendars: async function () {

                let l=0;
                this.calendars=[]
                this.$set(this.calendars,0,{
                    icon:"icon-loading",
                    name:t('appointments',"Loading..."),
                    clr:"",
                    url:"",
                })
                const f_host = window.location.protocol + '//' + window.location.host+ OC.webroot+"/index.php/svg/appointments/circ?color="
                axios.get('callist')
                    .then(response=>{
                        let cals=response.data.split(String.fromCharCode(31))
                        l=cals.length
                        for(let i=0;i<l;i++){
                            let cal=cals[i].split(String.fromCharCode(30))
                            this.$set(this.calendars, i, {
                                // "Personal", "#795AAB", "personal"
                                icon: f_host + cal[1].slice(1),
                                name: cal[0],
                                clr: detectColor(cal[1]),
                                url: cal[2]
                            })
                        }
                    }).catch(function (error) {
                        console.log(error);
                    }).then(()=>{
                        // always executed
                        if(l===0){
                            this.$set(this.calendars,0,{
                                icon:"icon-error",
                                name:this.t('appointments',"Loading..."),
                                clr:"",
                                url:"",
                            })
                        }
                    });
            },
            setCalendarFromIndex:function (idx) {
                const c=this.calendars[idx]
                this.$emit('calSelected',c)
            },
        }
    }
</script>
