<?php
/*
Plugin Name: aSMTP mailer
Version: 1.4.3
Requires at least: 7.0
Requires PHP: 7.4
Author: Awhadi
Description: Configure a secure SMTP server for WordPress email delivery.
Text Domain: asmtp-mailer
Domain Path: /languages 
 */

if (!defined('ABSPATH')){
    exit;
}

class ASMTP_MAILER {
    
    var $plugin_version = '1.4.3';
    var $plugin_url;
    var $plugin_path;
    
    function __construct() {
        define('ASMTP_MAILER_VERSION', $this->plugin_version);
        define('ASMTP_MAILER_URL', $this->plugin_url());
        $this->loader_operations();
    }

    function loader_operations() {
        add_action('plugins_loaded', array($this, 'plugins_loaded_handler'));
        add_action('admin_menu', array($this, 'options_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('pre_wp_mail', 'asmtp_mailer_capture_mail_context', 5, 2);
        add_filter('wp_mail_from', 'asmtp_mailer_filter_from_email');
        add_filter('wp_mail_from_name', 'asmtp_mailer_filter_from_name');
        add_action('phpmailer_init', 'asmtp_mailer_configure_phpmailer');
        add_action('wp_mail_succeeded', 'asmtp_mailer_log_successful_email');
        add_action('wp_mail_failed', 'asmtp_mailer_log_failed_email');
    }
    
    function enqueue_admin_scripts($hook) {
        if('settings_page_asmtp-mailer-settings' != $hook) {
            return;
        }
        wp_register_style('asmtp-mailer-admin', ASMTP_MAILER_URL.'/assets/css/admin-settings-interface.css', array(), ASMTP_MAILER_VERSION);
        wp_enqueue_style('asmtp-mailer-admin');
        wp_register_script('asmtp-mailer-admin', ASMTP_MAILER_URL.'/assets/js/admin-settings-controls.js', array(), ASMTP_MAILER_VERSION, true);
        wp_localize_script(
            'asmtp-mailer-admin',
            'asmtpMailerAdmin',
            array(
                'deleteConfirmMessage' => __('Are you sure you want to reset these settings?', 'asmtp-mailer'),
                'clearLogConfirmMessage' => __('Are you sure you want to clear the logs?', 'asmtp-mailer'),
                'defaultPorts' => asmtp_mailer_get_encryption_ports(),
            )
        );
        wp_enqueue_script('asmtp-mailer-admin');
    }
    
    function plugins_loaded_handler()
    {
        if(is_admin() && current_user_can('manage_options')){
            add_filter('plugin_action_links', array($this, 'add_plugin_action_links'), 10, 2);
        }
        load_plugin_textdomain('asmtp-mailer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/'); 
    }

    function plugin_url() {
        if ($this->plugin_url){
            return $this->plugin_url;
        }
        return $this->plugin_url = plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__));
    }

    function plugin_path() {
        if ($this->plugin_path){
            return $this->plugin_path;
        }
        return $this->plugin_path = untrailingslashit(plugin_dir_path(__FILE__));
    }

    function add_plugin_action_links($links, $file) {
        if ($file == plugin_basename(__FILE__)) {
            $links[] = '<a href="' . esc_url(admin_url('options-general.php?page=asmtp-mailer-settings')) . '">' . esc_html__('Settings', 'asmtp-mailer') . '</a>';
        }
        return $links;
    }
    
    function options_menu() {
        add_options_page(__('aSMTP mailer', 'asmtp-mailer'), __('aSMTP mailer', 'asmtp-mailer'), 'manage_options', 'asmtp-mailer-settings', array($this, 'options_page'));
    }
    
    function options_page() {
        $plugin_tabs = array(
            '' => __('SMTP', 'asmtp-mailer'),
            'sender' => __('Sender & Reply-To', 'asmtp-mailer'),
            'test-email' => __('Test Email', 'asmtp-mailer'),
            'email-log' => __('Logs', 'asmtp-mailer'),
        );
        echo '<div class="wrap asmtp-mailer-admin-wrap">';
        echo '<div class="asmtp-mailer-toast-region" aria-live="polite" aria-atomic="true"></div>';
        echo '<div class="asmtp-mailer-admin-header">';
        echo '<div class="asmtp-mailer-header-brand">';
        echo '<h1 class="asmtp-mailer-header-title">' . esc_html__('SMTP Mailer', 'asmtp-mailer') . '</h1>';
        echo '<p class="asmtp-mailer-header-tagline">' . esc_html__('Secure SMTP delivery, reply routing, testing, and logs in one focused panel.', 'asmtp-mailer') . '</p>';
        echo '</div>';
        echo '<div class="asmtp-mailer-header-actions"><span class="asmtp-mailer-version">' . esc_html__('Version', 'asmtp-mailer') . ' ' . esc_html(ASMTP_MAILER_VERSION) . '</span></div>';
        echo '</div>';
        $action = '';
        if (isset($_GET['action'])) {
            $action = sanitize_key(wp_unslash($_GET['action']));
        }
        $content = '';
        $content .= '<div class="asmtp-mailer-admin-tabs">';
        foreach ($plugin_tabs as $tab_action => $tabname) {
            if ($action === $tab_action) {
                $class = ' nav-tab-active';
            } else {
                $class = '';
            }
            $tab_url = admin_url('options-general.php?page=asmtp-mailer-settings');
            if (!empty($tab_action)) {
                $tab_url = add_query_arg('action', $tab_action, $tab_url);
            }
            $active_class = $class ? ' active' : '';
            $content .= '<a class="asmtp-mailer-tab' . esc_attr($active_class) . '" href="' . esc_url($tab_url) . '">' . esc_html($tabname) . '</a>';
        }
        $content .= '</div>';
        $allowed_html_tags = array(
            'a' => array(
                'href' => array(),
                'class' => array()
            ),
            'div' => array(
                'class' => array()
            ),
        );
        echo wp_kses($content, $allowed_html_tags);
        echo '<div class="asmtp-mailer-admin-content">';
        if(!empty($action))
        { 
            switch($action)
            {
               case 'sender':
                   $this->sender_settings();
                   break;
               case 'test-email':
                   $this->test_email_settings();
                   break;
               case 'email-log':
                   $this->email_log_settings();
                   break;
            }
        }
        else
        {
            $this->general_settings();
        }
        echo '</div>';
        echo '</div>';
    }    
    
    function test_email_settings(){
        $options = asmtp_mailer_normalize_options(asmtp_mailer_get_option());
        $default_subject = sprintf(__('aSMTP mailer test from %s', 'asmtp-mailer'), wp_parse_url(home_url(), PHP_URL_HOST));
        $default_message = sprintf(
            __("This is a diagnostic test email from aSMTP mailer.

For best delivery, confirm these requirements before testing:\n- SMTP Host, TLS encryption, and port 587 are configured.\n- SMTP Authentication is enabled when your provider requires credentials.\n- From Email uses an address approved by your SMTP provider.\n- Reply-To is configured only when replies should go somewhere different from From Email.\n- Logs are enabled when developers need records of WordPress wp_mail() send attempts; message body logging stays off unless your team explicitly needs it.

Site: %s\nWordPress mail function: wp_mail()", 'asmtp-mailer'),
            home_url()
        );
        if(isset($_POST['asmtp_mailer_send_test_email'])){
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to send test emails.', 'asmtp-mailer'));
            }
            $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'asmtp_mailer_test_email')) {
                wp_die(__('Error! Nonce Security Check Failed! please send the test email again.', 'asmtp-mailer'));
            }
            $to = '';
            if(isset($_POST['asmtp_mailer_to_email']) && !empty($_POST['asmtp_mailer_to_email'])){
                $to = sanitize_email(wp_unslash($_POST['asmtp_mailer_to_email']));
            }
            $subject = '';
            if(isset($_POST['asmtp_mailer_email_subject']) && !empty($_POST['asmtp_mailer_email_subject'])){
                $subject = sanitize_text_field(wp_unslash($_POST['asmtp_mailer_email_subject']));
            }
            $message = '';
            if(isset($_POST['asmtp_mailer_email_body']) && !empty($_POST['asmtp_mailer_email_body'])){
                $message = sanitize_textarea_field(wp_unslash($_POST['asmtp_mailer_email_body']));
            }
            if (!is_email($to)) {
                asmtp_mailer_render_notice(__('Please enter a valid recipient email address.', 'asmtp-mailer'), 'error');
            } else {
                $GLOBALS['asmtp_mailer_test_context'] = array(
                    'active' => true,
                    'debug' => array(),
                    'error' => '',
                    'started_at' => microtime(true),
                );
                $sent = wp_mail($to, $subject, $message);
                $elapsed = microtime(true) - $GLOBALS['asmtp_mailer_test_context']['started_at'];
                $notice_text = $sent ? __('Test email sent. Please verify delivery in the destination inbox.', 'asmtp-mailer') : __('The test email could not be sent. Review the analysis below.', 'asmtp-mailer');
                asmtp_mailer_render_notice($notice_text, $sent ? 'success' : 'error');
                asmtp_mailer_render_test_analysis(asmtp_mailer_build_test_analysis($sent, $elapsed, $to, $subject));
                unset($GLOBALS['asmtp_mailer_test_context']);
            }
        }
        ?>
        <?php if (empty($options['smtp_host'])) : ?>
            <div class="asmtp-mailer-empty-state">
                <span class="dashicons dashicons-warning"></span>
                <h3><?php esc_html_e('SMTP not configured', 'asmtp-mailer'); ?></h3>
                <p><?php esc_html_e('Configure your SMTP host, port, and credentials on the SMTP tab before sending a test email. Use the Test Connection button to verify your settings first.', 'asmtp-mailer'); ?></p>
            </div>
        <?php else : ?>
        <form method="post" action="">
            <?php wp_nonce_field('asmtp_mailer_test_email'); ?>

            <div class="asmtp-mailer-settings-section">
                <h2><?php esc_html_e('Test Message', 'asmtp-mailer'); ?></h2>
                <p class="asmtp-mailer-section-intro"><?php esc_html_e('Send a real WordPress email through the current SMTP configuration. After sending, a delivery analysis appears with configuration checks, elapsed time, and redacted SMTP debug output for developers.', 'asmtp-mailer'); ?></p>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row"><label for="asmtp_mailer_to_email"><?php esc_html_e('To', 'asmtp-mailer');?></label></th>
                            <td><input name="asmtp_mailer_to_email" type="email" id="asmtp_mailer_to_email" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Use a mailbox you can check outside this WordPress site.', 'asmtp-mailer');?></p></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="asmtp_mailer_email_subject"><?php esc_html_e('Subject', 'asmtp-mailer');?></label></th>
                            <td><input name="asmtp_mailer_email_subject" type="text" id="asmtp_mailer_email_subject" value="<?php echo esc_attr($default_subject); ?>" class="regular-text"></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="asmtp_mailer_email_body"><?php esc_html_e('Message', 'asmtp-mailer');?></label></th>
                            <td><textarea name="asmtp_mailer_email_body" id="asmtp_mailer_email_body" rows="10"><?php echo esc_textarea($default_message); ?></textarea>
                                <p class="description"><?php echo esc_html(sprintf(__('Current transport: %1$s on port %2$s with SMTP authentication %3$s.', 'asmtp-mailer'), strtoupper($options['type_of_encryption']), $options['smtp_port'], $options['smtp_auth'])); ?></p></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="asmtp-mailer-form-actions">
                <input type="submit" name="asmtp_mailer_send_test_email" id="asmtp_mailer_send_test_email" class="button button-primary" value="<?php esc_attr_e('Send Test Email', 'asmtp-mailer');?>">
            </div>
        </form>
        <?php endif; ?>
        
        <?php
    }   
    
    function sender_settings() {
        if (isset($_POST['asmtp_mailer_reset_sender_settings'])) {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to reset sender settings.', 'asmtp-mailer'));
            }
            if (check_admin_referer('asmtp_mailer_reset_sender_settings', 'asmtp_mailer_reset_sender_settings_nonce')) {
                asmtp_mailer_update_option(asmtp_mailer_get_default_options_for_group('sender'));
                asmtp_mailer_render_notice(__('Sender, Reply-To, logging, and formatting settings reset.', 'asmtp-mailer'));
            }
        }

        if (isset($_POST['asmtp_mailer_update_sender_settings'])) {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to update sender settings.', 'asmtp-mailer'));
            }
            $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'asmtp_mailer_sender_settings')) {
                wp_die(__('Error! Nonce Security Check Failed! please save the settings again.', 'asmtp-mailer'));
            }
            $from_email = isset($_POST['from_email']) ? sanitize_email(wp_unslash($_POST['from_email'])) : '';
            $reply_to_email = isset($_POST['reply_to_email']) ? sanitize_email(wp_unslash($_POST['reply_to_email'])) : '';
            if (!empty($from_email) && !is_email($from_email)) {
                asmtp_mailer_render_notice(__('Please enter a valid From email address.', 'asmtp-mailer'), 'error');
            } elseif (!empty($reply_to_email) && !is_email($reply_to_email)) {
                asmtp_mailer_render_notice(__('Please enter a valid Reply-To email address.', 'asmtp-mailer'), 'error');
            } else {
                $options = array(
                    'from_email' => $from_email,
                    'from_name' => isset($_POST['from_name']) ? sanitize_text_field(wp_unslash($_POST['from_name'])) : '',
                    'force_from_name' => isset($_POST['force_from_name']) ? 1 : '',
                    'force_from_email' => isset($_POST['force_from_email']) ? 1 : '',
                    'force_from_address' => isset($_POST['force_from_address']) ? 1 : '',
                    'reply_to_email' => $reply_to_email,
                    'reply_to_name' => isset($_POST['reply_to_name']) ? sanitize_text_field(wp_unslash($_POST['reply_to_name'])) : '',
                    'force_reply_to' => isset($_POST['force_reply_to']) ? 1 : '',
                    'enable_email_logging' => isset($_POST['enable_email_logging']) ? 1 : '',
                    'log_email_body' => isset($_POST['log_email_body']) ? 1 : '',
                    'convert_plain_text_to_html' => isset($_POST['convert_plain_text_to_html']) ? 1 : '',
                    'email_log_retention' => isset($_POST['email_log_retention']) ? asmtp_mailer_sanitize_log_retention(wp_unslash($_POST['email_log_retention'])) : 100,
                );
                asmtp_mailer_update_option($options);
                asmtp_mailer_render_notice(__('Sender settings saved.', 'asmtp-mailer'));
            }
        }

        $options = asmtp_mailer_normalize_options(asmtp_mailer_get_option());
        ?>
        <form method="post" action="" id="asmtp-mailer-sender-settings-form">
            <?php wp_nonce_field('asmtp_mailer_sender_settings'); ?>
            <div class="asmtp-mailer-settings-section">
                <h2><?php esc_html_e('Sender Identity', 'asmtp-mailer'); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row"><label for="from_email"><?php esc_html_e('From Email Address', 'asmtp-mailer');?></label></th>
                            <td><input name="from_email" type="email" id="from_email" value="<?php echo esc_attr($options['from_email']); ?>" class="regular-text code" placeholder="<?php echo esc_attr(asmtp_mailer_get_default_from_email()); ?>">
                                <p class="description"><?php echo esc_html(sprintf(__('Leave empty to use %s. Many SMTP providers reject From addresses not owned by the authenticated SMTP user.', 'asmtp-mailer'), asmtp_mailer_get_default_from_email()));?></p></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="from_name"><?php esc_html_e('From Name', 'asmtp-mailer');?></label></th>
                            <td><input name="from_name" type="text" id="from_name" value="<?php echo esc_attr($options['from_name']); ?>" class="regular-text code">
                                <p class="description"><?php esc_html_e('The default sender name for outgoing WordPress email.', 'asmtp-mailer');?></p></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e('Force Sender', 'asmtp-mailer');?></th>
                            <td>
                                <label class="asmtp-mailer-toggle"><input name="force_from_name" type="checkbox" id="force_from_name" <?php checked($options['force_from_name'], 1); ?> value="1"><span></span><?php esc_html_e('Force From name', 'asmtp-mailer');?></label>
                                <label class="asmtp-mailer-toggle"><input name="force_from_email" type="checkbox" id="force_from_email" <?php checked($options['force_from_email'], 1); ?> value="1"><span></span><?php esc_html_e('Force From email', 'asmtp-mailer');?></label>
                                <label class="asmtp-mailer-toggle"><input name="force_from_address" type="checkbox" id="force_from_address" <?php checked($options['force_from_address'], 1); ?> value="1"><span></span><?php esc_html_e('Force full From address', 'asmtp-mailer');?></label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="asmtp-mailer-settings-section">
                <h2><?php esc_html_e('Reply-To', 'asmtp-mailer'); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row"><label for="reply_to_email"><?php esc_html_e('Reply-To Email Address', 'asmtp-mailer');?></label></th>
                            <td><input name="reply_to_email" type="email" id="reply_to_email" value="<?php echo esc_attr($options['reply_to_email']); ?>" class="regular-text code">
                                <p class="description"><?php esc_html_e('Replies will be directed to this address when no message-specific Reply-To exists, or always when forced.', 'asmtp-mailer');?></p></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="reply_to_name"><?php esc_html_e('Reply-To Name', 'asmtp-mailer');?></label></th>
                            <td><input name="reply_to_name" type="text" id="reply_to_name" value="<?php echo esc_attr($options['reply_to_name']); ?>" class="regular-text code">
                                <p class="description"><?php esc_html_e('Optional display name for the Reply-To address.', 'asmtp-mailer');?></p></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="force_reply_to"><?php esc_html_e('Force Reply-To', 'asmtp-mailer');?></label></th>
                            <td><label class="asmtp-mailer-toggle"><input name="force_reply_to" type="checkbox" id="force_reply_to" <?php checked($options['force_reply_to'], 1); ?> value="1"><span></span><?php esc_html_e('Replace existing Reply-To headers with the address above.', 'asmtp-mailer');?></label></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="asmtp-mailer-settings-section">
                <h2><?php esc_html_e('Email Formatting', 'asmtp-mailer'); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row"><label for="convert_plain_text_to_html"><?php esc_html_e('Plain Text to HTML', 'asmtp-mailer');?></label></th>
                            <td>
                                <label class="asmtp-mailer-toggle"><input name="convert_plain_text_to_html" type="checkbox" id="convert_plain_text_to_html" <?php checked($options['convert_plain_text_to_html'], 1); ?> value="1"><span></span><?php esc_html_e('Convert plain text emails to simple HTML while preserving a text alternative.', 'asmtp-mailer');?></label>
                                <p class="description"><?php esc_html_e('Use this when you want consistent HTML email formatting. Leave it off if another plugin already formats email bodies.', 'asmtp-mailer'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="asmtp-mailer-settings-section">
                <h2><?php esc_html_e('Logs', 'asmtp-mailer'); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row"><label for="enable_email_logging"><?php esc_html_e('Enable Logs', 'asmtp-mailer');?></label></th>
                            <td>
                                <label class="asmtp-mailer-toggle"><input name="enable_email_logging" type="checkbox" id="enable_email_logging" <?php checked($options['enable_email_logging'], 1); ?> value="1"><span></span><?php esc_html_e('Record every WordPress email sent through wp_mail() in the database.', 'asmtp-mailer');?></label>
                                <p class="description"><?php esc_html_e('Logs are stored in the WordPress database and visible on the Logs tab.', 'asmtp-mailer'); ?></p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><label for="log_email_body"><?php esc_html_e('Log Message Body', 'asmtp-mailer');?></label></th>
                            <td><label class="asmtp-mailer-toggle"><input name="log_email_body" type="checkbox" id="log_email_body" <?php checked($options['log_email_body'], 1); ?> value="1"><span></span><?php esc_html_e('Save a 30-word excerpt of the email body alongside each log entry.', 'asmtp-mailer');?></label>
                                <p class="description"><?php esc_html_e('The excerpt is stored in the same WordPress database option as the log record. Leave off for better privacy.', 'asmtp-mailer'); ?></p></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="email_log_retention"><?php esc_html_e('Log Retention', 'asmtp-mailer');?></label></th>
                            <td>
                                <select name="email_log_retention" id="email_log_retention">
                                    <option value="100" <?php selected($options['email_log_retention'], 100); ?>><?php esc_html_e('Latest 100 emails', 'asmtp-mailer'); ?></option>
                                    <option value="250" <?php selected($options['email_log_retention'], 250); ?>><?php esc_html_e('Latest 250 emails', 'asmtp-mailer'); ?></option>
                                    <option value="500" <?php selected($options['email_log_retention'], 500); ?>><?php esc_html_e('Latest 500 emails', 'asmtp-mailer'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </form>
        <div class="asmtp-mailer-form-actions">
            <input type="submit" form="asmtp-mailer-sender-settings-form" name="asmtp_mailer_update_sender_settings" id="asmtp_mailer_update_sender_settings" class="button button-primary asmtp-mailer-admin-button asmtp-mailer-admin-button-primary" value="<?php esc_attr_e('Save Sender Settings', 'asmtp-mailer');?>">
            <form method="post" class="asmtp-mailer-reset-form">
                <?php wp_nonce_field('asmtp_mailer_reset_sender_settings', 'asmtp_mailer_reset_sender_settings_nonce'); ?>
                <input type="submit" name="asmtp_mailer_reset_sender_settings" id="asmtp_mailer_reset_sender_settings" class="button" value="<?php esc_attr_e('Reset Sender Settings', 'asmtp-mailer'); ?>">
            </form>
        </div>
        <?php
    }

    function general_settings() {
        $connection_test_result = null;
        
        if (isset($_POST['asmtp_mailer_test_smtp_connection'])) {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to test SMTP settings.', 'asmtp-mailer'));
            }
            $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'asmtp_mailer_general_settings')) {
                wp_die(__('Error! Nonce Security Check Failed! please test the connection again.', 'asmtp-mailer'));
            }
            $test_options = asmtp_mailer_get_smtp_options_from_post();
            $save_options = $test_options;
            unset($save_options['smtp_password_plain']);
            asmtp_mailer_update_option($save_options);
            $connection_test_result = asmtp_mailer_test_smtp_connection($test_options);
            asmtp_mailer_render_notice($connection_test_result['message'], $connection_test_result['success'] ? 'success' : 'error');
        }

        if (isset($_POST['asmtp_mailer_update_settings'])) {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to update aSMTP mailer settings.', 'asmtp-mailer'));
            }
            $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'asmtp_mailer_general_settings')) {
                wp_die(__('Error! Nonce Security Check Failed! please save the settings again.', 'asmtp-mailer'));
            }
            $options = asmtp_mailer_get_smtp_options_from_post();
            unset($options['smtp_password_plain']);
            asmtp_mailer_update_option($options);
            asmtp_mailer_render_notice(__('SMTP settings saved.', 'asmtp-mailer'));
        }

        if (isset($_POST['asmtp_mailer_reset_smtp_settings'])) {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to reset SMTP settings.', 'asmtp-mailer'));
            }
            if(check_admin_referer('asmtp_mailer_reset_smtp_settings', 'asmtp_mailer_reset_smtp_settings_nonce')) {
                asmtp_mailer_update_option(asmtp_mailer_get_default_options_for_group('smtp'));
                asmtp_mailer_render_notice(__('SMTP settings reset.', 'asmtp-mailer'));
            }
        }
        
        $options = asmtp_mailer_normalize_options(asmtp_mailer_get_option());
        $password_is_saved = !empty($options['smtp_password']);
        $password_is_editable = !$password_is_saved || (is_array($connection_test_result) && !$connection_test_result['success']);
        
        ?>

        <form method="post" action="" id="asmtp-mailer-smtp-settings-form">
            <?php wp_nonce_field('asmtp_mailer_general_settings'); ?>

            <div class="asmtp-mailer-settings-section">
                <h2><?php esc_html_e('Connection', 'asmtp-mailer'); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row"><label for="smtp_host"><?php esc_html_e('SMTP Host', 'asmtp-mailer');?></label></th>
                            <td><input name="smtp_host" type="text" id="smtp_host" value="<?php echo esc_attr($options['smtp_host']); ?>" class="regular-text code" placeholder="smtp.example.com">
                                <p class="description"><?php esc_html_e('The SMTP server used for outgoing WordPress email.', 'asmtp-mailer');?></p></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="asmtp-mailer-settings-section">
                <h2><?php esc_html_e('Authentication', 'asmtp-mailer'); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="smtp_auth"><?php esc_html_e('SMTP Authentication', 'asmtp-mailer');?></label></th>
                            <td>
                                <select name="smtp_auth" id="smtp_auth">
                                    <option value="true" <?php selected($options['smtp_auth'], 'true'); ?>><?php esc_html_e('Enabled', 'asmtp-mailer');?></option>
                                    <option value="false" <?php selected($options['smtp_auth'], 'false'); ?>><?php esc_html_e('Disabled', 'asmtp-mailer');?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Most SMTP providers require authentication.', 'asmtp-mailer');?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="smtp_username"><?php esc_html_e('SMTP Username', 'asmtp-mailer');?></label></th>
                            <td><input name="smtp_username" type="text" id="smtp_username" value="<?php echo esc_attr($options['smtp_username']); ?>" class="regular-text code"></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="smtp_password"><?php esc_html_e('SMTP Password', 'asmtp-mailer');?></label></th>
                            <td>
                                <div class="asmtp-mailer-inline-control">
                                    <input name="smtp_password" type="password" id="smtp_password" value="<?php echo $password_is_saved && !$password_is_editable ? esc_attr('••••••••••••') : ''; ?>" class="regular-text code" placeholder="<?php echo $password_is_saved ? esc_attr__('Password saved', 'asmtp-mailer') : esc_attr__('Enter SMTP password', 'asmtp-mailer'); ?>" <?php disabled(!$password_is_editable); ?> data-saved-placeholder="••••••••••••">
                                    <?php if ($password_is_saved) : ?>
                                        <button type="button" class="button asmtp-mailer-admin-button asmtp-mailer-admin-button-secondary asmtp-mailer-change-password-button" data-password-target="smtp_password"><?php esc_html_e('Change Password', 'asmtp-mailer'); ?></button>
                                    <?php endif; ?>
                                    <input type="submit" name="asmtp_mailer_test_smtp_connection" id="asmtp_mailer_test_smtp_connection" class="button asmtp-mailer-admin-button asmtp-mailer-admin-button-secondary" value="<?php esc_attr_e('Test Connection', 'asmtp-mailer'); ?>">
                                </div>
                                <p class="description"><?php echo !empty($options['smtp_password']) ? esc_html__('A password is saved securely. Enter a new one only when you want to replace it.', 'asmtp-mailer') : esc_html__('No SMTP password is saved yet.', 'asmtp-mailer'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="asmtp-mailer-settings-section">
                <h2><?php esc_html_e('Security', 'asmtp-mailer'); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="type_of_encryption"><?php esc_html_e('Type of Encryption', 'asmtp-mailer');?></label></th>
                            <td>
                                <select name="type_of_encryption" id="type_of_encryption" data-port-target="smtp_port">
                                    <option value="tls" <?php selected($options['type_of_encryption'], 'tls'); ?>><?php esc_html_e('TLS - recommended', 'asmtp-mailer');?></option>
                                    <option value="ssl" <?php selected($options['type_of_encryption'], 'ssl'); ?>><?php esc_html_e('SSL', 'asmtp-mailer');?></option>
                                    <option value="none" <?php selected($options['type_of_encryption'], 'none'); ?>><?php esc_html_e('None - not recommended', 'asmtp-mailer');?></option>
                                </select>
                                <p class="description"><?php esc_html_e('TLS is the secure default. Changing this field updates the port suggestion automatically.', 'asmtp-mailer');?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="smtp_port"><?php esc_html_e('SMTP Port', 'asmtp-mailer');?></label></th>
                            <td><input name="smtp_port" type="number" id="smtp_port" value="<?php echo esc_attr($options['smtp_port']); ?>" class="regular-text code" min="1" max="65535" data-port-manual="false">
                                <p class="description"><?php esc_html_e('Common ports: TLS 587, SSL 465, no encryption 25.', 'asmtp-mailer');?></p></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </form>
        <div class="asmtp-mailer-form-actions">
            <input type="submit" form="asmtp-mailer-smtp-settings-form" name="asmtp_mailer_update_settings" id="asmtp_mailer_update_settings" class="button button-primary" value="<?php esc_attr_e('Save SMTP Settings', 'asmtp-mailer')?>">
            <form method="post" class="asmtp-mailer-reset-form">
                <?php wp_nonce_field('asmtp_mailer_reset_smtp_settings', 'asmtp_mailer_reset_smtp_settings_nonce'); ?>
                <input type="submit" name="asmtp_mailer_reset_smtp_settings" id="asmtp_mailer_reset_smtp_settings" class="button" value="<?php esc_attr_e('Reset SMTP Settings', 'asmtp-mailer')?>">
            </form>
        </div>
        
        <?php
        }
        
        function email_log_settings() {
            if (isset($_POST['asmtp_mailer_clear_email_log'])) {
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to clear logs.', 'asmtp-mailer'));
                }
                if (check_admin_referer('asmtp_mailer_clear_email_log', 'asmtp_mailer_clear_email_log_nonce')) {
                    delete_option('asmtp_mailer_email_logs');
                    asmtp_mailer_render_notice(__('Logs reset.', 'asmtp-mailer'));
                }
            }
            $options = asmtp_mailer_normalize_options(asmtp_mailer_get_option());
            $status_filter = isset($_GET['log_status']) ? sanitize_key(wp_unslash($_GET['log_status'])) : 'all';
            $status_filter = in_array($status_filter, array('all', 'success', 'error'), true) ? $status_filter : 'all';
            $logs = asmtp_mailer_get_email_logs();
            if ($status_filter !== 'all') {
                $logs = array_values(array_filter($logs, function($log) use ($status_filter) {
                    return isset($log['status']) && $log['status'] === $status_filter;
                }));
            }
            ?>
            <div class="asmtp-mailer-logs-header">
                <h2><?php esc_html_e('Logs', 'asmtp-mailer'); ?></h2>
                <div class="asmtp-mailer-logs-actions">
                    <div class="asmtp-mailer-segmented-control" aria-label="<?php esc_attr_e('Filter logs by status', 'asmtp-mailer'); ?>">
                        <a class="<?php echo $status_filter === 'all' ? 'active' : ''; ?>" href="<?php echo esc_url(admin_url('options-general.php?page=asmtp-mailer-settings&action=email-log')); ?>"><?php esc_html_e('All', 'asmtp-mailer'); ?></a>
                        <a class="<?php echo $status_filter === 'success' ? 'active' : ''; ?>" href="<?php echo esc_url(admin_url('options-general.php?page=asmtp-mailer-settings&action=email-log&log_status=success')); ?>"><?php esc_html_e('Sent', 'asmtp-mailer'); ?></a>
                        <a class="<?php echo $status_filter === 'error' ? 'active' : ''; ?>" href="<?php echo esc_url(admin_url('options-general.php?page=asmtp-mailer-settings&action=email-log&log_status=error')); ?>"><?php esc_html_e('Failed', 'asmtp-mailer'); ?></a>
                    </div>
                    <a class="button asmtp-mailer-admin-button asmtp-mailer-admin-button-secondary" href="<?php echo esc_url(admin_url('options-general.php?page=asmtp-mailer-settings&action=email-log')); ?>"><?php esc_html_e('Refresh Logs', 'asmtp-mailer'); ?></a>
                    <form method="post" class="asmtp-mailer-inline-form">
                        <?php wp_nonce_field('asmtp_mailer_clear_email_log', 'asmtp_mailer_clear_email_log_nonce'); ?>
                        <input type="submit" name="asmtp_mailer_clear_email_log" id="asmtp_mailer_clear_email_log" class="button asmtp-mailer-admin-button asmtp-mailer-admin-button-danger-ghost" value="<?php esc_attr_e('Reset Logs', 'asmtp-mailer'); ?>">
                    </form>
                </div>
            </div>
            <?php if (empty($options['enable_email_logging'])) : ?>
                <div class="asmtp-mailer-empty-state">
                    <span class="dashicons dashicons-hidden"></span>
                    <h3><?php esc_html_e('Logs are disabled', 'asmtp-mailer'); ?></h3>
                    <p><?php esc_html_e('Enable Logs in Sender & Reply-To settings when developers need delivery records.', 'asmtp-mailer'); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($options['enable_email_logging']) && empty($logs)) : ?>
                <div class="asmtp-mailer-empty-state">
                    <span class="dashicons dashicons-email-alt"></span>
                    <h3><?php esc_html_e('No email events yet', 'asmtp-mailer'); ?></h3>
                    <p><?php esc_html_e('Send a test email or wait for the next WordPress email event to start collecting delivery records.', 'asmtp-mailer'); ?></p>
                </div>
            <?php elseif (!empty($logs)) : ?>
                <div class="asmtp-mailer-status-legend"><span class="asmtp-mailer-status-badge asmtp-mailer-status-success">&bull;</span> <?php esc_html_e('Sent — SMTP server accepted the message for delivery. This does not guarantee inbox delivery.', 'asmtp-mailer'); ?><br><span class="asmtp-mailer-status-badge asmtp-mailer-status-error">&bull;</span> <?php esc_html_e('Failed — The send attempt did not complete. Check the Details column for the specific error.', 'asmtp-mailer'); ?></div>

                <div class="asmtp-mailer-logs-table-wrap">
                    <table class="asmtp-mailer-logs-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Time', 'asmtp-mailer'); ?></th>
                                <th><?php esc_html_e('Status', 'asmtp-mailer'); ?></th>
                                <th><?php esc_html_e('To', 'asmtp-mailer'); ?></th>
                                <th><?php esc_html_e('Subject', 'asmtp-mailer'); ?></th>
                                <th><?php esc_html_e('From', 'asmtp-mailer'); ?></th>
                                <th><?php esc_html_e('Reply-To', 'asmtp-mailer'); ?></th>
                                <th><?php esc_html_e('Details', 'asmtp-mailer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) : ?>
                                <tr>
                                    <td><?php echo esc_html($log['time']); ?></td>
                                    <td><span class="asmtp-mailer-status-badge asmtp-mailer-status-<?php echo esc_attr($log['status']); ?>"><?php echo esc_html(asmtp_mailer_get_status_label($log['status'])); ?></span></td>
                                    <td><?php echo esc_html($log['to']); ?></td>
                                    <td><?php echo esc_html($log['subject']); ?></td>
                                    <td><?php echo esc_html($log['from']); ?></td>
                                    <td><?php echo esc_html($log['reply_to']); ?></td>
                                    <td>
                                        <?php echo esc_html($log['details']); ?>
                                        <?php if (!empty($log['body_excerpt'])) : ?>
                                            <div class="asmtp-mailer-log-excerpt"><?php echo esc_html($log['body_excerpt']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php
        }
}

function asmtp_mailer_render_notice($message, $type = 'success') {
    $class = 'notice notice-' . ($type === 'error' ? 'error' : 'success');
    echo '<div id="message" class="' . esc_attr($class) . '"><p><strong>' . esc_html($message) . '</strong></p></div>';
}

function asmtp_mailer_get_option(){
    $options = get_option('asmtp_mailer_options');
    if (!is_array($options)) {
        $legacy_options = get_option('smtp_mailer_options');
        if (is_array($legacy_options)) {
            update_option('asmtp_mailer_options', $legacy_options);
            $options = $legacy_options;
        }
    }
    return $options;
}

function asmtp_mailer_normalize_options($options) {
    if (!is_array($options)) {
        $options = array();
    }
    $options = array_merge(asmtp_mailer_get_empty_options_array(), $options);
    $options['smtp_auth'] = in_array($options['smtp_auth'], array('true', 'false'), true) ? $options['smtp_auth'] : 'true';
    $options['type_of_encryption'] = in_array($options['type_of_encryption'], array('tls', 'ssl', 'none'), true) ? $options['type_of_encryption'] : 'tls';
    if (empty($options['smtp_port'])) {
        $options['smtp_port'] = (string) asmtp_mailer_get_default_port_for_encryption($options['type_of_encryption']);
    }
    $options['enable_email_logging'] = !empty($options['enable_email_logging']) ? 1 : '';
    $options['log_email_body'] = !empty($options['log_email_body']) ? 1 : '';
    $options['convert_plain_text_to_html'] = !empty($options['convert_plain_text_to_html']) ? 1 : '';
    $options['email_log_retention'] = asmtp_mailer_sanitize_log_retention($options['email_log_retention']);
    return $options;
}

function asmtp_mailer_get_smtp_options_from_post() {
    $current_options = asmtp_mailer_normalize_options(asmtp_mailer_get_option());
    $type_of_encryption = isset($_POST['type_of_encryption']) ? sanitize_key(wp_unslash($_POST['type_of_encryption'])) : 'tls';
    $type_of_encryption = in_array($type_of_encryption, array('tls', 'ssl', 'none'), true) ? $type_of_encryption : 'tls';
    $smtp_port = isset($_POST['smtp_port']) ? absint(wp_unslash($_POST['smtp_port'])) : 0;
    $smtp_port = ($smtp_port >= 1 && $smtp_port <= 65535) ? (string) $smtp_port : (string) asmtp_mailer_get_default_port_for_encryption($type_of_encryption);
    $options = array(
        'smtp_host' => isset($_POST['smtp_host']) ? sanitize_text_field(wp_unslash($_POST['smtp_host'])) : '',
        'smtp_auth' => isset($_POST['smtp_auth']) ? sanitize_key(wp_unslash($_POST['smtp_auth'])) : 'true',
        'smtp_username' => isset($_POST['smtp_username']) ? sanitize_text_field(wp_unslash($_POST['smtp_username'])) : '',
        'type_of_encryption' => $type_of_encryption,
        'smtp_port' => $smtp_port,
    );
    $options['smtp_auth'] = in_array($options['smtp_auth'], array('true', 'false'), true) ? $options['smtp_auth'] : 'true';
    if (isset($_POST['smtp_password']) && trim(wp_unslash($_POST['smtp_password'])) !== '') {
        $options['smtp_password'] = asmtp_mailer_encrypt_secret(trim(wp_unslash($_POST['smtp_password'])));
        $options['smtp_password_plain'] = trim(wp_unslash($_POST['smtp_password']));
    } else {
        $options['smtp_password'] = $current_options['smtp_password'];
    }
    return $options;
}

function asmtp_mailer_get_encryption_ports() {
    return array(
        'tls' => 587,
        'ssl' => 465,
        'none' => 25,
    );
}

function asmtp_mailer_get_default_port_for_encryption($encryption) {
    $ports = asmtp_mailer_get_encryption_ports();
    return isset($ports[$encryption]) ? $ports[$encryption] : $ports['tls'];
}

function asmtp_mailer_sanitize_log_retention($retention) {
    $retention = absint($retention);
    return in_array($retention, array(100, 250, 500), true) ? $retention : 100;
}

function asmtp_mailer_encrypt_secret($secret) {
    if (function_exists('openssl_encrypt') && defined('AUTH_KEY') && defined('SECURE_AUTH_KEY') && AUTH_KEY) {
        try {
            $iv = random_bytes(16);
            $key = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY, true);
            $encrypted = openssl_encrypt($secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($encrypted !== false) {
                return 'enc:' . base64_encode($iv . $encrypted);
            }
        } catch (Exception $e) {
            return 'b64:' . base64_encode($secret);
        }
    }
    return 'b64:' . base64_encode($secret);
}

function asmtp_mailer_decrypt_secret($secret) {
    if (strpos($secret, 'enc:') === 0 && function_exists('openssl_decrypt') && defined('AUTH_KEY') && defined('SECURE_AUTH_KEY') && AUTH_KEY) {
        $payload = base64_decode(substr($secret, 4), true);
        if ($payload !== false && strlen($payload) > 16) {
            $iv = substr($payload, 0, 16);
            $encrypted = substr($payload, 16);
            $key = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY, true);
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($decrypted !== false) {
                return $decrypted;
            }
        }
    }
    if (strpos($secret, 'b64:') === 0) {
        return (string) base64_decode(substr($secret, 4));
    }
    return (string) base64_decode($secret);
}

function asmtp_mailer_test_smtp_connection($options) {
    if (empty($options['smtp_host'])) {
        return array(
            'success' => false,
            'message' => __('SMTP host is required before testing the connection.', 'asmtp-mailer'),
        );
    }

    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    require_once ABSPATH . WPINC . '/class-wp-phpmailer.php';

    $phpmailer = new WP_PHPMailer(true);
    $phpmailer->isSMTP();
    $phpmailer->Host = $options['smtp_host'];
    $phpmailer->Port = absint($options['smtp_port']);
    $phpmailer->SMTPAuth = $options['smtp_auth'] === 'true';
    $phpmailer->SMTPAutoTLS = $options['type_of_encryption'] === 'tls';
    $phpmailer->SMTPSecure = $options['type_of_encryption'] === 'none' ? '' : $options['type_of_encryption'];
    $phpmailer->Timeout = 15;

    if ($phpmailer->SMTPAuth) {
        $phpmailer->Username = $options['smtp_username'];
        $phpmailer->Password = isset($options['smtp_password_plain']) ? $options['smtp_password_plain'] : asmtp_mailer_decrypt_secret($options['smtp_password']);
    }

    try {
        $phpmailer->smtpConnect();
        $phpmailer->smtpClose();
        return array(
            'success' => true,
            'message' => __('SMTP connection and authentication succeeded.', 'asmtp-mailer'),
        );
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => sprintf(__('SMTP connection failed: %s', 'asmtp-mailer'), $e->getMessage()),
        );
    }
}

function asmtp_mailer_update_option($new_options){
    $empty_options = asmtp_mailer_get_empty_options_array();
    $options = asmtp_mailer_normalize_options(asmtp_mailer_get_option());
    if(is_array($options)){
        $current_options = array_merge($empty_options, $options);
        $updated_options = array_merge($current_options, $new_options);
        update_option('asmtp_mailer_options', $updated_options);
    }
    else{
        $updated_options = array_merge($empty_options, $new_options);
        update_option('asmtp_mailer_options', $updated_options);
    }
}

function asmtp_mailer_get_empty_options_array(){
    $options = array();
    $options['smtp_host'] = '';
    $options['smtp_auth'] = 'true';
    $options['smtp_username'] = '';
    $options['smtp_password'] = '';
    $options['type_of_encryption'] = 'tls';
    $options['smtp_port'] = '587';
    $options['from_email'] = '';
    $options['from_name'] = '';
    $options['force_from_name'] = '';
    $options['force_from_email'] = '';
    $options['force_from_address'] = '';
    $options['reply_to_email'] = '';
    $options['reply_to_name'] = '';
    $options['force_reply_to'] = '';
    $options['enable_email_logging'] = 1;
    $options['log_email_body'] = '';
    $options['convert_plain_text_to_html'] = '';
    $options['email_log_retention'] = 100;
    return $options;
}

function asmtp_mailer_get_default_options_for_group($group) {
    $defaults = asmtp_mailer_get_empty_options_array();
    if ($group === 'smtp') {
        return array(
            'smtp_host' => $defaults['smtp_host'],
            'smtp_auth' => $defaults['smtp_auth'],
            'smtp_username' => $defaults['smtp_username'],
            'smtp_password' => $defaults['smtp_password'],
            'type_of_encryption' => $defaults['type_of_encryption'],
            'smtp_port' => $defaults['smtp_port'],
        );
    }
    if ($group === 'sender') {
        return array(
            'from_email' => $defaults['from_email'],
            'from_name' => $defaults['from_name'],
            'force_from_name' => $defaults['force_from_name'],
            'force_from_email' => $defaults['force_from_email'],
            'force_from_address' => $defaults['force_from_address'],
            'reply_to_email' => $defaults['reply_to_email'],
            'reply_to_name' => $defaults['reply_to_name'],
            'force_reply_to' => $defaults['force_reply_to'],
            'enable_email_logging' => $defaults['enable_email_logging'],
            'log_email_body' => $defaults['log_email_body'],
            'convert_plain_text_to_html' => $defaults['convert_plain_text_to_html'],
            'email_log_retention' => $defaults['email_log_retention'],
        );
    }
    return array();
}

function asmtp_mailer_get_email_logs() {
    $logs = get_option('asmtp_mailer_email_logs', array());
    return is_array($logs) ? $logs : array();
}

function asmtp_mailer_record_email_log($mail_data) {
    $options = asmtp_mailer_normalize_options(asmtp_mailer_get_option());
    if (empty($options['enable_email_logging'])) {
        return;
    }
    $logs = asmtp_mailer_get_email_logs();
    $body_excerpt = '';
    if (!empty($options['log_email_body']) && isset($mail_data['message'])) {
        $body_excerpt = wp_trim_words(wp_strip_all_tags((string) $mail_data['message']), 30, '...');
    }
    array_unshift($logs, array(
        'time' => current_time('mysql'),
        'status' => isset($mail_data['status']) ? sanitize_key($mail_data['status']) : 'info',
        'to' => asmtp_mailer_format_address_summary(isset($mail_data['to']) ? $mail_data['to'] : array()),
        'subject' => isset($mail_data['subject']) ? sanitize_text_field($mail_data['subject']) : '',
        'from' => isset($mail_data['from']) ? sanitize_text_field($mail_data['from']) : '',
        'reply_to' => asmtp_mailer_format_address_summary(isset($mail_data['reply_to']) ? $mail_data['reply_to'] : array()),
        'details' => isset($mail_data['details']) ? sanitize_text_field($mail_data['details']) : '',
        'body_excerpt' => $body_excerpt,
    ));
    update_option('asmtp_mailer_email_logs', array_slice($logs, 0, asmtp_mailer_sanitize_log_retention($options['email_log_retention'])), false);
}

function asmtp_mailer_format_address_summary($addresses) {
    if (empty($addresses)) {
        return '';
    }
    if (!is_array($addresses)) {
        $addresses = array($addresses);
    }
    $clean_addresses = array();
    foreach ($addresses as $address) {
        if (is_array($address)) {
            $address = implode(' ', $address);
        }
        $address = trim(wp_strip_all_tags((string) $address));
        if ($address !== '') {
            $clean_addresses[] = $address;
        }
    }
    return implode(', ', array_slice($clean_addresses, 0, 4));
}

function asmtp_mailer_get_status_label($status) {
    if ($status === 'success') {
        return __('Sent', 'asmtp-mailer');
    }
    if ($status === 'error') {
        return __('Failed', 'asmtp-mailer');
    }
    return ucfirst($status);
}

function asmtp_mailer_capture_smtp_debug($line, $level) {
    if (!isset($GLOBALS['asmtp_mailer_test_context']['active']) || !$GLOBALS['asmtp_mailer_test_context']['active']) {
        return;
    }
    $line = asmtp_mailer_redact_debug_line((string) $line);
    $GLOBALS['asmtp_mailer_test_context']['debug'][] = sprintf('[%d] %s', absint($level), $line);
}

function asmtp_mailer_redact_debug_line($line) {
    $line = wp_strip_all_tags($line);
    if (preg_match('/AUTH|LOGIN|PASS|USER|XOAUTH|Bearer|Password/i', $line)) {
        return preg_replace('/(:\s*|AUTH\s+|LOGIN\s+|PASS\s+|USER\s+).*/i', '$1[redacted]', $line);
    }
    return preg_replace('/\b[A-Za-z0-9+\/]{16,}={0,2}\b/', '[redacted-token]', $line);
}

function asmtp_mailer_build_test_analysis($sent, $elapsed, $to, $subject) {
    $options = asmtp_mailer_normalize_options(asmtp_mailer_get_option());
    $debug_lines = isset($GLOBALS['asmtp_mailer_test_context']['debug']) ? $GLOBALS['asmtp_mailer_test_context']['debug'] : array();
    $error = isset($GLOBALS['asmtp_mailer_test_context']['error']) ? $GLOBALS['asmtp_mailer_test_context']['error'] : '';
    $checks = array(
        __('SMTP host configured', 'asmtp-mailer') => !empty($options['smtp_host']),
        __('SMTP port configured', 'asmtp-mailer') => !empty($options['smtp_port']),
        __('Encryption selected', 'asmtp-mailer') => !empty($options['type_of_encryption']),
        __('From email valid', 'asmtp-mailer') => is_email(!empty($options['from_email']) ? $options['from_email'] : asmtp_mailer_get_default_from_email()),
        __('Socket transport available', 'asmtp-mailer') => function_exists('stream_socket_client') || function_exists('fsockopen'),
        __('OpenSSL available for TLS/SSL', 'asmtp-mailer') => $options['type_of_encryption'] === 'none' || extension_loaded('openssl') || defined('OPENSSL_ALGO_SHA1'),
    );
    if ($options['smtp_auth'] === 'true') {
        $checks[__('SMTP username configured', 'asmtp-mailer')] = !empty($options['smtp_username']);
        $checks[__('SMTP password saved', 'asmtp-mailer')] = !empty($options['smtp_password']);
    }
    return array(
        'sent' => (bool) $sent,
        'elapsed' => number_format_i18n($elapsed, 3),
        'to' => $to,
        'subject' => $subject,
        'host' => $options['smtp_host'],
        'port' => $options['smtp_port'],
        'encryption' => $options['type_of_encryption'],
        'auth' => $options['smtp_auth'],
        'from' => trim($options['from_name'] . ' <' . (!empty($options['from_email']) ? $options['from_email'] : asmtp_mailer_get_default_from_email()) . '>'),
        'reply_to' => trim($options['reply_to_name'] . ' <' . $options['reply_to_email'] . '>'),
        'checks' => $checks,
        'debug' => $debug_lines,
        'error' => $error,
    );
}

function asmtp_mailer_render_test_analysis($analysis) {
    ?>
    <div class="asmtp-mailer-analysis">
        <div class="asmtp-mailer-analysis-summary">
            <div><strong><?php esc_html_e('Result', 'asmtp-mailer'); ?></strong><span class="asmtp-mailer-status-badge asmtp-mailer-status-<?php echo $analysis['sent'] ? 'success' : 'error'; ?>"><?php echo esc_html($analysis['sent'] ? __('Sent', 'asmtp-mailer') : __('Failed', 'asmtp-mailer')); ?></span></div>
            <div><strong><?php esc_html_e('Duration', 'asmtp-mailer'); ?></strong><?php echo esc_html($analysis['elapsed']); ?>s</div>
            <div><strong><?php esc_html_e('SMTP', 'asmtp-mailer'); ?></strong><?php echo esc_html($analysis['host'] . ':' . $analysis['port']); ?></div>
            <div><strong><?php esc_html_e('Security', 'asmtp-mailer'); ?></strong><?php echo esc_html(strtoupper($analysis['encryption']) . ' / Auth ' . $analysis['auth']); ?></div>
        </div>
        <?php if (!empty($analysis['error'])) : ?>
            <div class="asmtp-mailer-analysis-error"><?php echo esc_html($analysis['error']); ?></div>
        <?php endif; ?>
        <h3><?php esc_html_e('Configuration Checks', 'asmtp-mailer'); ?></h3>
        <div class="asmtp-mailer-check-grid">
            <?php foreach ($analysis['checks'] as $label => $passed) : ?>
                <div class="asmtp-mailer-check <?php echo $passed ? 'is-passed' : 'is-failed'; ?>">
                    <span class="dashicons <?php echo $passed ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                    <?php echo esc_html($label); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <h3><?php esc_html_e('Redacted SMTP Conversation', 'asmtp-mailer'); ?></h3>
        <pre class="asmtp-mailer-debug-output"><?php echo esc_html(empty($analysis['debug']) ? __('No SMTP debug output was captured.', 'asmtp-mailer') : implode("\n", $analysis['debug'])); ?></pre>
    </div>
    <?php
}

$GLOBALS['asmtp_mailer'] = new ASMTP_MAILER();

function asmtp_mailer_capture_mail_context($null, $atts) {
    $GLOBALS['asmtp_mailer_current_mail'] = is_array($atts) ? $atts : array();
    unset($GLOBALS['asmtp_mailer_phpmailer_snapshot']);
    return $null;
}

function asmtp_mailer_filter_from_email($from_email) {
    $options = asmtp_mailer_normalize_options(asmtp_mailer_get_option());
    $should_apply_from = !asmtp_mailer_current_mail_has_header('from') || !empty($options['force_from_email']) || !empty($options['force_from_address']);
    if ($should_apply_from && !empty($options['from_email']) && is_email($options['from_email'])) {
        return $options['from_email'];
    }
    if ($should_apply_from && empty($options['from_email'])) {
        return asmtp_mailer_get_default_from_email();
    }
    return $from_email;
}

function asmtp_mailer_filter_from_name($from_name) {
    $options = asmtp_mailer_normalize_options(asmtp_mailer_get_option());
    $should_apply_from = !asmtp_mailer_current_mail_has_header('from') || !empty($options['force_from_name']) || !empty($options['force_from_address']);
    if ($should_apply_from && !empty($options['from_name'])) {
        return $options['from_name'];
    }
    return $from_name;
}

function asmtp_mailer_current_mail_has_header($header_name) {
    $mail = isset($GLOBALS['asmtp_mailer_current_mail']) && is_array($GLOBALS['asmtp_mailer_current_mail']) ? $GLOBALS['asmtp_mailer_current_mail'] : array();
    $headers = isset($mail['headers']) ? $mail['headers'] : array();
    if (empty($headers)) {
        return false;
    }
    if (!is_array($headers)) {
        $headers = explode("\n", str_replace("\r\n", "\n", $headers));
    }
    foreach ($headers as $header) {
        if (stripos(trim((string) $header), $header_name . ':') === 0) {
            return true;
        }
    }
    return false;
}

function asmtp_mailer_get_default_from_email() {
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    $host = $host ? preg_replace('/^www\./', '', strtolower($host)) : 'localhost.localdomain';
    return 'asmtp-mailer@' . $host;
}

function asmtp_mailer_configure_phpmailer($phpmailer) {
    $options = asmtp_mailer_normalize_options(asmtp_mailer_get_option());
    if (empty($options['smtp_host'])) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $options['smtp_host'];
    $phpmailer->Port = absint($options['smtp_port']);
    $phpmailer->SMTPAuth = $options['smtp_auth'] === 'true';
    $phpmailer->SMTPAutoTLS = $options['type_of_encryption'] === 'tls';
    $phpmailer->SMTPSecure = $options['type_of_encryption'] === 'none' ? '' : $options['type_of_encryption'];

    if ($phpmailer->SMTPAuth) {
        $phpmailer->Username = $options['smtp_username'];
        $phpmailer->Password = asmtp_mailer_decrypt_secret($options['smtp_password']);
    }

    asmtp_mailer_apply_reply_to($phpmailer, $options);
    asmtp_mailer_apply_email_formatting($phpmailer, $options);
    asmtp_mailer_prepare_test_debug($phpmailer);
    asmtp_mailer_store_phpmailer_snapshot($phpmailer);
}

function asmtp_mailer_apply_email_formatting($phpmailer, $options) {
    if (empty($options['convert_plain_text_to_html'])) {
        return;
    }
    $is_html = stripos((string) $phpmailer->ContentType, 'html') !== false;
    if ($is_html) {
        return;
    }

    $plain_body = $phpmailer->Body;
    $phpmailer->AltBody = $plain_body;
    $phpmailer->isHTML(true);
    $phpmailer->Body = nl2br(esc_html($plain_body));
}

function asmtp_mailer_apply_reply_to($phpmailer, $options) {
    if (empty($options['reply_to_email']) || !is_email($options['reply_to_email'])) {
        return;
    }

    $reply_to_addresses = method_exists($phpmailer, 'getReplyToAddresses') ? $phpmailer->getReplyToAddresses() : array();
    if (!empty($options['force_reply_to'])) {
        $phpmailer->clearReplyTos();
        $reply_to_addresses = array();
    }

    if (empty($reply_to_addresses)) {
        try {
            $phpmailer->addReplyTo($options['reply_to_email'], $options['reply_to_name']);
        } catch (PHPMailer\PHPMailer\Exception $e) {
            if (isset($GLOBALS['asmtp_mailer_test_context']['active'])) {
                $GLOBALS['asmtp_mailer_test_context']['error'] = $e->getMessage();
            }
        }
    }
}

function asmtp_mailer_prepare_test_debug($phpmailer) {
    if (!isset($GLOBALS['asmtp_mailer_test_context']['active']) || !$GLOBALS['asmtp_mailer_test_context']['active']) {
        return;
    }
    $phpmailer->SMTPDebug = 2;
    $phpmailer->Debugoutput = 'asmtp_mailer_capture_smtp_debug';
}

function asmtp_mailer_store_phpmailer_snapshot($phpmailer) {
    $reply_to_addresses = method_exists($phpmailer, 'getReplyToAddresses') ? $phpmailer->getReplyToAddresses() : array();
    $GLOBALS['asmtp_mailer_phpmailer_snapshot'] = array(
        'from' => trim($phpmailer->FromName . ' <' . $phpmailer->From . '>'),
        'reply_to' => asmtp_mailer_format_phpmailer_address_rows($reply_to_addresses),
        'mailer' => $phpmailer->Mailer,
        'host' => $phpmailer->Host,
        'port' => $phpmailer->Port,
        'secure' => $phpmailer->SMTPSecure,
        'auth' => $phpmailer->SMTPAuth ? 'true' : 'false',
    );
}

function asmtp_mailer_format_phpmailer_address_rows($rows) {
    $addresses = array();
    foreach ((array) $rows as $row) {
        if (is_array($row)) {
            $email = isset($row[0]) ? $row[0] : '';
            $name = isset($row[1]) ? $row[1] : '';
            $addresses[] = trim($name . ' <' . $email . '>');
        } elseif (is_string($row)) {
            $addresses[] = $row;
        }
    }
    return array_filter($addresses);
}

function asmtp_mailer_log_successful_email($mail_data) {
    $snapshot = isset($GLOBALS['asmtp_mailer_phpmailer_snapshot']) ? $GLOBALS['asmtp_mailer_phpmailer_snapshot'] : array();
    asmtp_mailer_record_email_log(array_merge($mail_data, array(
        'status' => 'success',
        'from' => isset($snapshot['from']) ? $snapshot['from'] : '',
        'reply_to' => isset($snapshot['reply_to']) ? $snapshot['reply_to'] : array(),
        'details' => __('Accepted by WordPress PHPMailer for SMTP delivery.', 'asmtp-mailer'),
    )));
}

function asmtp_mailer_log_failed_email($error) {
    $mail_data = $error instanceof WP_Error ? (array) $error->get_error_data() : array();
    $snapshot = isset($GLOBALS['asmtp_mailer_phpmailer_snapshot']) ? $GLOBALS['asmtp_mailer_phpmailer_snapshot'] : array();
    $message = $error instanceof WP_Error ? $error->get_error_message() : __('Unknown mail failure.', 'asmtp-mailer');
    if (isset($GLOBALS['asmtp_mailer_test_context']['active'])) {
        $GLOBALS['asmtp_mailer_test_context']['error'] = $message;
    }
    asmtp_mailer_record_email_log(array_merge($mail_data, array(
        'status' => 'error',
        'from' => isset($snapshot['from']) ? $snapshot['from'] : '',
        'reply_to' => isset($snapshot['reply_to']) ? $snapshot['reply_to'] : array(),
        'details' => $message,
    )));
}
