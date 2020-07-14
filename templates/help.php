<div class="srgdev-appt-hs-inner">
    <h2 class="srgdev-appt-hs-h1">1. Select a Calendar (Simple Mode)</h2>
    <p class="srgdev-appt-hs-p"><code class="srgdev-appt-hs-code_short">Manage Appointment Slots &gt; Select a Calendar</code><br>It is recommended to create a separate calendar.</p>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_destcal">Calendar for booked appointments</strong> - if this calendar is different from the main calendar, confirmed/finalized appointments will be moved here. <em style="font-style: italic;">This calendar is reset every time the main calendar is changed.</em></p>
    <h2 class="srgdev-appt-hs-h1">2. Enter Organization Info</h2>
    <p class="srgdev-appt-hs-p">See the "User/Organization Info" section for required Name, Location and Email Address settings.</p>
    <h2 class="srgdev-appt-hs-h1">3. Add Appointments</h2>
    <p class="srgdev-appt-hs-p">Please use the <code class="srgdev-appt-hs-code_short">Manage Appointment Slots &gt; Add Appointment Slots</code> dialog or see "External mode" below.</p>
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
    <p class="srgdev-appt-hs-p-h"><strong>Appointment's Title</strong></p>
    <p class="srgdev-appt-hs-p">If an event's title/summary starts with an "_" character then the title will be displayed next or below the time in the form. For example: <code class="srgdev-appt-hs-code_short" style="padding-top: .1em; padding-bottom: .1em"><strong>_</strong>Language Lessons</code> will be displayed as "Language Lessons"</p>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_style">Style Override</strong></p>
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
&lt;/style&gt;</code>
    <h2 class="srgdev-appt-hs-h1">5. Email Settings</h2>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_emailatt">Email Attendee when the appointment is modified and/or deleted</strong> - Attendees will be notified via email when their <strong>upcoming</strong> appointments are updated or deleted in the calendar app or via some other external mechanism. Only changes to Date/Time, Status or Location will trigger the "Modified" notification.</p>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_emailme">Email Me when an appointment is updated</strong> - A notification email will be sent to you when an appointment is booked via the public page or an upcoming appointment is confirmed or canceled via the email links.</p>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_emailskipevs">Skip email validation step</strong> - When this option is selected the "<em>... action needed</em>" validation email will NOT be sent to the attendee. Instead the "<em>... Appointment is confirmed</em>" message is going to be sent right away, and the "<em>All done</em>" page is going to be shown when the form is submitted.</p>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_emaildef"><code>useDefaultEmail</code></strong> - Most instance of NC won't have the particular configuration allowing to send emails on behalf of organizers. Therefore, the default email address as per <a style="color: blue; text-decoration: underline" href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/email_configuration.html" target="_blank">Mail Settings</a> is used, and your address is added in the "Reply-To:" header field. If your Nextcloud configuration supports sending out emails for individual users, Admins can override the 'useDefaultEmail' directive like so: <code style="background: #eeeeee; padding: 0 .5em">occ config:app:set appointments useDefaultEmail --value no</code></p>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_emailmoretext"><code>Additional Email Text</code></strong>  - this text is appended as paragraph to the end of validation and confirmation email. Currently only pain text is allowed, HTML will be escaped.</p>
    <h2 class="srgdev-appt-hs-h1">6. Share the Public Link</h2>
    <p class="srgdev-appt-hs-p">Enable sharing and pass along the public page link <code class="srgdev-appt-hs-code_short">Public Page [...] &gt; Show URL/link</code>. Upcoming appointments will be available on the booking page.</p>
    <h2 class="srgdev-appt-hs-h1">7. Check Status in the Calendar</h2>
    <p class="srgdev-appt-hs-p">Once an appointment is booked it will be visible in the calendar with "⌛ pending" status. The attendee can "✔️ Confirm" or "<span style="text-decoration: line-through">Cancel</span>" the appointment via an email link, the status change will be reflected in the calendar upon page reload.</p>
    <h2 class="srgdev-appt-hs-h1" id="srgdev-sec_ts_mode">8. Time slot mode</h2>
    <p class="srgdev-appt-hs-p-h"><strong>Simple mode</strong> - Use provided "Add Appointment Slots" dialog to add "available" time slots. Recurrence is not suported in this mode.</p>
    <p class="srgdev-appt-hs-p-h"><strong>External mode</strong> - Use Nextcloud's Calendar App or any other CalDAV compatible client to add "available" timeslots. Most recurrence rules are supported in this mode. Two calendars are required: a "Source Calendar" to keep track of your availability timeslots and a "Destination Calendar" for booked appointments.</p>
    <p style="margin-left: 1em" class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_sourcecal_nr">Source Calendar (External mode)</strong> - Any event with "Show As" a.k.a. "Time As" a.k.a. "Free/Busy" a.k.a. "Time Transparency" set to "<strong>Free</strong>" (<a style="color: blue; text-decoration: underline" href="https://tools.ietf.org/html/rfc5545#section-3.8.2.7">RFC5545 specs</a> "TRANSP:TRANSPARENT") will be available for booking in the public form. Most recurrence rules are supported. Also see <span style="font-style: italic">Require "Appointment" category.</span></p>
    <p style="margin-left: 1em" class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_destcal_nr">Destination Calendar (External mode)</strong> - Booked appointments will be placed in here. In addition to booked appointments, any events in this calendar marked as "<strong>Busy</strong>" will prevent conflicting timeslots in the "Source Calendar" from appearing in the public form. Also see <span style="font-style: italic">Require "Appointment" category.</span></p>
    <p style="margin-left: 1em" class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_push_rec_nr">Optimize recurrence (External mode)</strong> - If recurrent events are used in the "Source Calendar" the start (DTSTART) date will be pushed forward once in a while in order to improve performance.</p>
    <p style="margin-left: 1em" class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_require_cat_nr">Require "Appointment" category (External mode)</strong> - When this option is set only events with with "Category" set to "<strong>Appointment</strong>" (in Engilsh) will be considered.</p>
    <p style="margin-left: 1em" class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_auto_fix_nr">Auto-fix "Source" timeslots (External mode)</strong> - Some calendar apps do not allow users to set Free/Busy parameter, resulting in timeslots not being available for booking. If this option is enabled <strong>AND the first character of the Description/Notes is "_"</strong> then the Free/Busy will be set to "Free" and "Appointment" category will be added automatically when a new event is created in the "Source" calendar.</p>
    <h2 class="srgdev-appt-hs-h1">9. iFrame/Embedding</h2>
    <div class="srgdev-appt-hs-p">
        1. If the iframe is under a different domain use <strong>occ</strong> to set allowed Frame Ancestor Domain:
        <code style="white-space: pre" class="srgdev-appt-hs-code">php occ config:app:set appointments "emb_afad_YourUserName" --value "your.domain.com"</code>
    2. Email confirm/cancel buttons need to be redirected. (If email validation step is skipped then this is not needed).<br>Use <strong>occ</strong> to set base URL for the host page with <strong>a query parameter available at the end of the URL</strong>:
        <code style="white-space: pre" class="srgdev-appt-hs-code">php occ config:app:set appointments "emb_cncf_YourUserName" --value "your.domain.com/page_url?some_param_name="</code>

Example using PHP:
        <code style="white-space: pre" class="srgdev-appt-hs-code">...
&lt;?php
$src='PROVIDED_EMBEDDABLE_URL';
if(isset($_GET['some_param_name'])){
    // Email Confirm/Cancel button was clicked
    $src=substr($src,0,-4).'cncf?d='.urlencode($_GET['some_param_name']);
}
echo '&lt;iframe src = "'.$src.'"&gt;&lt;/iframe&gt;';
?&gt;
...</code>
        Nextcloud <strong>occ</strong>: <a style="color: blue; text-decoration: underline" href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/occ_command.html">https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/occ_command.html</a><br>
        Frame Ancestors: <a style="color: blue; text-decoration: underline" href="https://w3c.github.io/webappsec-csp/#directive-frame-ancestors">https://w3c.github.io/webappsec-csp/#directive-frame-ancestors</a><br>
    </div>

</div>

