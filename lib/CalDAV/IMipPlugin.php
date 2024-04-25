<?php

namespace OCA\Appointments\CalDAV;

use OCA\Appointments\Backend\ApptDocProp;
use OCA\Appointments\Backend\BackendUtils;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\VObject\ITip\Message;

class IMipPlugin extends ServerPlugin
{
    public function initialize(Server $server): void
    {
        $server->on('schedule', [$this, 'schedule'], 50);
    }

    /**
     * We want to stop NC from sending default emails
     *
     * @return bool true = other handlers will deal with this message
     *              false = other handlers will not be called (no email sent)
     */
    public function schedule(Message $iTipMessage): bool
    {

        if ($iTipMessage->component !== 'VEVENT' || !isset($iTipMessage->message->VEVENT)) {
            // we only deal with events
            return true;
        }

        /** @var \Sabre\VObject\Component\VEvent $evt */
        $evt = $iTipMessage->message->VEVENT;

        // we will handle emails for this event if:
        //  1. BackendUtils::XAD_PROP or ApptDocProp::PROP_NAME
        //  2. category is BackendUtils::APPT_CAT
        return !(
            (isset($evt->{BackendUtils::XAD_PROP})
                || isset($evt->{ApptDocProp::PROP_NAME}))
            && isset($evt->CATEGORIES)
            && $evt->CATEGORIES->getValue() === BackendUtils::APPT_CAT
        );
    }
}