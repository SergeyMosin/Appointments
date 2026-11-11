<?php
/** @var array $_ */
$anaCfg = $_['appt_analytics'];
?>
<style>
.appt-consent{position:fixed;left:0;right:0;bottom:0;z-index:10000;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.5em 1em;padding:.75em 1em;background:var(--color-main-background,#fff);color:var(--color-main-text,#222);border-top:1px solid var(--color-border,#ddd);box-shadow:0 -2px 8px rgba(0,0,0,.1);font-size:.95em}
.appt-consent__text{margin:0;max-width:60em}
.appt-consent__link{color:var(--color-primary-element,#00679e);text-decoration:underline}
.appt-consent__actions{display:flex;gap:.5em}
.appt-consent__btn{cursor:pointer;min-height:34px;padding:.3em 1.2em;border-radius:var(--border-radius-pill,20px);border:1px solid var(--color-border-dark,#999);background:var(--color-main-background,#fff);color:var(--color-main-text,#222);font:inherit}
.appt-consent__accept{background:var(--color-primary-element,#00679e);border-color:var(--color-primary-element,#00679e);color:var(--color-primary-element-text,#fff)}
.appt-consent__btn:focus-visible{outline:2px solid var(--color-primary-element,#00679e);outline-offset:2px}
</style>
<script nonce="<?php p($anaCfg['nonce']); ?>">
(function () {
	var c = <?php echo json_encode($anaCfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	var w = window, d = document;
	var consentKey = 'appt-analytics-consent-' + c.page;
	var evtKey = 'appt-analytics-evt-' + c.page;
	var loaded = false;

	function get(k) { try { return w.localStorage.getItem(k); } catch (e) { return null; } }
	function set(k, v) { try { w.localStorage.setItem(k, v); } catch (e) { } }
	function add(src) {
		var s = d.createElement('script');
		s.async = true;
		s.src = src;
		s.nonce = c.nonce;
		d.head.appendChild(s);
	}

	function load() {
		if (loaded) return;
		loaded = true;
		w.dataLayer = w.dataLayer || [];
		if (c.gtm) {
			w.dataLayer.push({'gtm.start': new Date().getTime(), event: 'gtm.js'});
			add('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(c.gtm));
		} else if (c.ga4) {
			w.gtag = w.gtag || function () { w.dataLayer.push(arguments); };
			w.gtag('js', new Date());
			w.gtag('config', c.ga4);
			add('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(c.ga4));
		}
		if (c.clarity) {
			w.clarity = w.clarity || function () { (w.clarity.q = w.clarity.q || []).push(arguments); };
			add('https://www.clarity.ms/tag/' + encodeURIComponent(c.clarity));
			w.clarity('consent');
		}
		if (c.li) {
			w._linkedin_partner_id = c.li;
			w._linkedin_data_partner_ids = w._linkedin_data_partner_ids || [];
			w._linkedin_data_partner_ids.push(c.li);
			if (!w.lintrk) {
				w.lintrk = function (a, b) { w.lintrk.q.push([a, b]); };
				w.lintrk.q = [];
			}
			add('https://snap.licdn.com/li.lms-analytics/insight.min.js');
		}
		fireEvent();
	}

	function fireEvent() {
		// once per booking, reloads of the finish page do not count again
		if (!c.evt || get(evtKey) === c.evt) return;
		set(evtKey, c.evt);
		if (c.gtm) {
			w.dataLayer.push({event: 'booking_submitted'});
		} else if (c.ga4) {
			w.gtag('event', 'booking_submitted');
		}
		if (c.clarity) w.clarity('event', 'booking_submitted');
		if (c.li && c.liConv) w.lintrk('track', {conversion_id: parseInt(c.liConv, 10)});
	}

	function showBar() {
		var bar = d.createElement('div');
		bar.className = 'appt-consent';
		bar.setAttribute('role', 'dialog');
		bar.setAttribute('aria-modal', 'false');
		bar.setAttribute('aria-live', 'polite');
		bar.setAttribute('aria-label', c.l.label);

		var p = d.createElement('p');
		p.className = 'appt-consent__text';
		p.textContent = c.text + ' ';
		if (c.privacyUrl) {
			var a = d.createElement('a');
			a.className = 'appt-consent__link';
			a.href = c.privacyUrl;
			a.target = '_blank';
			a.rel = 'noopener';
			a.textContent = c.l.privacy;
			p.appendChild(a);
		}
		bar.appendChild(p);

		var actions = d.createElement('div');
		actions.className = 'appt-consent__actions';
		function btn(cls, label, choice) {
			var b = d.createElement('button');
			b.type = 'button';
			b.className = 'appt-consent__btn ' + cls;
			b.textContent = label;
			b.addEventListener('click', function () {
				set(consentKey, choice);
				bar.parentNode.removeChild(bar);
				if (choice === 'granted') load();
			});
			actions.appendChild(b);
		}
		btn('appt-consent__decline', c.l.decline, 'denied');
		btn('appt-consent__accept', c.l.accept, 'granted');
		bar.appendChild(actions);
		d.body.appendChild(bar);
	}

	function init() {
		if (c.test) {
			load();
			return;
		}
		var choice = get(consentKey);
		if (choice === 'granted') {
			load();
		} else if (choice !== 'denied') {
			showBar();
		}
	}

	if (d.readyState === 'loading') {
		d.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
</script>
