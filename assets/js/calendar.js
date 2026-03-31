jQuery(function($) {

    let currentCalendarUser = null;
	
	function combineDateTime(dateField, timeField) {
    const date = document.getElementById(dateField).value;
    const time = document.getElementById(timeField).value;

    if (!date || !time) {
        return null; // invalid
    }

    return `${date}T${time}`;
    }	
	
	function isoLocal(dt) {
    return new Date(dt).toISOString();
    }
	
	document.getElementById('gcs-send-request').addEventListener('click', function () {

    const startDateTime = combineDateTime('gcs-start-date', 'gcs-start-time');
    const endDateTime   = combineDateTime('gcs-end-date', 'gcs-end-time');

    if (!startDateTime || !endDateTime) {
        alert("Please fill both date and time for start and end.");
        return;
    }

    // Populate the hidden datetime-local fields
    document.getElementById('gcs-start').value = startDateTime;
    document.getElementById('gcs-end').value   = endDateTime;
	
	 // Now safe to call isoLocal()
    const startIso = isoLocal(new Date(startDateTime));
    const endIso   = isoLocal(new Date(endDateTime));

    console.log("Start ISO:", startIso);
    console.log("End ISO:", endIso);

    // Now you can send the request using gcs-start and gcs-end
    const payload = {
        name: document.getElementById('gcs-name').value,
        email: document.getElementById('gcs-email').value,
        start: document.getElementById('gcs-start').value,
        end: document.getElementById('gcs-end').value,
        description: document.getElementById('gcs-description').value
    };

    console.log("Sending payload:", payload);
   

    function loadCalendars(callback) {
        $.ajax({
            url: GCS.restUrl + 'calendars',
            method: 'GET'
        }).done(function(cals) {
            const $sel = $('#gcs-calendar-select');
            $sel.empty();
            cals.forEach((c, idx) => {
                const opt = $('<option></option>')
                    .val(c.user)
                    .text(c.label + ' (' + c.user + ')');
                if (idx === 0) {
                    opt.prop('selected', true);
                    currentCalendarUser = c.user;
                }
                $sel.append(opt);
            });
            if (callback) callback();
        });
    }

    const calendarEl = document.getElementById('gcs-calendar');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        editable: GCS.userCanEdit || false,
        selectable: true,

        eventSources: [
            {
                events: function(fetchInfo, success, failure) {
                    if (!currentCalendarUser) {
                        success([]);
                        return;
                    }
                    $.ajax({
                        url: GCS.restUrl + 'events',
                        method: 'GET',
                        data: {
                            start: fetchInfo.start.toISOString(),
                            end: fetchInfo.end.toISOString(),
                            user: currentCalendarUser
                        }
                    }).done(function(events) {
                        events.forEach(ev => {
                            if (ev.category) {
                                ev.backgroundColor = gcsCategoryColor(ev.category);
                                ev.borderColor = ev.backgroundColor;
                            }
                        });
                        success(events);
                    }).fail(failure);
                }
            }
        ],

        select: function(info) {
            $('#gcs_start').val(info.startStr.substring(0, 16));
            $('#gcs_end').val(info.endStr.substring(0, 16));
        },

        eventDrop: function(info) {
            updateEvent(info.event);
        },

        eventResize: function(info) {
            updateEvent(info.event);
        },

        eventClick: function(info) {
            if (!GCS.userCanEdit) return;
            if (confirm("Delete this event?")) {
                deleteEvent(info.event);
            }
        }
    });

    calendar.render();

    $('#gcs-calendar-select').on('change', function() {
        currentCalendarUser = $(this).val();
        calendar.refetchEvents();
    });

    $('#gcs-send-request').on('click', function() {
        const payload = {
            name: $('#gcs-name').val(),
            email: $('#gcs-email').val(),
            start: $('#startIso').val(),
            end: $('#endIso').val(),
            description: $('#gcs-description').val(),
            calendarUser: currentCalendarUser
        };

        $.ajax({
            url: GCS.restUrl + 'events',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload)
        }).done(function() {
            alert('Meeting request sent.');
            calendar.refetchEvents();
        }).fail(function(err) {
            alert('Error sending request');
            console.log(err);
        });
    });

    function updateEvent(event) {
        $.ajax({
            url: GCS.restUrl + 'events/' + event.id,
            method: 'PATCH',
            contentType: 'application/json',
            data: JSON.stringify({
                start: event.start.toISOString(),
                end: event.end ? event.end.toISOString() : event.start.toISOString(),
                title: event.title,
                calendarUser: currentCalendarUser
            }),
            beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', GCS.nonce)
        }).fail(err => console.log(err));
    }

    function deleteEvent(event) {
        $.ajax({
            url: GCS.restUrl + 'events/' + event.id + '?calendarUser=' + encodeURIComponent(currentCalendarUser),
            method: 'DELETE',
            beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', GCS.nonce)
        }).done(() => {
            event.remove();
        }).fail(err => console.log(err));
    }

    function gcsCategoryColor(cat) {
        const map = {
            "Business": "#0d6efd",
            "Personal": "#dc3545",
            "Travel": "#20c997",
            "Clients": "#6610f2",
            "Family": "#fd7e14"
        };
        return map[cat] || "#6c757d";
    }

    loadCalendars(function() {
        calendar.refetchEvents();
    });
});
