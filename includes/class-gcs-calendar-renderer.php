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
                <h3>Request a Meeting</h3>
                <label>Name</label>
                <input type="text" id="gcs-name">
                <label>Email</label>
                <input type="email" id="gcs-email">
                <label>Start</label>
                <input type="datetime-local" id="gcs-start">
                <label>End</label>
                <input type="datetime-local" id="gcs-end">
                <label>Description</label>
                <textarea id="gcs-description"></textarea>
                <button id="gcs-send-request">Send Request</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
