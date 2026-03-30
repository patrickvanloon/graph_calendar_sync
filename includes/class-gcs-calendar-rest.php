<?php
if (!defined('ABSPATH')) exit;

class GCS_Calendar_REST {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('graph-calendar/v1', '/events', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'get_events'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'  => 'POST',
                'callback' => [$this, 'create_event'],
                'permission_callback' => '__return_true', // visitors can request
            ],
        ]);

        register_rest_route('graph-calendar/v1', '/events/(?P<id>[^/]+)', [
            [
                'methods'  => 'PATCH',
                'callback' => [$this, 'update_event'],
                'permission_callback' => [$this, 'can_edit_calendar'],
            ],
            [
                'methods'  => 'DELETE',
                'callback' => [$this, 'delete_event'],
                'permission_callback' => [$this, 'can_edit_calendar'],
            ],
        ]);

        register_rest_route('graph-calendar/v1', '/calendars', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_calendars'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function can_edit_calendar() {
        return current_user_can('edit_posts');
    }

    public function get_calendars(WP_REST_Request $request) {
        $client = new GCS_Graph_Client();
        $cals   = $client->get_calendars_config();
        return rest_ensure_response($cals);
    }

    public function get_events(WP_REST_Request $request) {
        $start = $request->get_param('start');
        $end   = $request->get_param('end');
        $user  = $request->get_param('user'); // UPN

        if (!$start || !$end) {
            return new WP_Error('missing_params', 'start and end required', ['status' => 400]);
        }

        $client = new GCS_Graph_Client();
        $data   = $client->get_events($start, $end, $user);

        if (is_wp_error($data)) return $data;

        $events = [];
        if (!empty($data['value'])) {
            foreach ($data['value'] as $ev) {
                $events[] = [
                    'id'        => $ev['id'],
                    'title'     => $ev['subject'],
                    'start'     => $ev['start']['dateTime'],
                    'end'       => $ev['end']['dateTime'],
                    'category'  => $ev['categories'][0] ?? null,
                    'location'  => $ev['location']['displayName'] ?? '',
                    'preview'   => $ev['bodyPreview'] ?? '',
                ];
            }
        }

        return rest_ensure_response($events);
    }

    public function create_event(WP_REST_Request $request) {
        $params = $request->get_json_params();

        $client = new GCS_Graph_Client();
        $user   = !empty($params['calendarUser']) ? $params['calendarUser'] : null;

        $event = [
            'subject' => $params['description'] ?? 'Meeting request',
            'start'   => [
                'dateTime' => $params['start'],
                'timeZone' => 'Europe/Rome',
            ],
            'end'     => [
                'dateTime' => $params['end'],
                'timeZone' => 'Europe/Rome',
            ],
            'location' => [
                'displayName' => $params['location'] ?? '',
            ],
            'body' => [
                'contentType' => 'HTML',
                'content'     => 'Meeting request from website.<br><br>' .
                                 'Name: ' . esc_html($params['name'] ?? '') . '<br>' .
                                 'Email: ' . esc_html($params['email'] ?? '') . '<br>' .
                                 'Description: ' . nl2br(esc_html($params['description'] ?? '')),
            ],
            'attendees' => [
                [
                    'emailAddress' => [
                        'address' => $params['email'] ?? '',
                        'name'    => $params['name'] ?? 'Guest',
                    ],
                    'type' => 'required',
                ]
            ]
        ];

        $res = $client->create_event($event, $user);
        if (is_wp_error($res)) return $res;

        // Send confirmation email via Graph (option C)
        if (!empty($params['email'])) {
            $message = [
                'subject' => 'Your meeting request at Villa Centena',
                'body' => [
                    'contentType' => 'HTML',
                    'content'     =>
                        'Thank you for your meeting request.<br><br>' .
                        'We received the following details:<br>' .
                        'Start: ' . esc_html($params['start']) . '<br>' .
                        'End: ' . esc_html($params['end']) . '<br>' .
                        'Description: ' . nl2br(esc_html($params['description'] ?? '')) . '<br><br>' .
                        'We will confirm as soon as possible.'
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $params['email'],
                            'name'    => $params['name'] ?? 'Guest',
                        ]
                    ]
                ]
            ];
            $client->send_mail($message, $user);
        }

        return rest_ensure_response($res);
    }

    public function update_event(WP_REST_Request $request) {
        $id     = $request['id'];
        $params = $request->get_json_params();
        $user   = $params['calendarUser'] ?? null;

        $event = [];
        if (!empty($params['title'])) {
            $event['subject'] = $params['title'];
        }
        if (!empty($params['start'])) {
            $event['start'] = [
                'dateTime' => $params['start'],
                'timeZone' => 'Europe/Rome',
            ];
        }
        if (!empty($params['end'])) {
            $event['end'] = [
                'dateTime' => $params['end'],
                'timeZone' => 'Europe/Rome',
            ];
        }

        $client = new GCS_Graph_Client();
        $res    = $client->update_event($id, $event, $user);
        if (is_wp_error($res)) return $res;

        return rest_ensure_response($res);
    }

    public function delete_event(WP_REST_Request $request) {
        $id   = $request['id'];
        $user = $request->get_param('calendarUser');

        $client = new GCS_Graph_Client();
        $res    = $client->delete_event($id, $user);
        if (is_wp_error($res)) return $res;

        return rest_ensure_response(['deleted' => true]);
    }
}
