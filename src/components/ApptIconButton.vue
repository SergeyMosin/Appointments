<template>
    <div @click="doClick" class="appt_icon_button_cont">
    <div role="button"
         :class="['appt_icon_button',{'disabled':disabled}]">
        <span :class="['aib_icon_wrap',icon,{'icon-loading':loading}]"></span><span class="aib_text_span">{{text}}</span>
    </div>
    <div class="aib_actions_slot">
        <slot name="actions"/>
    </div>
    </div>
</template>

<script>
    export default {
        name: "ApptIconButton",
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
            disabled: {
                type: Boolean,
                default: false
            },
            loading: {
                type: Boolean,
                default: false
            },
        },
        methods:{
            doClick(){
                if(!this.loading && !this.disabled){
                    this.$emit('click')
                }
            }
        }
    }
</script>

<style scoped lang="scss">
.appt_icon_button_cont{
    margin: .25em 0;
    position: relative;
    .aib_actions_slot{
        position: absolute;
        right: 1em;
        top: 0;
        height: 100%;    }
}
.appt_icon_button{
    display: inline-block;
    vertical-align: middle;
    padding: .5em;
    cursor: pointer;
    position: relative;

    .aib_icon_wrap,
    .aib_text_span{
        display: inline-block;
        vertical-align: middle;
    }
    .aib_icon_wrap{
        width: 1.5em;
        height: 1.5em;
        opacity: .7;
        cursor: inherit;
    }
    .aib_text_span{
        margin-left: .75em;
        color: var(--color-main-text);
        height: 2em;
        line-height: 2em;
        cursor: inherit;
    }

    &:hover {
        .aib_icon_wrap {
            opacity: 1;
            transform: scale(1.1);
            transform-origin: center;
        }
    }

    &.disabled{
        pointer-events: none;
        opacity: .75;
    };
}
</style>