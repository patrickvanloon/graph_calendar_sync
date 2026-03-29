<?php
if (!defined('ABSPATH')) exit;

class GCS_Graph_Client {

    private $tenant_id;
    private $client_id;
    private $client_secret;
    private $default_user;

    public function __construct() {
        $this->tenant_id     = get_option('gcs_tenant_id');
        $this->client_id     = get_option('gcs_client_id');
        $this->client_secret = get_option('gcs_client_secret');
        $this->default_user  = get_option('gcs_default_calendar_user');
    }

    private function get_access_token() {
        $cache_key = 'gcs_graph_token';
        $token = get_transient($cache_key);
        if ($token) return $token;

        $url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";

        $response = wp_remote_post($url, [
            'body' => [
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'scope'         => 'https://graph.microsoft.com/.default',
                'grant_type'    => 'client_credentials',
            ]
        ]);

        if (is_wp_error($response)) return null;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) return null;

        set_transient($cache_key, $body['access_token'], $body['expires_in'] - 60);
        return $body['access_token'];
    }

    private function request($method, $endpoint, $body = null, $user = null) {
        $token = $this->get_access_token();
        if (!$token) return new WP_Error('no_token', 'Could not obtain access token');

        $user = $user ?: $this->default_user;
        $url  = "https://graph.microsoft.com/v1.0/users/{$user}{$endpoint}";

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json'
            ]
        ];
        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) return $response;

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            return new WP_Error('graph_error', 'Graph error', ['code' => $code, 'data' => $data]);
        }

        return $data;
    }

    public function get_events($start, $end, $user = null) {
        $endpoint = "/calendarView?startDateTime={$start}&endDateTime={$end}&\$top=200&\$select=subject,start,end,bodyPreview,location,organizer,attendees,categories,id";
        return $this->request('GET', $endpoint, null, $user);
    }

    public function create_event($event, $user = null) {
        return $this->request('POST', '/events', $event, $user);
    }

    public function update_event($id, $event, $user = null) {
        return $this->request('PATCH', "/events/{$id}", $event, $user);
    }

    public function delete_event($id, $user = null) {
        return $this->request('DELETE', "/events/{$id}", null, $user);
    }

    public function send_mail($message, $user = null) {
        $token = $this->get_access_token();
        if (!$token) return new WP_Error('no_token', 'Could not obtain access token');

        $user = $user ?: $this->default_user;
        $url  = "https://graph.microsoft.com/v1.0/users/{$user}/sendMail";

        $args = [
            'method'  => 'POST',
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json'
            ],
            'body' => wp_json_encode(['message' => $message, 'saveToSentItems' => true])
        ];

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) return $response;

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            return new WP_Error('graph_error', 'Graph sendMail error', ['code' => $code, 'data' => $data]);
        }

        return true;
    }

    public function get_calendars_config() {
        $json = get_option('gcs_calendars', '[]');
        $arr  = json_decode($json, true);
        if (!is_array($arr)) $arr = [];
        if ($this->default_user) {
            array_unshift($arr, [
                'label' => 'Default',
                'user'  => $this->default_user,
            ]);
        }
        return $arr;
    }
	
	public function send_meeting_invite($toEmail, $subject, $body, $start, $end) {

		require_once plugin_dir_path(__FILE__) . 'class-gcs-ics-generator.php';

		$ics = GCS_ICS_Generator::generate($subject, $body, $start, $end);

		$message = [
			"message" => [
				"subject" => $subject,
				"body" => [
					"contentType" => "HTML",
					"content" => $body
				],
				"toRecipients" => [
					[
						"emailAddress" => [
							"address" => $toEmail
						]
					]
				],
				"attachments" => [
					[
						"@odata.type" => "#microsoft.graph.fileAttachment",
						"name" => "invite.ics",
						"contentBytes" => base64_encode($ics)
					]
				]
			],
			"saveToSentItems" => true
		];

		return $this->client
			->createRequest("POST", "/users/{$this->sender}/sendMail")
			->attachBody($message)
			->execute();
	}
	
	
}
