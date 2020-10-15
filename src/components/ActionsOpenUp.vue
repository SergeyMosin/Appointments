<script>
import {Actions} from '@nextcloud/vue'
export default {
  components: {Actions},
  name: "ActionsOpenUp",
  extends: Actions,
  methods: {
    onOpen() {
      this.offsetX = 0
      this.offsetY = 0 - Math.round(this.$refs.menu.clientHeight) - 42
      this.offsetYArrow = Math.round(this.$refs.menu.clientHeight) + 17
      this.rotateArrow = true
    },
  }
}
</script>

