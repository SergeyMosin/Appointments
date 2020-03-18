<div class="srgdev-appt-hs-inner">
    <h2 class="srgdev-appt-hs-h1">1. Select a Calendar</h2>
    <p class="srgdev-appt-hs-p">It is recommended to create a separate calendar.</p>
    <h2 class="srgdev-appt-hs-h1">2. Enter Organization Info</h2>
    <p class="srgdev-appt-hs-p">See the Settings section for the required Organization Name, Address and Email.</p>
    <h2 class="srgdev-appt-hs-h1">3. Add Appointments</h2>
    <p class="srgdev-appt-hs-p">Please use the "Add Appointments" dialog.</p>
    <p class="srgdev-appt-hs-p"><strong id="srgdev-sec_timezone">Timezone Options:</strong></p>
    <p class="srgdev-appt-hs-p"><span style="text-decoration: underline">Local (floating)</span> - this option should be used if you are booking appointments for a real location, like an office or a store. "Floating" values are not bound to any time zone in particular, and will represent the same hour, minute, and second value regardless of which time zone is currently being observed.</p>
    <p class="srgdev-appt-hs-p"><span style="text-decoration: underline">Calendar Timezone</span> - your calendar's timezone will be used. This option should be used if you are booking events where people participate from different locations, like phone calls or video conferences.</p>
    <p class="srgdev-appt-hs-p" style="font-style: italic">This options mostly affect the .ics file attached to the confirmation email. Also, Gnome Calendar does not like "floating" timezones.</p>
    <h2 class="srgdev-appt-hs-h1">4. Customize Public Page</h2>
    <p class="srgdev-appt-hs-p"><strong id="srgdev-sec_gdpr">GDPR Compliance:</strong></p>
    <p class="srgdev-appt-hs-p">Any text in the "GDPR Compliance" field will trigger display of the "GDPR" check box. Plain text (no html) will work as is, but if you need to add a link to a privacy policy please read on... For the link to work properly you should separate it from the &lt;label&gt; element, and the &lt;label&gt;'s <strong>"for"</strong> attribute MUST be set to <strong>"appt_gdpr_id"</strong>, example:</p>
<code class="srgdev-appt-hs-code">
&lt;label for=&quot;appt_gdpr_id&quot;&gt;Some text &lt;/label&gt;&lt;a href=&quot;PRIVACY_POLCY_URL&quot;&gt;Privacy Policy&lt;/a&gt;&lt;label for=&quot;appt_gdpr_id&quot;&gt; some more text.&lt;/label&gt;
</code>

    <h2 class="srgdev-appt-hs-h1">5. Share the Public Link</h2>
    <p class="srgdev-appt-hs-p">Enable sharing and pass along the public form link. Seven upcoming days of appointments are going to be available on the booking page.</p>
    <h2 class="srgdev-appt-hs-h1">6. Check Status in the Calendar</h2>
    <p class="srgdev-appt-hs-p">Once an appointment is booked it will be visible in the calendar with "⌛ pending" status. The attendee can "✔️ Confirm" or "<span style="text-decoration: line-through">Cancel</span>" the appointment via an email link, the status change will be reflected in the calendar upon page reload.</p>

</div>

