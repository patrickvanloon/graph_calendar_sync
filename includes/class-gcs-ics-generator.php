<?php

class Graph_Calendar_ICS_Generator {

    public static function generate($subject, $body, $start, $end) {
        $uid = uniqid();
        $dtStart = gmdate("Ymd\THis\Z", strtotime($start));
        $dtEnd   = gmdate("Ymd\THis\Z", strtotime($end));
        $dtStamp = gmdate("Ymd\THis\Z");

        return "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Graph Calendar Sync//EN
METHOD:REQUEST
BEGIN:VEVENT
UID:$uid
DTSTAMP:$dtStamp
DTSTART:$dtStart
DTEND:$dtEnd
SUMMARY:$subject
DESCRIPTION:$body
END:VEVENT
END:VCALENDAR";
    }
}
