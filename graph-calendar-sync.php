<?php
/**
 * Plugin Name: Graph Calendar Sync
 * Description: Sync Outlook/Exchange Online calendars via Microsoft Graph with FullCalendar frontend and role-based editing.
 * Version: 2026.03.02	
 * Author: Patrick van Loon
 * Text Domain: graph-calendar-sync
 */

if (!defined('ABSPATH')) exit;

define('GCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GCS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GCS_PLUGIN_DIR . 'includes/class-gcs-graph-client.php';
require_once GCS_PLUGIN_DIR . 'includes/class-gcs-calendar-renderer.php';
require_once GCS_PLUGIN_DIR . 'includes/class-gcs-calendar-rest.php';

class GCS_Plugin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('graph_calendar_sync', [$this, 'render_calendar_shortcode']);

        new GCS_Calendar_REST();
    }

    public function add_settings_page() {
        add_options_page(
            'Graph Calendar Sync',
            'Graph Calendar Sync',
            'manage_options',
            'gcs-settings',
            [$this, 'settings_page_html']
        );
    }

    public function register_settings() {
        register_setting('gcs_settings', 'gcs_tenant_id');
        register_setting('gcs_settings', 'gcs_client_id');
        register_setting('gcs_settings', 'gcs_client_secret');
        register_setting('gcs_settings', 'gcs_default_calendar_user'); // e.g. info@villacentena.com
        register_setting('gcs_settings', 'gcs_calendars'); // JSON: [{label, user}]
    }

    public function settings_page_html() {
        if (!current_user_can('manage_options')) return;

        $calendars_json = get_option('gcs_calendars', '[]');
        ?>
        <div class="wrap">
            <h1>Graph Calendar Sync Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('gcs_settings'); ?>
                <?php do_settings_sections('gcs_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Tenant ID</th>
                        <td><input type="text" name="gcs_tenant_id" value="<?php echo esc_attr(get_option('gcs_tenant_id')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Client ID</th>
                        <td><input type="text" name="gcs_client_id" value="<?php echo esc_attr(get_option('gcs_client_id')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Client Secret</th>
                        <td><input type="password" name="gcs_client_secret" value="<?php echo esc_attr(get_option('gcs_client_secret')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Default Calendar User (UPN)</th>
                        <td><input type="text" name="gcs_default_calendar_user" value="<?php echo esc_attr(get_option('gcs_default_calendar_user')); ?>" class="regular-text" placeholder="info@villacentena.com"></td>
                    </tr>
                    <tr>
                        <th scope="row">Calendars (JSON)</th>
                        <td>
                            <textarea name="gcs_calendars" rows="6" class="large-text code" placeholder='[{"label":"Main","user":"info@villacentena.com"}]'><?php echo esc_textarea($calendars_json); ?></textarea>
                            <p class="description">List of calendars as JSON: [{"label":"Main","user":"info@villacentena.com"}, {"label":"Room 1","user":"room1@villacentena.com"}]</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
            [],
            '6.1.10'
        );

        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
            [],
            '6.1.10',
            true
        );

        wp_enqueue_script(
            'gcs-calendar',
            GCS_PLUGIN_URL . 'assets/js/calendar.js',
            ['fullcalendar', 'jquery'],
            '0.1.0',
            true
        );

        wp_enqueue_style(
            'gcs-calendar',
            GCS_PLUGIN_URL . 'assets/css/calendar.css',
            [],
            '0.1.0'
        );

        $user_can_edit = current_user_can('edit_posts');

        wp_localize_script('gcs-calendar', 'GCS', [
            'restUrl'     => esc_url_raw(rest_url('graph-calendar/v1/')),
            'nonce'       => wp_create_nonce('wp_rest'),
            'userCanEdit' => $user_can_edit,
        ]);
    }

    public function render_calendar_shortcode($atts) {
        $renderer = new GCS_Calendar_Renderer();
        return $renderer->render();
    }
}

new GCS_Plugin();
