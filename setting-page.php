<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Add a menu item in the WordPress dashboard
function quick_store_api_add_menu_item()
{
    add_menu_page(
        'Quick Store API Settings',
        'Quick Store API',
        'manage_options',
        'quick-store-api-settings',
        'quick_store_api_settings_page',
        'dashicons-rest-api',
        6
    );
}
add_action('admin_menu', 'quick_store_api_add_menu_item');

// Register and sanitize settings
function quick_store_api_register_settings()
{
    register_setting('quick_store_api_settings', 'quick_store_api_option', 'sanitize_callback');
    register_setting('quick_store_api_settings', 'quick_store_api_status');

    add_settings_section(
        'quick_store_api_section',
        'General Settings',
        'quick_store_api_section_callback',
        'quick-store-api-settings'
    );

    add_settings_field(
        'quick_store_api_domain',
        '',
        'quick_store_api_domain_callback',
        'quick-store-api-settings',
        'quick_store_api_section'
    );

    add_settings_field(
        'quick_store_api_license_key',
        'License Key:',
        'quick_store_api_license_key_callback',
        'quick-store-api-settings',
        'quick_store_api_section'
    );

    add_settings_field(
        'quick_store_api_status',
        'License Status:',
        'quick_store_api_status_callback',
        'quick-store-api-settings',
        'quick_store_api_section'
    );
}
add_action('admin_init', 'quick_store_api_register_settings');

// Sanitize callback function
function sanitize_callback($input)
{
    return array_map('sanitize_text_field', $input);
}

// Display the form
function quick_store_api_settings_page()
{
?>
    <div class="wrap">
        <h1>Quick Store API Settings</h1>
        <?php settings_errors(); // Display the settings errors 
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="save_quick_store_api_options">
            <?php
            // Add nonce field for security
            wp_nonce_field('quick_store_api_nonce_action', 'quick_store_api_nonce_field');

            do_settings_sections('quick-store-api-settings');
            submit_button('Save');
            ?>
        </form>
        <div id="api-response-message" style="display:none;background: #ffe8c6;padding: 20px;border-left: 6px solid #ffb74c;font-size: 16px;font-weight: 500;"></div> <!-- Placeholder for the message -->
    </div>
    <script>
        jQuery(document).ready(function($) {
            var responseMessage = '<?php echo isset($_GET['api_response_message']) ? esc_js($_GET['api_response_message']) : ''; ?>';
            if (responseMessage) {
                $('#api-response-message').text(decodeURIComponent(responseMessage)).show();
            }
        });
    </script>
<?php
}


function quick_store_api_section_callback()
{
    echo '<p>Enter your API settings below:</p>';
}

function quick_store_api_domain_callback()
{
    $domain = esc_attr($_SERVER['HTTP_HOST']);
    echo '<input type="hidden" name="quick_store_api_option[domain]" id="domain" value="' . esc_attr($domain) . '" required readonly>';
}

function quick_store_api_license_key_callback()
{
    $option = get_option('quick_store_api_option');
    $licenseKey = isset($option['license_key']) ? esc_attr($option['license_key']) : '';
    echo '<input type="text" name="quick_store_api_option[license_key]" id="license_key" value="' . esc_attr($licenseKey) . '" class="regular-text" required>';
}

function quick_store_api_status_callback()
{
    $status = get_option('quick_store_api_status', '0'); // Default to 0 if not set
    $status_text = $status == '1' ? 'Valid' : 'Invalid';
    $status_color = $status == '1' ? 'green' : 'red';
    echo '<input type="hidden" name="quick_store_api_status" id="status" value="' . esc_attr($status_text) . '">';
    echo '<p style="color:' . esc_attr($status_color) . ';"><b>' . esc_html($status_text) . ' purchase code.</b></p>';
}

// Handle form submission securely
add_action('admin_post_save_quick_store_api_options', 'handle_quick_store_api_form_submission');

function handle_quick_store_api_form_submission()
{

    // Check nonce field
    if (!isset($_POST['quick_store_api_nonce_field']) || !wp_verify_nonce($_POST['quick_store_api_nonce_field'], 'quick_store_api_nonce_action')) {
        wp_die(__('Nonce verification failed', 'quick-store-api'));
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page', 'quick-store-api'));
    }

    if (!isset($_POST['quick_store_api_option']['domain']) || !isset($_POST['quick_store_api_option']['license_key'])) {
        wp_die(__('Required fields missing', 'quick-store-api'));
    }

    $domain = sanitize_text_field($_POST['quick_store_api_option']['domain']);
    $licenseKey = sanitize_text_field($_POST['quick_store_api_option']['license_key']);

    // Retrieve existing options and update with new values
    $option = get_option('quick_store_api_option');
    if (!is_array($option)) {
        $option = array();
    }
    $option['domain'] = $domain;
    $option['license_key'] = $licenseKey;

    // Save the updated options
    update_option('quick_store_api_option', $option);

    // Construct the full URL with all query parameters
    $url = add_query_arg(
        array(
            'domain' => $domain,
            'license_key' => $licenseKey
        ),
        'https://pims.goldlavender.jp/verify-quick-store-license-key/'
    );

    $args = array(
        'timeout' => 30,
    );

    $response = wp_remote_get($url, $args);
    error_log('API Response: ' . print_r($response, true));

    $response_message = '';

    if (is_wp_error($response)) {
        $response_message = 'API request error: ' . $response->get_error_message();
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        $response_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';

        if ($response_code === 200) {
            // Save the status as 1
            update_option('quick_store_api_status', '1');
        } else {
            // Save the status as 0
            update_option('quick_store_api_status', '0');
        }
    }

    // Redirect to the settings page with the response message
    wp_redirect(add_query_arg('api_response_message', urlencode($response_message), admin_url('admin.php?page=quick-store-api-settings')));
    exit;
}
