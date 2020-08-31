<?php

/*
  Plugin Name: Vendor Registration Form
  Description: Custom registration form
  Version: 1.1
  Author: org100h
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}


class emailConfirmation
{
    const PREFIX = 'email-confirmation-';

    public static function send($to, $subject, $message, $headers)
    {
        $token = sha1(uniqid());

        $oldData = get_option(self::PREFIX . 'data') ?: array();
        $data = array();
        $data[$token] = $_POST;
        update_option(self::PREFIX . 'data', array_merge($oldData, $data));

        // Get woocommerce mailer from instance
        $mailer = WC()->mailer();

        // Wrap message using woocommerce html email template
        $wrapped_message = $mailer->wrap_message($subject, sprintf($message, $token));

        // Create new WC_Email instance
        $wc_email = new WC_Email;

        // Style the wrapped message with woocommerce inline styles
        $html_message = $wc_email->style_inline($wrapped_message);

        wp_mail($to, $subject, $html_message, $headers);
    }

    public static function check($token)
    {

        $userData = [];
        $data = get_option(self::PREFIX . 'data');
        if (array_key_exists($token, $data))
            $userData = $data[$token];

        if (isset($userData)) {
            unset($data[$token]);
            update_option(self::PREFIX . 'data', $data);
        }

        return $userData;
    }
}


class vendorRegForm
{

    private $vendor_form_slug = 'vendor-dashboard-pro';
    private $pending_role = 'pending_user';

    public function __construct()
    {
        // register shortcode
        add_shortcode('vendor_registration_form', [$this, 'vendor_custom_shortcode_registration']);


        // register
        add_action('wp_ajax_create_user', [$this, 'create_user']);
        add_action('wp_ajax_nopriv_create_user', [$this, 'create_user']);

        // register assets
        add_action('wp_enqueue_scripts', [$this, 'front_assets']);

        // check token email confirmation

        add_action('init', [$this, 'check_token']);

        // add not confirmed user role

        register_activation_hook(__FILE__, [$this, 'add_pending_role']);
    }

    public function add_pending_role()
    {
        add_role($this->pending_role, 'Pending User', array('read' => true, 'level_0' => true));
    }

    /**
     *  Check user activation token
     *
     * @return string
     */
    public function check_token()
    {
        if (isset($_GET['token'])) {
            $args = EmailConfirmation::check($_GET['token']); // $_POST saved for this token
            //var_dump($args);
            if (!empty($args)) {
                // Set the user's role (and implicitly remove the previous role).

                $user_id = get_user_by('email', $args['vendor__user_email'])->ID;

                $user = new WP_User($user_id);
                $user->set_role('customer');

                $sign = wp_signon(['user_login' => $args['vendor__user_name'], 'user_password' => $args['vendor__user_pass'], 'remember' => true]);
                if (is_wp_error($sign)) {
                    $errors[] = $sign->get_error_message();
                }
            }
        }
    }

    public function front_assets()
    {
        wp_enqueue_script('vrf-validate', 'https://cdn.jsdelivr.net/npm/jquery-validation@1.19.1/dist/jquery.validate.min.js', array('jquery'), 'v1.0', true);
        wp_register_style('vrf-css', plugins_url('/css/style.css', __FILE__), '1.0', 'all');
        wp_register_script('vrf-js', plugins_url('/js/functions.js', __FILE__), [], '1.0', true);

        // print js variable
        wp_localize_script('vrf-js', 'form', [
                'ajaxurl' => admin_url('admin-ajax.php')
            ]
        );
    }

    /*
     *
     * Register form shortcode
     *
     */

    public function vendor_custom_shortcode_registration()
    {

        wp_enqueue_style('vrf-css');
        wp_enqueue_script('vrf-js');
        wp_enqueue_script('password-strength-meter');
        if (!is_user_logged_in()) {
            ob_start(); ?>
            <form id="vendor__login_form" class="vendor__form cart" action="" method="post">
                <fieldset class="cart-body">

                    <div class="form-response">

                    </div>
                    <span class="loading">
                        <div class="lds-ripple"><div></div><div></div></div>
                    </span>
                    <div class="row">

                        <p class="col-md-12">
                            <label for="vendor__user_name">User name<span class="required">*</span></label>
                            <input name="vendor__user_name" id="vendor__user_name" class="required" type="text"/>
                        </p>

                        <p class="col-md-12">
                            <label for="vendor__user_email">eMail<span class="required">*</span></label>
                            <input name="vendor__user_email" id="vendor__user_email" class="required" type="email"/>
                        </p>
                    </div>
                    <div class="row">
                        <p class="col-md-6">
                            <label for="vendor__user_pass">Password<span class="required">*</span></label>
                            <input name="vendor__user_pass" id="vendor__user_pass" class="required" type="password"/>
                        </p>
                        <p class="col-md-6">
                            <label for="vendor__user_pass_check">Repeat Password<span class="required">*</span></label>
                            <input name="vendor__user_pass_check" id="vendor__user_pass_check" class="required"
                                   type="password"/>
                        </p>
                    </div>
                    <p>
                        <input type="hidden" name="vendor__register_nonce" id="vendor__register_nonce"
                               value="<?php echo wp_create_nonce('vendor-register-nonce'); ?>"/>
                        <input id="vendor__register_submit" type="submit" value="Register"/>
                    </p>
                </fieldset>
            </form>
            <?php
            return ob_get_clean();
        } else {
            ob_start(); ?>
            <script>
                location.href = '<?= $this->vendor_form_slug ?>'
            </script>
            <?php
            return ob_get_clean();
        }
    }

    /*
     *  Create wordpress user end redirect him to dashboard
     */

    public function create_user()
    {

        $args = [];
        $errors = [];

        check_ajax_referer('vendor-register-nonce', 'nonce');

        array_filter($_POST, [$this, 'trim_value']);

        $args['vendor__user_name'] = filter_var($_POST['vendor__user_name'], FILTER_SANITIZE_STRING);
        $args['vendor__user_email'] = filter_var($_POST['vendor__user_email'], FILTER_SANITIZE_EMAIL);
        $args['vendor__user_pass'] = $_POST['vendor__user_pass'];
        $args['vendor__user_pass_check'] = $_POST['vendor__user_pass_check'];

        if (empty($args['vendor__user_name'])) {
            $errors[] = 'User name field is empty';
        }

        if (empty($args['vendor__user_email'])) {
            $errors[] = 'eMail field is empty';
        }

        if (empty($args['vendor__user_pass'])) {
            $errors[] = 'Password field is empty';
        }

        if (empty($args['vendor__user_pass_check']) || $args['vendor__user_pass'] !== $args['vendor__user_pass_check']) {
            $errors[] = 'Password didn\'t match';
        }

        if (empty($errors)) {
            $user_id = wp_create_user($args['vendor__user_name'], $args['vendor__user_pass'], $args['vendor__user_email']);

            // wp_new_user_notification($user_id, 'admin');

            if (is_wp_error($user_id)) {
                $errors[] = $user_id->get_error_message();
            } else {

                // Set the user's role (and implicitly remove the previous role).
                $user = new WP_User($user_id);
                $user->set_role($this->pending_role);

                $this->sendConfirmation($args);
            }
        }

        if (!empty($errors)) {
            wp_send_json_error($errors);
        } else {
            wp_send_json_success($this->vendor_form_slug);
        }

    }

    private function sendConfirmation($args)
    {
        //$headers = 'From: admin <' . get_bloginfo('admin_email') . '>; Content-Type: text/html; charset=UTF-8';
        $headers = ['Content-Type: text/html; charset=UTF-8 From: Hello from Findar <' . get_bloginfo('admin_email') . '>;'];
        $to = $args['vendor__user_email'];
        $subject = 'Welcome to Findar';
        $message = '<p>Thank you for registering to sell on Findar. We are looking forward to receiving your deal on our platform.</p>
                    <p>Finish your Vendor signup, upload as many product deals as you want. We will review and if all information is clear, you will see your products go live in less than 24 hrs.</p>                
                    <a style="color:#fff; background-color:#ff75ea; padding:18px; border-radius: 12px; text-decoration: none;width:  margin: 30px auto;display: block;width:230px; text-align: center;font-size:12px; 
                  " href="' . home_url($this->vendor_form_slug) . '?token=%s">Confirm your registration</a>';


        EmailConfirmation::send($to, $subject, $message, $headers);
    }

    /*
     * Sanitize $_POST
     */

    private function trim_value(&$val)
    {
        $val = trim(strip_tags($val));
    }


}

$vendorRegForm = new vendorRegForm();