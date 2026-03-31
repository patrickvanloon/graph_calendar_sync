<?php
if (!defined('ABSPATH')) exit;

class GCS_Calendar_Renderer {

    public function render() {
        ob_start();
        ?>
        <div class="gcs-calendar-wrapper">
            <div class="gcs-calendar-header">
                <label>Calendar:</label>
                <select id="gcs-calendar-select"></select>
            </div>

            <div id="gcs-calendar"></div>

            <div class="gcs-meeting-form">
                <h3>Make a booking request</h3>
                <label>Name</label>
                <input type="text" id="gcs-name">
                <label>Email</label>
                <input type="email" id="gcs-email">
                <label>Start Date</label>
                <input type="date" id="gcs-start-date">
                <label>Start Time</label>
				<input type="time" id="gcs-start-time" value="00:00" required>
				<label>End Date</label>
                <input type="date" id="gcs-end_date">
                <label>End Time</label>
				<input type="time" id="gcs-end-time" value="23:59" required>
				<label>Description</label>
                <textarea id="gcs-description"></textarea>
                <button id="gcs-send-request">Send Request</button>
				<input type="datetime-local" id="gcs-start" style="display:none;">
				<input type="datetime-local" id="gcs-end" style="display:none;">
            </div>
		
		
		</div>
		
        		
		<?php
        return ob_get_clean();
    }
}