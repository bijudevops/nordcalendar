<?php
/**
 * Plugin Name: Super Calendar by NORD
 * Description: Elegant booking calendar with 1-hour slots (8:00–19:00), monthly view, weekend support, backend logs, and email notifications.
 * Version: 1.0.0
 * Author: ChatGPT (for NORD)
 * Text Domain: super-calendar-by-nord
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Super_Calendar_By_Nord {
    const VERSION = '1.0.3';
    const OPTION_EMAIL = 'scbn_notify_email';
    const TABLE_NAME = 'scbn_bookings';

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'on_activate'));
        add_action('init', array($this, 'register_assets'));
        add_shortcode('super_calendar', array($this, 'shortcode'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public static function tz() {
        $tz_string = get_option('timezone_string');
        if (!$tz_string) {
            $offset  = get_option('gmt_offset');
            $hours   = (int) $offset;
            $minutes = abs(($offset - $hours) * 60);
            $tz_string = sprintf('%+03d:%02d', $hours, $minutes);
        }
        return $tz_string ?: 'Pacific/Auckland';
    }

    public function on_activate() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            phone VARCHAR(64) NULL,
            email VARCHAR(191) NOT NULL,
            booking_date DATE NOT NULL,
            slot_hour TINYINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_date_slot (booking_date, slot_hour)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function register_assets() {
        $v = self::VERSION;
        wp_register_style('scbn-style', plugins_url('public/css/style.css', __FILE__), array(), $v);
        wp_register_script('scbn-script', plugins_url('public/js/app.js', __FILE__), array('wp-i18n'), $v, true);
    }

    public function shortcode($atts = array()) {
        wp_enqueue_style('scbn-style');
        wp_enqueue_script('scbn-script');
        wp_localize_script('scbn-script', 'SCBN', array(
            'restUrl' => esc_url_raw( rest_url('super-calendar/v1') ),
            'nonce'   => wp_create_nonce('wp_rest'),
            'theme'   => '#66BB6A',
            'tz'      => self::tz(),
            'strings' => array(
                'selectDate' => __('Select a date', 'super-calendar-by-nord'),
                'selectTime' => __('Select a time', 'super-calendar-by-nord'),
                'book'       => __('Book Appointment', 'super-calendar-by-nord'),
                'name'       => __('Name', 'super-calendar-by-nord'),
                'phone'      => __('Phone', 'super-calendar-by-nord'),
                'email'      => __('Email', 'super-calendar-by-nord'),
                'success'    => __('Thank you for your booking. A NORD consultant will be in touch at your scheduled time.', 'super-calendar-by-nord'),
                'taken'      => __('This slot is already booked.', 'super-calendar-by-nord'),
                'invalid'    => __('Please fill all required fields and choose a slot.', 'super-calendar-by-nord'),
            ),
        ));
        ob_start(); ?>
        <div class="scbn-container">
           <div id="scbn-root"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function admin_menu() {
        add_menu_page(
            __('Super Calendar', 'super-calendar-by-nord'),
            __('Super Calendar', 'super-calendar-by-nord'),
            'manage_options',
            'scbn-admin',
            array($this, 'admin_page'),
            'dashicons-calendar-alt',
            26
        );
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // Handle email save
        if (isset($_POST['scbn_email']) && check_admin_referer('scbn_save_email')) {
            update_option(self::OPTION_EMAIL, sanitize_email($_POST['scbn_email']));
            echo '<div class="updated notice"><p>Email updated.</p></div>';
        }

        $email = esc_attr( get_option(self::OPTION_EMAIL, get_bloginfo('admin_email')) );
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY booking_date DESC, slot_hour DESC LIMIT 500");
        ?>
        <div class="wrap">
            <h1>Super Calendar – Bookings</h1>
            <form method="post" style="margin:12px 0;">
                <?php wp_nonce_field('scbn_save_email'); ?>
                <label for="scbn_email"><strong>Notification email:</strong></label>
                <input type="email" id="scbn_email" name="scbn_email" value="<?php echo $email; ?>" style="min-width:320px;"/>
                <button class="button button-primary">Save</button>
            </form>
            <table class="widefat striped">
                <thead>
                    <tr><th>ID</th><th>Date</th><th>Time</th><th>Name</th><th>Phone</th><th>Email</th><th>Created</th></tr>
                </thead>
                <tbody>
                <?php if ($rows): foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo (int)$r->id; ?></td>
                        <td><?php echo esc_html($r->booking_date); ?></td>
                        <td><?php echo esc_html(sprintf('%02d:00–%02d:00', $r->slot_hour, $r->slot_hour + 1)); ?></td>
                        <td><?php echo esc_html($r->name); ?></td>
                        <td><?php echo esc_html($r->phone); ?></td>
                        <td><?php echo esc_html($r->email); ?></td>
                        <td><?php echo esc_html($r->created_at); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7">No bookings yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function register_routes() {
        register_rest_route('super-calendar/v1', '/bookings', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_bookings'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'year'  => array('required' => true, 'validate_callback' => 'is_numeric'),
                    'month' => array('required' => true, 'validate_callback' => 'is_numeric'),
                ),
            ),
            array(
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_booking'),
                'permission_callback' => function() {
                    return wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest');
                },
            ),
        ));
    }

    public function get_bookings($request) {
        global $wpdb;
        $year  = intval($request->get_param('year'));
        $month = intval($request->get_param('month'));
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-d', strtotime("$start +1 month"));
        $table = $wpdb->prefix . self::TABLE_NAME;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT booking_date, slot_hour FROM $table WHERE booking_date >= %s AND booking_date < %s",
            $start, $end
        ));
        $out = array();
        foreach ($rows as $r) {
            $out[$r->booking_date][] = intval($r->slot_hour);
        }
        return rest_ensure_response($out);
    }

    protected function is_past_date($date) {
        $site_tz = self::tz();
        try { $today = new DateTime('now', new DateTimeZone($site_tz)); }
        catch (Exception $e) { $today = new DateTime('now'); }
        $today->setTime(0,0,0);
        try { $d = new DateTime($date, new DateTimeZone($site_tz)); }
        catch (Exception $e) { $d = new DateTime($date); }
        $d->setTime(0,0,0);
        return $d < $today;
    }

    public function create_booking($request) {
        $name   = sanitize_text_field($request->get_param('name'));
        $phone  = sanitize_text_field($request->get_param('phone'));
        $email  = sanitize_email($request->get_param('email'));
        $date   = sanitize_text_field($request->get_param('date'));
        $hour   = intval($request->get_param('hour'));

        if (!$name || !$email || !$date || $hour < 8 || $hour > 18) {
            return new WP_Error('invalid', 'Invalid fields.', array('status' => 400));
        }
        if ($this->is_past_date($date)) {
            return new WP_Error('past', 'Cannot book past dates.', array('status' => 400));
        }

            return new WP_Error('invalid', 'Invalid fields.', array('status' => 400));
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $now = current_time('mysql');
        $inserted = $wpdb->insert($table, array(
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'booking_date' => $date,
            'slot_hour' => $hour,
            'created_at' => $now,
        ), array('%s','%s','%s','%s','%d','%s'));

        if (!$inserted) {
            return new WP_Error('taken', 'This slot is already booked or could not be saved.', array('status' => 409));
        }

        // Email notification
        $to = get_option(self::OPTION_EMAIL, get_bloginfo('admin_email'));
        $subject = sprintf('[Super Calendar] New booking %s %02d:00', $date, $hour);
        $message = "New booking received:\n\nName: $name\nPhone: $phone\nEmail: $email\nDate: $date\nTime: " . sprintf('%02d:00–%02d:00', $hour, $hour+1) . "\n\n— Super Calendar by NORD";
        wp_mail($to, $subject, $message);

        return rest_ensure_response(array('ok' => true));
    }
}

new Super_Calendar_By_Nord();
