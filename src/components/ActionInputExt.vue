<script>
import {ActionInput} from '@nextcloud/vue'
export default {
    components: {ActionInput},
    extends: ActionInput,
    name: "ActionInput",
    methods: {
        onSubmit(event) {
            ActionInput.methods.onSubmit.call(this,event)
            if (!this.disabled && this.$parent && this.$parent.closeMenu) {
                this.$parent.closeMenu()
            }
        }
    }
}
</script>