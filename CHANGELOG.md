## v1.14.9 - 2023-01-11
### Changed
- 'Name' label -> 'Full Name' on the main form
### Fixed
- Front-end missing translations module causing broken validation - #392

## v1.14.8 - 2023-01-04
### Fixed
- Time not selectable when appointment has multiple durations - #391
- Add duration is not working unless slider is moved firsts

## v1.14.7 - 2022-12-12
### Added
- 18 and 24 weeks book ahead durations - #385
- 'Auto Style' support for custom colors  - #387
### Changed
- Removing unused NC js files to speedup form page
### Fixed
- Removed unnecessary translations - #388
- Normalize 'Auto Style' fallbacks - #387

## v1.14.6 - 2022-12-04
### Added
- Accessibility: add keyboard navigation to booking form - #214
- Multiple "extra" fields. Thanks @LadySolveig - #223
### Changed
- Updated build dependencies
### Closed pull requests
-  Feature: Support for multiple custom fields - #259 ([LadySolveig](https://github.com/LadySolveig))

## v1.14.5 - 2022-12-03
### Added
- Appointment title templating - #382
### Fixed
- Organizer email is not sent in some cases - #383

## v1.14.4 - 2022-12-02
### Changed
- Log warnings instead of errors for missing timezones
- Optimize "Auto Style" functionality (upgrade from v1.14.2 and v1.14.3 to improve performance)

## v1.14.3 - 2022-11-15
### Fixed
- NC25 Talk integration errors - #376
- Modal info popups - #377

## v1.14.2 - 2022-11-13
### Fixed
- "Auto Style" not working with NC 25.0.1 and guest pages

## v1.14.1 - 2022-11-13
### Added
- "Auto Style" toggle to NC25
- "pageId" to email buttons urls when using embedded form - #372
- Log error details when email to organizer fails - #375
### Changed
- Update "@nextcloud/vue" to v7.0.1 - #366
- Min Nextcloud version is 24 now

## v1.14.0 - 2022-10-29
### Fixed
- Styles and icons for NC 25 - #366
### Changed
- Public page title from `Nextcloud` -> `Appointments - Nextcloud` - #362
- Email footer uses `$theme->getEntity()` instead of `Nextcloud`

## v1.13.0 - 2022-10-25
### Added
- Next release possible breaking changes notice (prep for NC25)
### Changed
- Nextcloud 23 is min version now

## v1.12.8 - 2022-09-12
### Fixed
- Expected parameter of type '\DateTime', 'null' provided in Talk Integration

## v1.12.7 - 2022-06-23
### Fixed
- Wrong L10N class is passed to EMailTemplate::class constructor when custom 'mail_template_class' is used - issue #344

## v1.12.6 - 2022-06-06
### Fixed
- Ensure 'Additional ICS file description' is included - issue #342
- 'Form Title' settings display (single page mode) - issue #343
### Changed
- Use full time zone names(Eastern Daylight Time) instead of abbreviations(GMT-4) in PHP
- NC 'Locale' settings (instead of 'Language') is now used for JS i18n Dates/Times - issue #244
- HTML is now allowed in 'Additional Email Text' - issue #132
- Better error handling in extNotify function
- Update node dependencies (npm -> pnpm)

## v1.12.5 - 2022-05-24
### Added
- Default reminders language indicator - issue #323
- Rudimentary notification extension system - issue #26
### Fixed
- Ignore "trash bin"(deleted) calendars - issue #64

## v1.12.4 - 2022-05-11
### Fixed
- Template/grid editor header dark theme compatibility - issue #327
- Reminders test
### Changed
- Nextcloud min version is 22 now (max version is 24) - issue #324
- Include 'CLASS: CONFIDENTIAL' (but not 'PRIVATE') events in conflict checks - issue #321
- Template/grid editor range is extended to start at 6:00 and end at 23:00 - issue #332

## v1.12.3 - 2022-04-11
### Added
- redirect to a custom "All Done" page after confirm option - issue #315
- improve iframe embedding (window.parent.postMessage) and more examples  - issue #313
### Fixed
- custom styles not applied to all pages - issue #313

## v1.12.2 - 2022-03-10
### Added
- option to add booked/pending appointment buffers (before/after blockers)
### Fixed
- missing translation - issue #190
### Changed
- trailing/filler empty days are not shown - issue #306

## v1.12.1 - 2022-03-02
### Fixed
- PHP: error when "Skip email validation step" option enabled - issue #304

## v1.12.0 - 2022-02-21
### Added
- Sunday is available now in "Edit Template" and "Add Appointments" - issue #13
- New "Private Page" mode: visitors must be logged-in to NC - issue #298
- Debugging: add "Sync Remote Calendar Now" option
### Fixed
- "Show end time" option is always ON in "weekly template mode" - issue #299
- JS: errors and "this" scope in doCopyPubLink function
- Cancellations and reminders not working in "simple" mode with dual calendars (regression from v1.11.14 ) - issue #302
### Changed
- dev: updated dependencies and config files

## 1.11.14 - 2022-02-14
### Changed
- Confirm, Cancel and ChangeType pages now have a "confirm" button to prevent antivirus / antimalware scans from taking action automatically - issue #293
### Added
- "GDPR text only (no checkbox)" to page settings - issue #292
### Fixed
- Dark theme styles in "Quick Start Guide" - issue #289

## 1.11.12 - 2022-01-26
### Added
- Option to add custom text to event/appointment DESCRIPTION property (the text is also added to .ics email attachment)

## 1.11.11 - 2022-01-19
### Fixed
- Interval tree bug: lookup might report busy slot as free under certain circumstances - issue #282
### Changed
- Allow non admin users to debug/dump own raw calendar data

## 1.11.10 - 2022-01-02
### Added
- Log remote blockers debugging option

## 1.11.9 - 2021-12-30
### Added
- Raw calendar data dump debugging option
- More logging in timezone detection

## 1.11.8 - 2021-12-11
### Added
- Read-only and linked/subscription calendars are available for conflict checks in "Weekly Template" mode now.

## 1.11.7 - 2021-12-09
### Fix
- multiple template mode pages might throw errors if in different timezones, possibly related to #272

## 1.11.6 - 2021-12-04
### Change
- max-version="22" -> max-version="23"

## 1.11.4 - 2021-11-29
### Fixed
- Remove time zone check on apply because the logic is moved to Template Edit Screen - issue #243

## 1.11.3 - 2021-11-27
### Fixed
- Increase hash_table.uid column length to 255 (same as calendarobjects.uid length) - issue #253
### Changed
- Use calendar timezone whenever possible - issue #243
- Moved time zone indicator to Template Edit screen

## 1.11.2 - 2021-11-16
### Fixed
- Max appointment duration in weekly template" mode - issue #230
### Changed
- Removed some deprecated \OC::$server->... calls
- Vue components directory structure
### Added
- "Quick Start Guide" + some more help
- Reminders - issue #68

## 1.10.2 - 2021-09-05
### Fixed
- Quotes escape in additional form field - issue #202
### Changed
- Max text length 255->512 in additional form field - issue #202
- Remove 8 week limiter in "template mode" - issue #234
### Added
- Option to allow all day events to block - issue #226

## 1.10.1 - 2021-09-01
### Added
- New translations after removal of ":" punctuation

## 1.10.0 - 2021-08-08
### Fixed
- No emails on nc22 - issue #225
### Changed
- Nextcloud min version is 21 now
- Remove ":" punctuation from the form ("Name:" -> "Name") because of nc22 l10n bug

## 1.9.3 - 2021-06-19
### Fixed
- double booking check overlap on adjacent timeslots - issue #209

## 1.9.2 - 2021-06-12
### Added
- Version info to `settings dump`
- Additional logging for #217 and #209 debugging
### Fixed
- End-time not showing in `simple` and `external` modes
### Changed
- Updated timezone info
- Updated build tools and dependencies
- Minor CSS tweaks


## 1.9.1 - 2021-04-20
### Fixed
- CSS layering - issue #203

## 1.9.0 - 2021-04-18
### Added
- page name to emails when multiple pages are in use
### Fixed
- incorrect timezone under some circumstances - issue #195
- grid menus not closing
### Changed
- Nextcloud min version is 20 now (v18 and v19 are not supported anymore)
- if enabled, timezone is displayed next to the date (instead of in the time cell)

## 1.8.10 - 2021-03-06
### Changed
- 'disableForGroups' -> 'limitToGroups'

## 1.8.9 - 2021-03-06
### Added
- 'disableForGroups' occ setting
### Fixed
- Talk integration when 'email attendee' is disabled
### Changed
- 'email attendee' options are 'On' by default now


## 1.8.8 - 2021-03-06
### Added
- DebugController
### Changed
- max-version is 21 now

## 1.8.7 - 2021-03-01
### Fix
- Issue #184 part 2

## 1.8.6 - 2021-03-01
### Fix
- Infinite loop in 'external mode' issue #184

## 1.8.5 - 2021-02-28
### Fix
- Daylight savings and start_date detection in 'weekly template' mode

## 1.8.4 - 2021-02-23
### Fix
- Talk 10.1.* integration - issue #179

## 1.8.3 - 2021-02-14
### Change
- Add recurrence blocking in external mode - issue #168
- Add more 'leadtime' options - issue #135

## 1.8.2 - 2021-02-07
### Fixed
- Past dates are shown in week template
- Access array offset on value of type null for additional pages

## 1.8.1 - 2021-02-01
### Fixed
- Upgrade failed issue #173 (POSTGRESQL ?)

## 1.8.0 - 2021-01-30
### Added
- Template Mode
### Changed
- Appointment slots can overlap now in the editor
- Use own DB table for settings, instead of 'oc_preferences'
### Fixed
- Modals on wide screens
- Doctrine3 type strings
- 'null' array in extra fields


## 1.7.15 - 2020-12-21
### Fixed 
- define 'more_html' at the start

## 1.7.14 - 2020-11-20
### Changed
- translations

## 1.7.13 - 2020-11-20
### Added
- Extra input field option - issue #24
### Changed 
- Removed "floating" timezones support

## 1.7.12 - 2020-10-31
### Changed 
- Change triple dot to ellipsis PR #144
### Fixed
"Uninitialized string offset: 0 at BackendUtils.php#665" possibly related to issue #149

## 1.7.11 - 2020-10-17
### Added
- display option for 8 and 12 weeks pull #138

## 1.7.10 - 2020-10-11
- translation release - issue# 142 ?

## 1.7.9 - 2020-10-11
### Added
- Meeting type change capabilities - issue# 140

## 1.7.8 - 2020-10-10
### Fixed
- Issue #139, NC20 talk integration.
- Issue #141, NC20 email template error.

## 1.7.7 - 2020-10-03
### Fixed
- Issue #136, wrong location when additional pages are used.

## 1.7.6 - 2020-09-22
### Fixed
- NC19 email template regression

## 1.7.5 - 2020-09-21
### Added
- "Meeting Type" form field for Talk integration
### Changed
- Use NC18 email template even on NC19
### Fixed
- CSS for dark themes


## 1.7.4 - 2020-09-20
### Added
- Talk App integration

## 1.7.3 - 2020-09-11
### Fixed
- issue #120

## 1.7.2 - 2020-09-06
### Fixed
- issue #123 

## 1.7.1 - 2020-09-05
### Fixed
- issue #124 

## 1.7.0 - 2020-09-05
### Added
- Multi-page support
### Changed
- Email confirm/cancel buttons depend on an attendee's PARTSTAT parameter
### Fixed
- Use "mailto" scheme in .ics attachments instead of "acct" 

## 1.6.8 - 2020-08-11
### Fixed
- issue #116

## 1.6.7 - 2020-07-29
### Added
- "Show end time" option
- "Show timezone" option
- spinner to "Book now" button
### Fixed
- pending appointments are not cancelled in "simple mode" with dual calendars

## 1.6.6 - 2020-07-26
### Added
- swipe and mobile style to the public page
### Changed
- moved calendar selectors to "Calendars" section
- internal code cleanup and optimization
### Fixed
- issue #112 (nginx)

## 1.6.5 - 2020-07-20
### Fixed
- finalize issue #111

## 1.6.4 - 2020-07-20
### Fixed
- Lodash security update
- issue #111

## 1.6.3 - 2020-07-14
### Fixed
- Stale calendar info

## 1.6.2 - 2020-07-14
### Added
- "Auto-fix" option for "External Mode"
- Ability to show appointment's title in the form
- Minimum prep/lead time

## 1.6.1 - 2020-07-06
### Changed
- "Appointment" category is optional in "External Mode"

## 1.6.0 - 2020-07-04
### Added
- External Mode to timeslot management
- Cancellation link to confirmation emails
### Changed
- "Sunday" is red now
### Fixed
- Hash table cleanup when deleting old appointments.

## 1.5.2 - 2020-06-15
### Added
- Text to "Public Page URL" dialog buttons
- Setup cancellation link for confirmation emails (awaiting translations)

## 1.5.1 - 2020-06-10
### Added
- css for XL screens
- Saturday to "add appointments" grid

## 1.5.0 - 2020-06-03
### Changed
- Grouped calendar options into 'Manage Appointment Slots'
- Moved 'Attendee Cancels' options to 'Manage Appointment Slots &gt; Advanced Options'
- Moved 'Copy public link' to 'Public Page [...]' menu
### Added
- Options for additional email text
- Added 'Remove Old Appointments' option
- Iframes support

## 1.4.16 - 2020-05-20
### Added
- Option to add 'robots noindex' meta tag
### Fixed
- Preview for "skip email verification step" option

## 1.4.15 - 2020-05-13
### Fixed
- frontend error check fails when date empty

## 1.4.14 - 2020-05-07
### Added
- Option to skip email validation test

## 1.4.13 - 2020-05-07
### Test
- Install test

## 1.4.12 - 2020-05-07
### Changed
- Display all existing appointments in the schedule generator
### Fixed
- Shared calendars support (must have edit permission)

## 1.4.11 - 2020-05-05
### Added
- Translations

## 1.4.10 - 2020-05-01
### Added
- Translations push

## 1.4.9 - 2020-04-29
### Added
- Option to hide phone number input
### Fixed
- Confirm page error (regression from v1.4.8)

## 1.4.8 - 2020-04-28
### Changed
- Timezone info in the help section
### Added
- Longer hours in the 'Schedule Generator' issues #62
### Fixed
- Error reporting when 'Schedule Generator' fails
- Do not re-run old update-hook.

## 1.4.6 - 2020-04-24
### Changed
- Free/Busy - issue #57
### Fixed
- translations 

## 1.4.5 - 2020-04-23
### Fixed
- Makefile and path to timezones.json

## 1.4.4 - 2020-04-23
### Fixed
- Minor fixes

## 1.4.3 - 2020-04-23
### Fixed
- issue #61: emails not sent on postgres instances
### Changed
- Non 'floating' times are "casted" to visitors local times if in different timezone

## 1.4.2 - 2020-04-22
### Fixed
- issue 51: can't set calendar on some instances

## 1.4.1 - 2020-04-21
### Fixed
- Safari wrong time issue 59
### Changed
- Make default email default issues 52 53

## 1.4.0 - 2020-04-20
### Changed
- Moved "User/Organization" settings to navigation area
- "Add Appointments" -> "Add Appointment Slots"
- "Help/Tutorial" is a toggle now
### Added
- Timezone abbreviation for non "floating" time appointments in emails
- Options for sending "update/cancel" emails to attendees when appointments are updated or deleted via the calendar or other external app
- Appointment booked/confirmed/canceled notifications for organizer
- Reset option for cancelled appointments.
- "appointments.use.default.email" config.php option for servers that do not provide email addresses for all users
- Advanced public page customization options
- BackendManager class and IBackendConnector interface
- DavListener class to send email on "updateCalendarObject" and "deleteCalendarObject" events
- "appointments_hash" table to keep track of active appointments
### Fixed
- "Copy public link" button for older versions of Safari
- Minor style tweaks

## 1.1.10 - 2020-03-20
### Fixed
- Webpath for custom install directories
### Changed
- GDPR checkbox style

## 1.1.9 - 2020-03-18
### Fixed
- Fetch public link from server if empty

## 1.1.8 - 2020-03-18
### Fixed
- Minor stability issues

## 1.1.7 - 2020-03-17
### Added
- GDPR Compliance
### Fixed
- NC16 Compatibility

## 1.1.6 - 2020-03-16
### Fixed
- Issue #29
- acorn security alert

## 1.1.5 - 2020-03-11
### Fixed
- Mixed timezones in date/time picker

## 1.1.4 - 2020-03-10
### Added
- Timezone support
- Custom form title option
- Add phone number to .ics files

## 1.1.3 - 2020-03-09
### Fixed
- Max range verified

## 1.1.2 - 2020-03-09
### Fixed
- Daylight savings grid drift
- Extra empty week
### Added
- Empty days text
### Changed
- Max range is 5 weeks now
- Help/Tutorial

## 1.1.1 - 2020-03-06
### Fixed
- Public page settings propagation

## 1.1.0 - 2020-03-06
### Added
- Address in the appointment location filed
- .ics file attachment option
### Changed
- New public page date/time picker UI
- New appointment generator UI
### Fixed
- Daylight savings 1 hour shift

## 1.0.9 - 2020-02-27
### Added
- L10N compatibility

## 1.0.8 - 2020-02-26
### Fixed
- Issue #9 (PostgreSQL related)
### Added
- L10N compatibility (partial)

## 1.0.7 - 2020-02-26
### Fixed
- Mostly style tweaks

## 1.0.6 - 2020-02-25
### Fixed
- PHP 5 token encode error 

## 1.0.5 - 2020-02-25
### Fixed
- Dark theme
- Persist tokens after update 

## 1.0.4 - 2020-02-25
### Fixed
- Token verify bug

## 1.0.3 - 2020-02-25
### Fixed
- Multi-user access
- Token verification

## 1.0.1 - 2020-02-24
### Added
- Initial Release
