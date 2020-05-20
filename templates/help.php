<div class="srgdev-appt-hs-inner">
    <h2 class="srgdev-appt-hs-h1">1. Select a Calendar</h2>
    <p class="srgdev-appt-hs-p">It is recommended to create a separate calendar.</p>
    <h2 class="srgdev-appt-hs-h1">2. Enter Organization Info</h2>
    <p class="srgdev-appt-hs-p">See the "User/Organization Info" section for required Name, Location and Email Address settings.</p>
    <h2 class="srgdev-appt-hs-h1">3. Add Appointments</h2>
    <p class="srgdev-appt-hs-p">Please use the "Add Appointment Slots" dialog.</p>
    <div class="srgdev-appt-hs-p">
        <span>1. Set "Schedule Generator" settings</span><br>
        <span>2. Use "3 Dot" dropdown menus</span><br>
        <span>3. Adjust times/Add break times by dragging slots up/down</span><br>
        <span>4. Duplicate the day's slots by clicking "Copy to Next" day option in ellipsis menu</span><br>
    </div>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_timezone">Timezone Options:</strong></p>
    <p class="srgdev-appt-hs-p"><span style="text-decoration: underline">Local (floating)</span> - this option should be used if you are booking appointments for a real location, like an office or a store. "Floating" values are not bound to any time zone in particular, and will represent the same hour, minute, and second value regardless of which time zone is currently being observed.</p>
    <p class="srgdev-appt-hs-p srgdev-appt-hs-p_t">Example: 12:00PM <strong>floating</strong> time appointment based in New York</p>
    <img class="srgdev-appt-hs-tz-img" src="<?php print_unescaped(image_path('appointments', 'floating_timezone.jpg')); ?>" />
    <p class="srgdev-appt-hs-p"><span style="text-decoration: underline">Calendar Timezone</span> - your calendar's timezone will be used. This option should be used if you are booking events where people participate from different locations(timezones), like phone calls or video conferences. <strong>Appointment time is "casted" to visitors local time.</strong></p>
    <p class="srgdev-appt-hs-p srgdev-appt-hs-p_t">Example: 12:00PM <strong>America/New_York timezone</strong>  appointment based in New York</p>
    <img class="srgdev-appt-hs-tz-img" src="<?php print_unescaped(image_path('appointments', 'actual_timezone.jpg')); ?>" />
    <h2 class="srgdev-appt-hs-h1">4. Customize Public Page</h2>
    <p class="srgdev-appt-hs-p"><strong id="srgdev-sec_gdpr">GDPR Compliance</strong></p>
    <p class="srgdev-appt-hs-p">Any text in the "GDPR Compliance" field will trigger display of the "GDPR" check box. Plain text (no html) will work as is, but if you need to add a link to a privacy policy please read on... For the link to work properly you should separate it from the &lt;label&gt; element, and the &lt;label&gt;'s <strong>"for"</strong> attribute MUST be set to <strong>"appt_gdpr_id"</strong>, example:</p>
<code class="srgdev-appt-hs-code">
&lt;label for=&quot;appt_gdpr_id&quot;&gt;Some text &lt;/label&gt;&lt;a href=&quot;PRIVACY_POLCY_URL&quot;&gt;Privacy Policy&lt;/a&gt;&lt;label for=&quot;appt_gdpr_id&quot;&gt; some more text.&lt;/label&gt;
</code>
    <p class="srgdev-appt-hs-p"><strong id="srgdev-sec_style">Style Override</strong></p>
    <p class="srgdev-appt-hs-p">Insert custom <code>&lt;style&gt;&lt;/style&gt;</code> element to override default page style. Try something like this for example:</p>
<code style="white-space: pre;" class="srgdev-appt-hs-code">&lt;style&gt;
#header{
    background: transparent !important;
}
#content{
    background: linear-gradient(to bottom, #ff00cc, #333399)  !important;
}
#body-public #content {
    min-height: 100%;
}
form{
    background:whitesmoke;box-shadow: 3px 3px 25px 0px rgba(0,0,0,0.75);
}
.srgdev-ncfp-form-header{
    border-bottom: 3px solid #961AB1;
}
&lt;/style&gt;
</code>
    <h2 class="srgdev-appt-hs-h1">5. Email Settings</h2>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_emailatt">Email Attendee when the appointment is modified and/or deleted</strong> - Attendees will be notified via email when their <strong>upcoming</strong> appointments are updated or deleted in the calendar app or via some other external mechanism. Only changes to Date/Time, Status or Location will trigger the "Modified" notification.</p>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_emailme">Email Me when an appointment is updated</strong> - A notification email will be sent to you when an appointment is booked via the public page or an upcoming appointment is confirmed or canceled via the email links.</p>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_emailskipevs">Skip email validation step</strong> - When this option is selected the "<em>... action needed</em>" validation email will NOT be sent to the attendee. Instead the "<em>... Appointment is confirmed</em>" message is going to be sent right away, and the "<em>All done</em>" page is going to be shown when the form is submitted. <span style="font-style: italic">As of now, appointment cancellation link/button is <strong>NOT</strong> included in the confirmation email.</span></p>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_emaildef"><code>useDefaultEmail</code></strong> - Most instance of NC won't have the particular configuration allowing to send emails on behalf of organizers. Therefore, the default email address as per <a style="color: blue; text-decoration: underline" href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/email_configuration.html" target="_blank">Mail Settings</a> is used, and your address is added in the "Reply-To:" header field. If your Nextcloud configuration supports sending out emails for individual users, Admins can override the 'useDefaultEmail' directive like so: <code style="background: #eeeeee; padding: 0 .5em">occ config:app:set appointments useDefaultEmail --value no</code></p>
    <h2 class="srgdev-appt-hs-h1">6. Share the Public Link</h2>
    <p class="srgdev-appt-hs-p">Enable sharing and pass along the public page link. Upcoming appointments will be available on the booking page.</p>
    <h2 class="srgdev-appt-hs-h1">7. Check Status in the Calendar</h2>
    <p class="srgdev-appt-hs-p">Once an appointment is booked it will be visible in the calendar with "⌛ pending" status. The attendee can "✔️ Confirm" or "<span style="text-decoration: line-through">Cancel</span>" the appointment via an email link, the status change will be reflected in the calendar upon page reload.</p>

</div>

