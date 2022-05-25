<?php


/**
 * This is just a test file.
 *
 * IMPORTANT:
 *  DO NOT PLACE YOUR EXTENSION FILES IN THIS DIRECTORY.
 *  YOU WILL LOSE THEM AFTER APP UPDATES.
 *
 * @see https://github.com/SergeyMosin/Appointments/issues/26 for more info
 *
 * @param array $eventData
 *  @type int eventData['dateTime']:
 *      0 = Created/Confirmed
 *      1 = Cancelled/Deleted
 *      2 = Updated/Changed
 *      3 = Appointment Type Changed (Talk Integration)
 *      4 = Reminder
 *  @type \DateTime eventData['dateTime'] - event date and time
 *  @type string eventData['attendeeName']
 *  @type string eventData['attendeeEmail']
 *  @type string eventData['attendeeTel']
 *  @type string eventData['pageId']
 *
 * @param \Psr\Log\LoggerInterface $logger
 *
 * @return void
 */
function notificationEventListener(array $eventData, \Psr\Log\LoggerInterface $logger) {

    $message='';
    switch ($eventData['eventType']){
        case 0:
            $message="Appointment created";
            break;
        case 1:
            $message="Appointment deleted/cancelled";
            break;
        case 2:
            $message="Appointment updated";
            break;
        case 3:
            // ignore talk integration
            return;
        case 4:
            $message="Appointment reminder";
            break;
        default:
            $logger->error('Unknown eventType: '.$eventData['eventType']);
            return;
    }

    // we use $logger->error instead of $logger->info for this test
    $logger->error("Test Log Message: ".$message .
        ", DateTime: ".$eventData['dateTime']->format('c').
        ", Name: ".$eventData['attendeeName'].
        ", Email: ".$eventData['attendeeEmail'].
        ", Tel: ".$eventData['attendeeTel']
    );

////     Make simple test call to NC Apps API (Must have PHP cURL)
//    $ch = curl_init();
//
//    curl_setopt($ch, CURLOPT_URL, "https://apps.nextcloud.com/apps/appointments/description");
//    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // always set timeout
//    curl_setopt($ch, CURLOPT_HEADER, false);
//    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//
//    $result = curl_exec($ch);
//    if ($result === false) {
//        $logger->error("Error: curl_exec() failed");
//    } else {
//        $logger->info("Result: " . $result);
//    }
//
//    curl_close($ch);
}
