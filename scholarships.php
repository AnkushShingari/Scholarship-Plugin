<?php
/*
Plugin Name: Scholarship Plugin
Description: A plugin to provide scholarship information and managing Scholarship applications.
Version: 1.2
Author: AnkushShinari
Created For: TAS and (seazengroup.com/)
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue scripts
add_action('wp_enqueue_scripts', 'scholarship_scripts');
function scholarship_scripts() {
    wp_enqueue_script(
        'scholarship-scripts',
        plugins_url('js/script.js', __FILE__),
        array('jquery'),
        '1.0',
        true
    );
    
    wp_localize_script('scholarship-scripts', 'scholarship_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('scholarship_application_nonce')
    ));
}

// Activation hook to create tables
register_activation_hook(__FILE__, 'scholarships_create_tables');

function scholarships_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();

    $table_ss_applications = $wpdb->prefix . 'scholarship_applications';
    $sql_ss_applications = "CREATE TABLE $table_ss_applications (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        scholarship_id bigint(20) UNSIGNED NOT NULL,
        first_name varchar(255) NOT NULL,
        last_name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(20) NOT NULL,
        birthday date NOT NULL,
        file varchar(255) NOT NULL,
        date_applied datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    $table_scholarships = $wpdb->prefix . 'scholarships';
    $sql_scholarships = "CREATE TABLE $table_scholarships (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        scholarship_title varchar(255) NOT NULL,
        location varchar(255) NOT NULL,
        tagline varchar(255),
        scholarship_brief longtext NOT NULL,
        eligibility longtext NOT NULL,
        requirement longtext NOT NULL,
        application_deadline datetime NOT NULL,
        featured_image VARCHAR(255),
        status tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id)
    ) $charset_collate;";
        
    $table_scholarships_mail_addresses = $wpdb->prefix . 'scholarships_mail_addresses';
    $sql_scholarships_mail_addresses = "CREATE TABLE $table_scholarships_mail_addresses (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        scholarships_mail_address varchar(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_ss_applications);
    dbDelta($sql_scholarships);
    dbDelta($sql_scholarships_mail_addresses);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'scholarships_delete_tables');

function scholarships_delete_tables() {
    global $wpdb;
    $tables = [
        $wpdb->prefix . 'scholarship_applications',
        $wpdb->prefix . 'scholarships',
        $wpdb->prefix . 'scholarships_mail_addresses'
    ];
    $wpdb->query("DROP TABLE IF EXISTS " . implode(', ', $tables));
}

// Admin menu
add_action('admin_menu', 'scholarship_menu');

function scholarship_menu() {
    add_menu_page(
        'Scholarships',
        'Scholarships',
        'manage_options',
        'scholarships',
        'scholarships_page_content',
        'dashicons-awards',
        6
    );
}

// Admin page content
function scholarships_page_content() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Scholarships Management', 'scholarships'); ?></h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=scholarships&tab=scholarships" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'scholarships' ? 'nav-tab-active' : ''; ?>">
                Scholarships
            </a>
            <a href="?page=scholarships&tab=applications" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'applications' ? 'nav-tab-active' : ''; ?>">
                Applications
            </a>
            <a href="?page=scholarships&tab=email_addresses" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'email_addresses' ? 'nav-tab-active' : ''; ?>">
                Email Addresses
            </a>
        </h2>

        <div class="tab-content">
            <?php
            $current_tab = $_GET['tab'] ?? 'scholarships';
            switch ($current_tab) {
                case 'applications': scholarships_applications_content(); break;
                case 'email_addresses': scholarships_email_addresses_content(); break;
                case 'view_scholarship': 
                    if (isset($_GET['scholarship_id'])) {
                        scholarships_view_scholarship_content($_GET['scholarship_id']);
                    }
                    break;
                case 'edit_scholarship': 
                    if (isset($_GET['scholarship_id'])) {
                        scholarships_edit_scholarship_content($_GET['scholarship_id']);
                    }
                    break;
                default: scholarships_list_content();
            }
            ?>
        </div>
    </div>
    <?php
}

// Include list content
function scholarships_list_content() {
    include plugin_dir_path(__FILE__) . 'scholarship_list.php';
}

// Applications content
function scholarships_applications_content() {
    include plugin_dir_path(__FILE__) . 'scholarship_applications.php';
}

// Email addresses content
function scholarships_email_addresses_content() {
    global $wpdb;
    $table_addresses = $wpdb->prefix . 'scholarships_mail_addresses';
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_address'])) {
        $email = sanitize_email($_POST['scholarships_mail_address']);
        
        if (!is_email($email)) {
            echo '<div class="error"><p>Invalid email address format!</p></div>';
        } else {
            $exists = $wpdb->get_var("SELECT id FROM $table_addresses LIMIT 1");
            if ($exists) {
                $wpdb->update($table_addresses, ['scholarships_mail_address' => $email], ['id' => 1]);
            } else {
                $wpdb->insert($table_addresses, ['scholarships_mail_address' => $email]);
            }
            echo '<div class="updated"><p>Email address updated successfully!</p></div>';
        }
    }

    $current_email = $wpdb->get_var("SELECT scholarships_mail_address FROM $table_addresses LIMIT 1");
    ?>
    <div class="wrap">
        <form method="post">
            <h2>Scholarship Notification Email</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="scholarships_mail_address">Email Address</label></th>
                    <td>
                        <input type="email" name="scholarships_mail_address" id="scholarships_mail_address" 
                               value="<?php echo esc_attr($current_email); ?>" class="regular-text" required>
                        <p class="description">Where scholarship applications should be sent</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Email Address', 'primary', 'update_address'); ?>
        </form>
    </div>
    <?php
}

// View scholarship
function scholarships_view_scholarship_content($scholarship_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'scholarships';
    $scholarship = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d", $scholarship_id
    ));

    if ($scholarship) {
        echo '<div class="wrap">';
        echo '<a href="?page=scholarships" class="button">Back to List</a>';
        echo '<h1>' . esc_html($scholarship->scholarship_title) . '</h1>';

        echo '<table class="widefat striped" style="width: 100%; background: #fff; border-radius: 10px; overflow: hidden;">';

        // Featured Image (if available)
        if (!empty($scholarship->featured_image)) {
            echo '<tr>';
            echo '<th colspan="2" style="text-align: center; padding: 15px;">';
            echo '<img src="' . esc_url($scholarship->featured_image) . '" alt="' . esc_attr($scholarship->scholarship_title) . '" style="max-width: 100%; height: auto; border-radius: 8px;">';
            echo '</th>';
            echo '</tr>';
        }

        echo '<tr><th>Location:</th><td>' . esc_html($scholarship->location) . '</td></tr>';
        echo '<tr><th>Tagline:</th><td>' . esc_html($scholarship->tagline) . '</td></tr>';
        echo '<tr><th>Application Deadline:</th><td>' . esc_html(date('F d, Y', strtotime($scholarship->application_deadline))) . '</td></tr>';
        echo '<tr><th>Status:</th><td>' . ($scholarship->status ? '<span style="color: green;">Open</span>' : '<span style="color: red;">Closed</span>') . '</td></tr>';
        
        echo '<tr><th>Scholarship Brief:</th><td>' . wp_kses_post($scholarship->scholarship_brief) . '</td></tr>';
        echo '<tr><th>Eligibility Criteria:</th><td>' . wp_kses_post($scholarship->eligibility) . '</td></tr>';
        echo '<tr><th>Requirements:</th><td>' . wp_kses_post($scholarship->requirement) . '</td></tr>';

        echo '</table>';
        echo '</div>';
    } else {
        echo '<div class="wrap"><p>Scholarship not found.</p></div>';
    }
}




// Edit scholarship
function scholarships_edit_scholarship_content($scholarship_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'scholarships';
    $upload_dir = wp_upload_dir(); // Get upload directory

    $scholarship = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d", $scholarship_id
    ));

    if (!$scholarship) {
        echo '<div class="wrap"><p>Scholarship not found.</p></div>';
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_scholarship'])) {
        $data = [
            'scholarship_title' => sanitize_text_field($_POST['scholarship_title']),
            'location' => sanitize_text_field($_POST['location']),
            'tagline' => sanitize_text_field($_POST['tagline']),
            'scholarship_brief' => wp_kses_post($_POST['scholarship_brief']),
            'eligibility' => wp_kses_post($_POST['eligibility']),
            'requirement' => wp_kses_post($_POST['requirement']),
            'application_deadline' => sanitize_text_field($_POST['application_deadline']),
            'status' => isset($_POST['status']) ? 1 : 0
        ];

        // Handle image upload
        if (!empty($_FILES['featured_image']['name'])) {
            $uploaded_file = $_FILES['featured_image'];
            $upload_overrides = ['test_form' => false];

            $movefile = wp_handle_upload($uploaded_file, $upload_overrides);
            if ($movefile && !isset($movefile['error'])) {
                $data['featured_image'] = $movefile['url']; // Store the image URL
            } else {
                echo '<div class="error"><p>Image upload failed: ' . esc_html($movefile['error']) . '</p></div>';
            }
        }

        $wpdb->update($table, $data, ['id' => $scholarship_id]);
        echo '<div class="updated"><p>Scholarship updated!</p></div>';
        $scholarship = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $scholarship_id));
    }
    ?>

    <div class="wrap">
        <a href="?page=scholarships" class="button">Back to List</a>
        <h1>Edit Scholarship</h1>
        
        <div class="wrap">

            <form method="post" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th><label>Scholarship Title</label></th>
                        <td><input type="text" name="scholarship_title" 
                            value="<?= esc_attr($scholarship->scholarship_title) ?>" required></td>
                    </tr>

                    <tr>
                        <th><label>Location</label></th>
                        <td><input type="text" name="location" 
                            value="<?= esc_attr($scholarship->location) ?>" required></td>
                    </tr>

                    <tr>
                        <th><label>Application Deadline</label></th>
                        <td><input type="date" name="application_deadline" 
                            value="<?= esc_attr(date('Y-m-d', strtotime($scholarship->application_deadline))) ?>" required></td>
                    </tr>

                    <tr>
                        <th><label>Status</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="status" value="1" 
                                    <?= checked($scholarship->status, 1) ?>> Active
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th><label>Scholarship Brief</label></th>
                        <td><?php wp_editor($scholarship->scholarship_brief, 'scholarship_brief', 
                                ['textarea_name' => 'scholarship_brief',
                                 'textarea_rows' => 6, 
                                 'media_buttons' => false, 
                                 'quicktags' => true,
                                ]); ?>
                        </td>
                    </tr>

                    <tr>
                        <th><label>Eligibility Criteria</label></th>
                        <td><?php wp_editor($scholarship->eligibility, 'eligibility', 
                                ['textarea_name' => 'eligibility', 
                                'textarea_rows' => 6, 
                                'media_buttons' => false, 
                                'quicktags' => true,
                               ]); ?>
                        </td>
                    </tr>

                    <tr>
                        <th><label>Requirements</label></th>
                        <td><?php wp_editor($scholarship->requirement, 'requirement', 
                                ['textarea_name' => 'requirement', 
                                'textarea_rows' => 6, 
                                'media_buttons' => false, 
                                'quicktags' => true,
                               ]); ?>
                        </td>
                    </tr>

                    <!-- Featured Image Section -->
                    <tr>
                        <th><label>Featured Image</label></th>
                        <td>
                            <?php if (!empty($scholarship->featured_image)) : ?>
                                <div>
                                    <img width="300" src="<?= esc_url($scholarship->featured_image) ?>" 
                                        style="max-width: 300px; height: auto; display: block; margin-bottom: 10px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="featured_image">
                            <p class="description">Upload a new featured image to replace the current one.</p>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2">
                            <?php submit_button('Update Scholarship', 'primary', 'update_scholarship'); ?>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
    <?php
}



// Search shortcode
add_shortcode('scholarships_search', 'scholarships_search_shortcode');
function scholarships_search_shortcode() {
    ob_start(); ?>
<style>
:root {
    --sp-primary-color: #0C3484;
    --sp-secondary-color: #FFFFFF;
    --sp-accent-color: #0C3484;
    --sp-text-color: #000000;
    --sp-hover-brightness: 1.15;
}

.scholarship-search {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
    color: var(--sp-text-color);
    padding: 20px 0;
    position: relative;
}

/* Left Side - Filters */
.scholarship-filters {
    background-color: var(--sp-secondary-color);
    padding: 1.5rem;
    border-radius: 8px;
    height: fit-content;
    position: sticky;
    top: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.scholarship-filters h3 {
    color: var(--sp-primary-color);
    font-size: 1.5rem;
    margin: 0 0 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--sp-accent-color);
}

/* Right Side - Scholarships List */
.scholarship-list {
    background-color: var(--sp-secondary-color);
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.scholarship-item {
    margin-bottom: 2rem;
    border-radius: 8px;
    overflow: hidden;
    background-color: rgba(0,0,0,0.15);
}

.scholarship-accordion {
    background-color: rgba(245, 245, 245, 1);
    color: var(--sp-text-color);
    cursor: pointer;
    padding: 1.5rem;
    width: 100%;
    text-align: left;
    border: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.scholarship-header {
    flex: 1;
    margin-right: 2rem;
}

.scholarship-title {
    font-size: 1.5rem;
    font-weight: bold;
    margin: 0 0 0.5rem;
    color: var(--sp-primary-color);
}

.scholarship-meta {
    display: flex;
    gap: 1.5rem;
    color: var(--sp-text-color);;
    font-size: 1rem;
}

.scholarship-details {
    display: none;
    padding: 2rem;
    background-color: rgba(245, 245, 245, 0.6);
}
.scholarship-item h3{
    color: var(--sp-text-color);
}

/* ------------------
APPLICATION FORM STYLES
------------------- */
.application-form {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid var(--sp-accent-color);
}

.form-group {
    position: relative;
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.8rem;
    color: rgba(0,0,0,0.9);
    font-size: 0.95rem;
}

.form-group input:not([type="checkbox"]),
.form-group input[type="date"],
.form-group input[type="file"] {
    width: 100%;
    padding: 1rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid #2E79DB;
    color: var(--sp-text-color);
    border-radius: 6px;
    transition: all 0.3s ease;
}

.form-group input:focus {
    border-color: var(--sp-primary-color);;
    box-shadow: 0 0 8px rgba(39, 39, 96, 0.15);
    outline: none;
}

/* File Upload Styling */
.file-upload-wrapper {
    position: relative;
    overflow: hidden;
}

.file-upload-wrapper input[type="file"] {
    padding: 1rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--sp-accent-color);
    border-radius: 6px;
}

input[type="file"]::file-selector-button {
    background-color: #2E79DB !important;
    color: var(--sp-text-color);
    border: none;
    padding: 0.8rem 1.5rem;
    border-radius: 4px;
    margin-right: 1rem;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

input[type="file"]::file-selector-button:hover {
    background-color: var(--sp-primary-color);
}

/* Date Input Styling */
input[type="date"] {
    position: relative;
}
input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(34%) sepia(75%) saturate(500%) hue-rotate(180deg);
    cursor: pointer;
}

/* Checkbox Styling */
.terms-checkbox {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin: 1.5rem 0;
}

.terms-checkbox label {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.4;
}

.terms-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--sp-primary-color);
    flex-shrink: 0;
}

/* Submit Button */
.form-submit {
    grid-column: 1 / -1;
    text-align: right;
}

button[type="submit"] {
    background-color: var(--sp-primary-color);
    color: #ffffff;
    padding: 1.2rem 3rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    text-transform: uppercase;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.8rem;
}

button[type="submit"]:hover {
    background-color: #0C3484;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

/* Validation Styles */
.form-group .error-message {
    display: none;
    color: #e74c3c;
    font-size: 0.85rem;
    margin-top: 0.5rem;
}

.form-group.error input {
    border-color: #e74c3c !important;
}

.form-group.error .error-message {
    display: block;
}

/* Success/Error Messages */
.form-response {
    grid-column: 1 / -1;
    padding: 1.5rem;
    border-radius: 6px;
    margin: 1rem 0;
}

.form-response.success {
    background-color: rgba(39, 39, 96, 0.15);
    color: #27ae60;
    border: 2px solid #27ae60;
}

.form-response.error {
    background-color: rgba(39, 39, 96, 0.15);
    color: #e74c3c;
    border: 2px solid #e74c3c;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .scholarship-search {
        grid-template-columns: 1fr;
    }
    
    .scholarship-filters {
        position: static;
        margin-bottom: 2rem;
    }
    
    .scholarship-accordion {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .scholarship-meta {
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 768px) {
    .application-form {
        grid-template-columns: 1fr;
    }
    
    .scholarship-list {
        padding: 1rem;
    }
    
    .scholarship-details {
        padding: 1.5rem;
    }
}

/* Additional States */
.scholarship-accordion:hover, .scholarship-accordion:focus {
    background-color: rgba(245, 245, 245, 0.7);
}

.scholarship-accordion.active {
    background-color: rgba(245, 245, 245, 0.7);
    border-left: 4px solid var(--sp-primary-color);
}

.success-message {
    background-color: rgba(39, 174, 96, 0.15);
    color: #27ae60;
    padding: 1.5rem;
    border-radius: 6px;
    margin: 2rem 0;
    border: 2px solid #27ae60;
}

.error {
    color: #e74c3c;
    padding: 1rem;
    background-color: rgba(231, 76, 60, 0.1);
    border-radius: 6px;
    margin: 1rem 0;
}

input[type="file"]::file-selector-button {
    background-color: var(--sp-accent-color) !important;
    color: var(--sp-secondary-color) !important;
    border: none;
    padding: 0.8rem 1.2rem;
    border-radius: 6px;
    margin-right: 1rem;
    cursor: pointer;
    transition: background-color 0.3s ease;
}


.terms-checkbox {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin: 1.5rem 0;
}

.terms-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--sp-primary-color);
}
</style>
    <div class="scholarship-search">
        <div class="scholarship-list" id="scholarship-list"></div>
    </div>
    <?php
    return ob_get_clean();
}

// AJAX filter
add_action('wp_ajax_filter_scholarships', 'filter_scholarships_ajax');
add_action('wp_ajax_nopriv_filter_scholarships', 'filter_scholarships_ajax');
function filter_scholarships_ajax() {
    check_ajax_referer('scholarship_application_nonce', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . 'scholarships';
    $countries = $_POST['countries'] ?? [];
    
    $query = "SELECT * FROM $table WHERE status = 1";

    $scholarships = $wpdb->get_results($query);

    if ($scholarships) {
        foreach ($scholarships as $s) {
            $featured_image = esc_url($s->featured_image); // Ensure safe output of URL
            
            echo '<div class="scholarship-item">
                <button class="scholarship-accordion">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <img src="' . $featured_image . '" alt="Featured Image" style="width: 250px; height: 200px; object-fit: cover; border-radius: 8px;">
                        <div class="scholarship-header">
                            <h3 class="scholarship-title">' . esc_html($s->scholarship_title) . '</h3>
                            <div class="scholarship-meta">
                                <span><strong>Location:</strong> ' . esc_html($s->location) . '</span>
                                <span><strong>Deadline:</strong> ' . esc_html(date('M j, Y', strtotime($s->application_deadline))) . '</span>
                            </div>
                        </div>
                    </div>
                </button>
                <div class="scholarship-details">
                    <h3><strong>About Scholarship:</strong></h3>
                    <div>' . wp_kses_post($s->scholarship_brief) . '</div><br/>
                    <h3><strong>Eligibility:</strong></h3>
                    <div>' . wp_kses_post($s->eligibility) . '</div><br/>
                    <h3><strong>Requirements:</strong></h3>
                    <div>' . wp_kses_post($s->requirement) . '</div><br/>
                    <h3><strong>Apply Now:</strong></h3>
                    <form class="scholarship-application" method="post" enctype="multipart/form-data">
                        ' . wp_nonce_field('scholarship_application_nonce', 'security', true, false) . '
                        <input type="hidden" name="action" value="submit_scholarship_application">
                        <input type="hidden" name="scholarship_id" value="' . esc_attr($s->id) . '">
                        <div class="form-group"><input type="text" name="first_name" placeholder="First Name" required></div>
                        <div class="form-group"><input type="text" name="last_name" placeholder="Last Name" required></div>
                        <div class="form-group"><input type="email" name="email" placeholder="Email" required></div>
                        <div class="form-group"><input type="tel" name="phone" placeholder="Phone" pattern="[0-9]{10}" required></div>
                        <em>Date of Birth</em>
                        <div class="form-group"><input type="date" name="birthday" placeholder="Birth Date" required></div>
                        <div class="form-group"><input type="file" name="document" accept=".pdf,.doc,.docx" required></div>
                        <div class="form-group terms">
                            <label><input type="checkbox" name="terms" required> I agree to the terms and conditions</label>
                        </div>
                        <button type="submit">Apply Now</button>
                    </form>
                </div>
            </div>';
        }
    } else {
        echo '<p>No scholarships found matching your criteria.</p>';
    }
    wp_die();
}


// Application handler
add_action('wp_ajax_submit_scholarship_application', 'handle_scholarship_application');
add_action('wp_ajax_nopriv_submit_scholarship_application', 'handle_scholarship_application');
function handle_scholarship_application() {
    check_ajax_referer('scholarship_application_nonce', 'security');

    global $wpdb;
    $data = [
        'scholarship_id' => intval($_POST['scholarship_id']),
        'first_name' => sanitize_text_field($_POST['first_name']),
        'last_name' => sanitize_text_field($_POST['last_name']),
        'email' => sanitize_email($_POST['email']),
        'phone' => sanitize_text_field($_POST['phone']),
        'birthday' => sanitize_text_field($_POST['birthday']),
        'file' => ''
    ];

    if (!empty($_FILES['document'])) {
        $upload = wp_handle_upload($_FILES['document'], ['test_form' => false]);
        if ($upload && !isset($upload['error'])) {
            $data['file'] = $upload['url'];
        } else {
            wp_send_json_error(['message' => 'File upload failed: ' . ($upload['error'] ?? 'Unknown error')]);
        }
    }

    $table = $wpdb->prefix . 'scholarship_applications';
    $result = $wpdb->insert($table, $data);

    if ($result) {
        // Send notifications
        $to = $wpdb->get_var("SELECT scholarships_mail_address FROM {$wpdb->prefix}scholarships_mail_addresses LIMIT 1");
        if ($to) {
            wp_mail($to, 'New Scholarship Application', print_r($data, true));
        }
        wp_mail($data['email'], 'Application Received', 'Thank you for your scholarship application!');
        
        wp_send_json_success(['message' => 'Application submitted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);
    }
}
