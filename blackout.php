<?php
/**
 * Plugin Name: Blackout mode
 * Description: Zamenjuje sve stranice sajta crnom pozadinom sa logoom i tekstom u određenom vremenskom periodu.
 * Version: 1.0
 * Author: Geek Area
 * Author URI: https://geekarea.net/
 */

// Add the blackout mode functionality
function blackout_mode_template_redirect() {
    $options = get_option('blackout_mode_options');
    if (!is_admin() && isset($options['enabled']) && $options['enabled']) {
        $start_time = strtotime($options['start_time']);
        $end_time = strtotime($options['end_time']);
        $current_time = current_time('timestamp');

        if ($current_time >= $start_time && $current_time <= $end_time) {
            echo '<title>' . get_bloginfo( 'name' ) . '</title>';
            echo '<style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 0;
                    background-color: black;
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    overflow: hidden;
                }
                .blackout-content {
                    text-align: center;
                }
                .blackout-content h1 {
                    font-size: 3rem;
                }
                .blackout-content h2 {
                    font-size: 2rem;
                }
                .blackout-content img {
                    max-width: 50%;
                    height: auto;
                    margin-bottom: 20px;
                }
                .blackout-content a {
                    color: grey;
                }
            </style>';
            echo '<div class="blackout-content">';
            if ($options['logo']) {
                echo '<img src="' . esc_url($options['logo']) . '" alt="Site Logo">';
            }
            echo '<div class="blackout-text">' . wp_kses_post(wpautop($options['custom_text'])) . '</div>';
            echo '</div>';
            exit;
        }
    }
}
add_action('template_redirect', 'blackout_mode_template_redirect');

// Admin menu for plugin settings
function blackout_mode_admin_menu() {
    add_options_page('Blackout Mode Settings', 'Blackout Mode', 'manage_options', 'blackout-mode', 'blackout_mode_settings_page');
}
add_action('admin_menu', 'blackout_mode_admin_menu');

// Register settings
function blackout_mode_register_settings() {
    register_setting('blackout_mode_options', 'blackout_mode_options');
}
add_action('admin_init', 'blackout_mode_register_settings');

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'apd_settings_link' );
function apd_settings_link( array $links ) {
    $url = get_admin_url() . "options-general.php?page=blackout-mode";
    $settings_link = '<a href="' . $url . '">' . __('Settings', 'blackout-mode') . '</a>';
      $links[] = $settings_link;
    return $links;
  }

// Getting default logo
function get_custom_logo_url()
{
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    if(empty($custom_logo_id)) {
        return '';
    }
    $image = wp_get_attachment_image_src( $custom_logo_id , 'full' );
    return $image[0];
}

// Settings page content
function blackout_mode_settings_page() {
    $options = get_option('blackout_mode_options', [
        'enabled' => 0,
        'start_time' => '2025-01-24T11:52',
        'end_time' => '2025-01-24T12:07',
        'custom_text' => '<h1 style="color:red;">GENERALNI ŠTRAJK</h1><h2>Više informacija na <a href="https://www.instagram.com/studenti_u_blokadi/">@studenti_u_blokadi</a></h2>',
        'logo' => get_custom_logo_url(),
    ]);
    
    wp_enqueue_media();
    ?>
    <div class="wrap">
        <h1>Blackout Mode Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('blackout_mode_options'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Blackout Mode</th>
                    <td>
                        <input type="checkbox" name="blackout_mode_options[enabled]" value="1" <?php checked($options['enabled'], 1); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Start Time</th>
                    <td>
                        <input type="datetime-local" name="blackout_mode_options[start_time]" value="<?php echo esc_attr($options['start_time']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">End Time</th>
                    <td>
                        <input type="datetime-local" name="blackout_mode_options[end_time]" value="<?php echo esc_attr($options['end_time']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Custom Text</th>
                    <td>
                        <?php
                        wp_editor(
                            $options['custom_text'],
                            'blackout_mode_custom_text',
                            [
                                'textarea_name' => 'blackout_mode_options[custom_text]',
                                'textarea_rows' => 5,
                                'media_buttons' => false,
                            ]
                        );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Logo</th>
                    <td>
                        <input type="hidden" id="blackout_mode_logo" name="blackout_mode_options[logo]" value="<?php echo esc_url($options['logo']); ?>">
                        <button type="button" class="button" id="blackout_mode_logo_button">Select Logo</button>
                        <button type="button" class="button" id="blackout_mode_clear_logo_button">Clear Logo</button>
                        <div id="blackout_mode_logo_preview">
                            <?php if ($options['logo']) : ?>
                                <img src="<?php echo esc_url($options['logo']); ?>" alt="Logo Preview" style="max-width: 300px; height: auto; margin-top: 10px;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $('#blackout_mode_logo_button').on('click', function(e) {
                e.preventDefault();
                const customUploader = wp.media({
                    title: 'Select Logo',
                    button: {
                        text: 'Use this logo'
                    },
                    multiple: false
                }).on('select', function() {
                    const attachment = customUploader.state().get('selection').first().toJSON();
                    $('#blackout_mode_logo').val(attachment.url);
                    $('#blackout_mode_logo_preview').html('<img src="' + attachment.url + '" alt="Logo Preview" style="max-width: 300px; height: auto; margin-top: 10px;">');
                }).open();
            });
            
            $('#blackout_mode_clear_logo_button').on('click', function() {
                $('#blackout_mode_logo').val('');
                $('#blackout_mode_logo_preview').html('');
            });
        });
    </script>
    <?php
}
