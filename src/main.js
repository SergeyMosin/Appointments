import Vue from 'vue'
import App from './App2.vue'
import {createPinia, PiniaVuePlugin} from 'pinia'

Vue.use(PiniaVuePlugin)
const pinia = createPinia()

// Vue.config.devtools = true;


// CSP config for webpack dynamic chunk loading
// noinspection JSUnresolvedVariable
// __webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
// noinspection JSUnresolvedVariable
// __webpack_public_path__ = linkTo('appointments', 'js/')

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

export default new Vue({
	el: '#content',
	pinia,
	render: h => h(App),
})
