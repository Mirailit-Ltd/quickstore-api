<?php

/*
Plugin Name: Quick Store API
Description: The Quick Store API plugin is designed to provide custom API endpoints for the Quick Store app, making it easier to build and integrate your e-commerce mobile app with your WordPress-based online store. This plugin facilitates seamless communication between your store and mobile app, ensuring efficient data exchange and improved user experience.
Version: 1.0.0
Author: Mirailit Limited
Author URI: https://mirailit.com/
License: GPLv2 or later
Requires PHP: 5.3
*/

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
    echo 'Hi there! I\'m just a plugin, not much I can do when called directly.';
    exit;
}

// Include the files
require_once plugin_dir_path(__FILE__) . 'points-helper.php';
require_once plugin_dir_path(__FILE__) . 'flutter-base.php';
require_once plugin_dir_path(__FILE__) . 'flutter-user.php';
require_once plugin_dir_path(__FILE__) . 'flutter-woo.php';
require_once plugin_dir_path(__FILE__) . '/helpers/apple-sign-in-helper.php';
require_once plugin_dir_path(__FILE__) . 'setting-page.php';


// Show Order device info in admin order page
add_action('woocommerce_admin_order_data_after_order_details', 'display_user_agent_and_custom_meta');

function display_user_agent_and_custom_meta($order)
{

    // Get order customer_user_agent
    $user_agent = $order->get_customer_user_agent();

    $api_created_via = $order->get_created_via();
    $order_device_info = get_post_meta($order->get_id(), 'fa_order_device_info', true);
    if ($order_device_info !== '' && $order_device_info !== null && array_key_exists('name', $order_device_info) && array_key_exists('systemName', $order_device_info) && array_key_exists('systemVersion', $order_device_info)) {
        $order_device_info_str = $order_device_info['name'] . '/' . $order_device_info['systemName'] . '/' . $order_device_info['systemVersion'];
    } else {
        $order_device_info_str = 'N/A';
    }

    echo '<div class="ordered_via_info">';
?>
    <div>
        <div id="agent-device" title="User Agent & Device Information">
            <h4 style="margin:5px 0;">User Agent</h4>
            <?php
            echo '<p style="margin: 5px 0 15px 0;">';
            if (!empty($user_agent)) {
                echo esc_html($user_agent);
            } else {
                echo 'No user agent for this order.';
            }
            echo '</p>';
            ?>

            <h4 style="margin:5px 0;">Device Information</h4>
            <?php
            echo '<p style="margin: 5px 0;">';

            if (!empty($order_device_info) && is_array($order_device_info)) {
                $display_keys = array(
                    'brand',
                    'device',
                    'display',
                    'manufacturer',
                    'model',
                    'displaySizeInches',
                    'serialNumber',
                    'name',
                    'systemName',
                    'systemVersion',
                    'model',
                    'version.sdkInt',
                    'version.release',
                    'utsname.machine:',
                );

                foreach ($display_keys as $key) {
                    if (isset($order_device_info[$key])) {
                        echo '<strong>' . ucfirst($key) . ':</strong> ' . esc_html($order_device_info[$key]) . '<br>';
                    }
                }
            } else {
                echo 'No device information for this order.';
            }

            echo '</p>';
            ?>
        </div>
        <div class="agent-device-btn">
            <a id="agent-device-btn">User Agent & Device Info</a>
        </div>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $("#agent-device").dialog({
                autoOpen: false,
                hide: "puff",
                show: "slide",
                width: 600,
                height: 400
            });

            $("#agent-device-btn").on("click", function() {
                $("#agent-device").dialog("open");
            });
        });
    </script>

<?php

    echo '<style>.rest-api-badge { 
        background: #e91e63;
        padding: 2px 8px 4px 8px;
        color: #FFFFFF;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500; 
    }
    .wordpress-badge { 
        background: #4b71b1;
        padding: 2px 8px 4px 8px;
        color: #FFFFFF;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500; 
    }
    .ui-widget-header,.ui-state-default, ui-button {
        background:#2271b1;
        border: 1px solid #2271b1;
        color: #FFFFFF;
        font-weight: bold;
     }
     .agent-device-btn a{
        margin: 15px 0 10px 0;
        background: #247bc1;
        padding: 4px 8px 4px 8px;
        color: #FFFFFF;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        border: 0;
        cursor: pointer;
        display: inline-block;
     }
     </style>';

    if ($api_created_via === 'rest-api') {
        echo '<span class="rest-api-badge">App/API</span>';
    } else {
        echo '<span class="wordpress-badge">Web/Old App</span>';
    }


    echo '</div>';
}

// Guest Login
add_filter('user_has_cap', 'quick_store_order_pay_without_login', 9999, 3);

function quick_store_order_pay_without_login($allcaps, $caps, $args)
{
    if (isset($caps[0], $_GET['key'])) {
        if ($caps[0] == 'pay_for_order') {
            $order_id = isset($args[2]) ? $args[2] : null;
            $order = wc_get_order($order_id);
            if ($order) {
                $allcaps['pay_for_order'] = true;
            }
        }
    }
    return $allcaps;
}

add_filter('woocommerce_order_email_verification_required', '__return_false', 9999);



// Enqueue scripts and styles
add_action('admin_enqueue_scripts', 'enqueue_custom_scripts');

function enqueue_custom_scripts()
{
    wp_enqueue_style('wp-jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
    wp_enqueue_script('wp-jquery-ui', 'https://code.jquery.com/ui/1.12.1/jquery-ui.js', array('jquery'), null, true);
}
