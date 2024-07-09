<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
require_once(__DIR__ . '/flutter-base.php');

class PointsHelperAPI extends FlutterBaseController
{
    protected $namespace = 'wc/v3';

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/get-customer-points', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_customer_points'),
            'permission_callback' => array($this, 'checkApiPermission')
        ));

        register_rest_route($this->namespace, '/apply-customer-points', array(
            'methods' => 'POST',
            'callback' => array($this, 'apply_customer_points'),
            'permission_callback' => array($this, 'checkApiPermission')
        ));

        register_rest_route($this->namespace, '/get-points-history', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_points_history'),
            'permission_callback' => array($this, 'checkApiPermission')
        ));
    }

    public function get_customer_points(WP_REST_Request $request)
    {
        try {
            // Get data from the request
            $data = $request->get_json_params();
            $userId = $data['user_id'];

            /** Get the data */
            global $wpdb;
            $meta_key = 'lws_wre_points_%';

            $meta_value = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value 
                     FROM {$wpdb->usermeta} 
                     WHERE user_id = %d 
                     AND meta_key LIKE %s",
                    (int) $userId,
                    $meta_key
                )
            );

            $db_points = $meta_value !== null ? $meta_value : 0;

            // Return points with 200 as json
            return new WP_REST_Response($db_points, 200);
        } catch (Exception $e) {
            // Handle any exceptions
            return $this->sendError('points_error', $e->getMessage(), 400);
        }
    }

    public function apply_customer_points(WP_REST_Request $request)
    {
        try {
            // Get data from the request
            $data = $request->get_json_params();
            $userId = $data['user_id'];

            if (isset($data['points_to_use'])) {
                $points_to_use = intval($data['points_to_use']);

                $user_points = get_user_meta($userId, 'customer_points', true);

                if ($points_to_use <= $user_points && $points_to_use > 0) {
                    $coupon_code = 'POINT-' . uniqid();

                    // Create a coupon array
                    $coupon = array(
                        'post_title' => $coupon_code,
                        'post_content' => '',
                        'post_status' => 'publish',
                        'post_author' => 1,
                        'post_type' => 'shop_coupon'
                    );

                    // Insert the coupon into the database
                    $new_coupon_id = wp_insert_post($coupon);

                    // Load the coupon by ID
                    $new_coupon = new WC_Coupon($new_coupon_id);

                    // Set coupon data
                    $new_coupon->set_discount_type('fixed_cart');
                    $new_coupon->set_amount($points_to_use);
                    $new_coupon->set_individual_use(true);
                    $new_coupon->set_usage_limit(1);

                    // Save the coupon
                    $new_coupon->save();

                    // Set metadata for the coupon using update_post_meta()
                    update_post_meta($new_coupon_id, 'is_point_redemption_coupon', 'yes');

                    // Save the points used in a user meta field
                    update_user_meta($userId, 'points_used_for_coupon', $points_to_use);

                    // Return coupon code and amount with 200 as json
                    return new WP_REST_Response(
                        array(
                            'coupon_code' => $coupon_code,
                            'coupon_amount' => $points_to_use,
                        ),
                        200
                    );
                } else {
                    // Return error if points are not enough
                    return $this->sendError('points_error', 'Not enough points', 400);
                }
            } else {
                return $this->sendError('points_error', 'Points not set', 400);
            }
        } catch (Exception $e) {
            // Handle any exceptions
            return $this->sendError('points_error', $e->getMessage(), 400);
        }
    }

    public function get_points_history(WP_REST_Request $request)
    {
        try {
            // Get data from the request
            $data = $request->get_json_params();
            $userId = $data['user_id'];

            $current_points = get_user_meta($userId, 'customer_points', true);

            global $wpdb;
            $table_name = $wpdb->prefix . 'custom_points_table';

            // Get points data for the current user
            $points_data = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT points_moved, new_total, commentar, mvt_date, given_by FROM $table_name WHERE used_id = %d ORDER BY mvt_date DESC, id DESC",
                    $userId
                )
            );

            // If string is serialized can be deserialized
            foreach ($points_data as $key => $row) {
                if (is_serialized($row->commentar)) {
                    $points_data[$key]->commentar = maybe_unserialize($row->commentar);
                    if (is_array($points_data[$key]->commentar) && count($points_data[$key]->commentar) >= 3) {
                        $points_data[$key]->commentar = sprintf($points_data[$key]->commentar[0], $points_data[$key]->commentar[1], $points_data[$key]->commentar[2]);
                    }
                }
            }

            if ($points_data) {
                // Send points data, current points with 200 as json
                return new WP_REST_Response(
                    array(
                        'current_points' => $current_points,
                        'points_history' => $points_data,
                    ),
                    200
                );
            } else {
                // Return current points 0 with 200 and an history data
                $points_data = array(
                    array(
                        'points_moved' => '0',
                        'new_total' => '0',
                        'commentar' => 'No Point Available',
                        'mvt_date' => '2024-01-14 21:23:36',
                        'given_by' => '863',
                    ),
                );

                return new WP_REST_Response(
                    array(
                        'current_points' => '0',
                        'points_history' => $points_data,
                    ),
                    200
                );
            }
        } catch (Exception $e) {
            // Handle any exceptions
            $points_data = array(
                array(
                    'points_moved' => '0',
                    'new_total' => '0',
                    'commentar' => 'No Point Available',
                    'mvt_date' => '2024-01-14 21:23:36',
                    'given_by' => '863',
                ),
            );
            return new WP_REST_Response(
                array(
                    'current_points' => '0',
                    'points_history' => $points_data,
                ),
                200
            );
        }
    }
}

// Instantiate the child class to ensure the routes are registered
new PointsHelperAPI();



// Add Customer Points to customer REST API response
add_filter('woocommerce_rest_prepare_customer', 'add_points_rest_api_response', 10, 3);
function add_points_rest_api_response($response, $user_data, $request)
{
    // Customize response data
    // $response->data["custom_data_points"] = 3;

    // Add customer points from meta
    $userId = $user_data->ID;
    $current_points = get_user_meta($userId, 'customer_points', true);
    // If empty or null, return 0; otherwise, return as int
    $current_points = $current_points !== '' && $current_points !== null ? (int) $current_points : 0;

    $response->data["shinjuku_customer_points"] = $current_points;
    return $response;
}
