<?php
/**
 * Plugin Name: Mailchimp User Registration Integration
 * Plugin URI: https://yoursite.com
 * Description: Automatically sends user registration details to Mailchimp with 'User Registration' tag during the 5-second redirect window.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: mailchimp-user-reg
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MailchimpUserRegistration {
    
    private $mailchimp_api_key;
    private $mailchimp_list_id;
    private $mailchimp_server_prefix;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        
        // Hook into User Registration plugin
        add_action('user_registration_after_register_user_action', array($this, 'send_to_mailchimp'), 10, 3);
        
        // Alternative hooks in case the above doesn't work
        add_action('wp_ajax_nopriv_mailchimp_user_reg', array($this, 'ajax_send_to_mailchimp'));
        add_action('wp_ajax_mailchimp_user_reg', array($this, 'ajax_send_to_mailchimp'));
        add_action('wp_footer', array($this, 'add_registration_script'));
    }
    
    public function init() {
        $this->mailchimp_api_key = get_option('mur_mailchimp_api_key', '');
        $this->mailchimp_list_id = get_option('mur_mailchimp_list_id', '');
        
        if (!empty($this->mailchimp_api_key)) {
            $this->mailchimp_server_prefix = substr($this->mailchimp_api_key, strpos($this->mailchimp_api_key, '-') + 1);
        }
    }
    
    /**
     * Main function to send user data to Mailchimp
     */
    public function send_to_mailchimp($valid_form_data, $form_id, $user_id) {
        if (empty($this->mailchimp_api_key) || empty($this->mailchimp_list_id)) {
            error_log('Mailchimp User Registration: API key or List ID not configured');
            return;
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            error_log('Mailchimp User Registration: User not found for ID ' . $user_id);
            return;
        }
        
        $email = $user->user_email;
        $first_name = get_user_meta($user_id, 'first_name', true) ?: $user->display_name;
        $last_name = get_user_meta($user_id, 'last_name', true) ?: '';
        
        // If first/last name not available, try to extract from form data
        if (empty($first_name) && isset($valid_form_data['user_registration_first_name'])) {
            $first_name = sanitize_text_field($valid_form_data['user_registration_first_name']['value']);
        }
        if (empty($last_name) && isset($valid_form_data['user_registration_last_name'])) {
            $last_name = sanitize_text_field($valid_form_data['user_registration_last_name']['value']);
        }
        
        $this->add_subscriber_to_mailchimp($email, $first_name, $last_name);
    }
    
    /**
     * Add subscriber to Mailchimp with User Registration tag
     */
    private function add_subscriber_to_mailchimp($email, $first_name = '', $last_name = '') {
        $url = "https://{$this->mailchimp_server_prefix}.api.mailchimp.com/3.0/lists/{$this->mailchimp_list_id}/members";
        
        $data = array(
            'email_address' => $email,
            'status' => 'subscribed',
            'merge_fields' => array(
                'FNAME' => $first_name,
                'LNAME' => $last_name
            ),
            'tags' => array('User Registration')
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'apikey ' . $this->mailchimp_api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('Mailchimp User Registration Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            error_log("Mailchimp User Registration: Successfully added {$email} to list");
            return true;
        } else {
            error_log("Mailchimp User Registration Error {$response_code}: " . $response_body);
            return false;
        }
    }
    
    /**
     * AJAX handler for fallback method
     */
    public function ajax_send_to_mailchimp() {
        if (!isset($_POST['email']) || !is_email($_POST['email'])) {
            wp_die('Invalid email');
        }
        
        $email = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        
        $result = $this->add_subscriber_to_mailchimp($email, $first_name, $last_name);
        
        wp_die($result ? 'success' : 'error');
    }
    
    /**
     * Add JavaScript to handle registration page
     */
    public function add_registration_script() {
        // Only run on registration success pages
        if (!is_page() || !isset($_GET['ur_registration_successful'])) {
            return;
        }
        
        global $wp;
        $current_url = home_url($wp->request);
        
        // Check if we're on a registration success page
        if (strpos($current_url, 'registration') === false && !isset($_GET['ur_registration_successful'])) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Try to extract user data from the page or form
            var userEmail = '';
            var firstName = '';
            var lastName = '';
            
            // Try to get data from URL parameters or hidden fields
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('user_email')) {
                userEmail = urlParams.get('user_email');
            }
            if (urlParams.get('first_name')) {
                firstName = urlParams.get('first_name');
            }
            if (urlParams.get('last_name')) {
                lastName = urlParams.get('last_name');
            }
            
            // Try to get from form fields if still on the page
            if (!userEmail) {
                var emailField = $('input[name*="email"], input[type="email"]').last();
                if (emailField.length) {
                    userEmail = emailField.val();
                }
            }
            
            if (!firstName) {
                var firstNameField = $('input[name*="first"], input[name*="fname"]').last();
                if (firstNameField.length) {
                    firstName = firstNameField.val();
                }
            }
            
            if (!lastName) {
                var lastNameField = $('input[name*="last"], input[name*="lname"]').last();
                if (lastNameField.length) {
                    lastName = lastNameField.val();
                }
            }
            
            // Send to Mailchimp if we have an email
            if (userEmail && userEmail.indexOf('@') > -1) {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'mailchimp_user_reg',
                        email: userEmail,
                        first_name: firstName,
                        last_name: lastName
                    },
                    success: function(response) {
                        console.log('Mailchimp registration:', response);
                    },
                    error: function(xhr, status, error) {
                        console.log('Mailchimp error:', error);
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Mailchimp User Registration',
            'Mailchimp User Reg',
            'manage_options',
            'mailchimp-user-reg',
            array($this, 'options_page')
        );
    }
    
    /**
     * Settings initialization
     */
    public function settings_init() {
        register_setting('mailchimp_user_reg_settings', 'mur_mailchimp_api_key');
        register_setting('mailchimp_user_reg_settings', 'mur_mailchimp_list_id');
        
        add_settings_section(
            'mur_settings_section',
            'Mailchimp Configuration',
            array($this, 'settings_section_callback'),
            'mailchimp_user_reg_settings'
        );
        
        add_settings_field(
            'mur_mailchimp_api_key',
            'Mailchimp API Key',
            array($this, 'api_key_render'),
            'mailchimp_user_reg_settings',
            'mur_settings_section'
        );
        
        add_settings_field(
            'mur_mailchimp_list_id',
            'Mailchimp List/Audience ID',
            array($this, 'list_id_render'),
            'mailchimp_user_reg_settings',
            'mur_settings_section'
        );
    }
    
    public function api_key_render() {
        $api_key = get_option('mur_mailchimp_api_key', '');
        echo '<input type="text" name="mur_mailchimp_api_key" value="' . esc_attr($api_key) . '" size="50" />';
        echo '<p class="description">Your Mailchimp API key (format: xxxxxxxxxxxxxxxxxxxxxxxxx-us12)</p>';
    }
    
    public function list_id_render() {
        $list_id = get_option('mur_mailchimp_list_id', '');
        echo '<input type="text" name="mur_mailchimp_list_id" value="' . esc_attr($list_id) . '" size="20" />';
        echo '<p class="description">Your Mailchimp Audience/List ID</p>';
    }
    
    public function settings_section_callback() {
        echo '<p>Configure your Mailchimp settings to automatically add new user registrations to your audience with the "User Registration" tag.</p>';
    }
    
    /**
     * Options page
     */
    public function options_page() {
        ?>
        <div class="wrap">
            <h1>Mailchimp User Registration Settings</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('mailchimp_user_reg_settings');
                do_settings_sections('mailchimp_user_reg_settings');
                submit_button();
                ?>
            </form>
            
            <div class="notice notice-info">
                <h3>Setup Instructions:</h3>
                <ol>
                    <li><strong>Get your API Key:</strong> Go to Mailchimp → Account → Extras → API Keys</li>
                    <li><strong>Get your List ID:</strong> Go to Audience → Settings → Audience name and campaign defaults</li>
                    <li><strong>Tags:</strong> Users will automatically be tagged with "User Registration"</li>
                    <li><strong>Testing:</strong> Check your WordPress error logs for debugging information</li>
                </ol>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new MailchimpUserRegistration();

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'mailchimp_user_reg_activate');
function mailchimp_user_reg_activate() {
    // Create default options
    add_option('mur_mailchimp_api_key', '');
    add_option('mur_mailchimp_list_id', '');
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'mailchimp_user_reg_deactivate');
function mailchimp_user_reg_deactivate() {
    // Clean up if needed
}
?>