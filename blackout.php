<?php
/**
 * Plugin Name:       Blackout mode
 * Description:       Zamenjuje sve stranice sajta prilagodljivom stranicom sa logoom i tekstom u određenom vremenskom periodu.
 * Version:           2.0.0
 * Requires at least: 5.3
 * Requires PHP:      7.4
 * Author:            Geek Area
 * Author URI:        https://geekarea.net/
 * License:           GPL-2.0-or-later
 * Text Domain:       blackout-mode
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BLACKOUT_MODE_VERSION', '2.0.0' );
define( 'BLACKOUT_MODE_OPTION', 'blackout_mode_options' );

/* -------------------------------------------------------------------------
 * Options
 * ---------------------------------------------------------------------- */

// Font presets. The chosen preset is also the fallback stack for a Google Font.
function blackout_mode_font_stacks() {
    return [
        'system'    => [ 'label' => 'System UI',       'stack' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif' ],
        'arial'     => [ 'label' => 'Arial',           'stack' => 'Arial, Helvetica, sans-serif' ],
        'helvetica' => [ 'label' => 'Helvetica',       'stack' => '"Helvetica Neue", Helvetica, Arial, sans-serif' ],
        'verdana'   => [ 'label' => 'Verdana',         'stack' => 'Verdana, Geneva, Tahoma, sans-serif' ],
        'georgia'   => [ 'label' => 'Georgia',         'stack' => 'Georgia, "Times New Roman", Times, serif' ],
        'times'     => [ 'label' => 'Times New Roman', 'stack' => '"Times New Roman", Times, serif' ],
        'courier'   => [ 'label' => 'Courier New',     'stack' => '"Courier New", Courier, monospace' ],
        'mono'      => [ 'label' => 'Monospace',       'stack' => 'ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace' ],
    ];
}

// Defaults. The suggested period starts at the next full hour and lasts one hour.
function blackout_mode_defaults() {
    static $defaults = null;
    if ( null !== $defaults ) {
        return $defaults;
    }

    $now   = new DateTimeImmutable( 'now', wp_timezone() );
    $start = $now->setTime( (int) $now->format( 'G' ) + 1, 0 );
    $end   = $start->modify( '+1 hour' );

    $defaults = [
        'enabled'       => 0,
        'bypass_admins' => 1,
        'start_time'    => $start->format( 'Y-m-d\TH:i' ),
        'end_time'      => $end->format( 'Y-m-d\TH:i' ),
        'custom_text'   => '<h1>Sajt je privremeno nedostupan</h1><p>Vraćamo se uskoro. Hvala na strpljenju.</p>',
        'logo'          => blackout_mode_theme_logo_url(),
        'bg_color'      => '#000000',
        'text_color'    => '#ffffff',
        'heading_color' => '#ffffff',
        'link_color'    => '#bbbbbb',
        'font_family'   => 'system',
        'google_font'   => '',
        'font_size'     => 18,
        'content_width' => 720,
        'custom_css'    => '',
    ];

    return $defaults;
}

function blackout_mode_get_options() {
    $saved = get_option( BLACKOUT_MODE_OPTION, [] );
    return wp_parse_args( is_array( $saved ) ? $saved : [], blackout_mode_defaults() );
}

// Logo set in the theme customizer, used as the default logo.
function blackout_mode_theme_logo_url() {
    $logo_id = get_theme_mod( 'custom_logo' );
    if ( empty( $logo_id ) ) {
        return '';
    }
    $image = wp_get_attachment_image_src( $logo_id, 'full' );
    return $image ? $image[0] : '';
}

// Converts a stored "Y-m-d\TH:i" value (site timezone) to a Unix timestamp.
function blackout_mode_to_timestamp( $value ) {
    if ( empty( $value ) ) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
    return $date ? $date->getTimestamp() : null;
}

/**
 * Whether the blackout page should be shown right now.
 * Runs on every front-end request, so it reads the raw autoloaded option and
 * returns before any date handling when the plugin is switched off.
 * An empty start or end time leaves that side of the period open.
 */
function blackout_mode_is_active() {
    static $active = null;
    if ( null !== $active ) {
        return $active;
    }

    $options = get_option( BLACKOUT_MODE_OPTION );
    if ( empty( $options['enabled'] ) ) {
        return $active = false;
    }

    $now    = time();
    $start  = blackout_mode_to_timestamp( $options['start_time'] ?? '' );
    $end    = blackout_mode_to_timestamp( $options['end_time'] ?? '' );
    $active = ( null === $start || $now >= $start ) && ( null === $end || $now < $end );

    return $active;
}

/* -------------------------------------------------------------------------
 * Front end
 * ---------------------------------------------------------------------- */

function blackout_mode_template_redirect() {
    if ( is_robots() || ( function_exists( 'is_favicon' ) && is_favicon() ) ) {
        return;
    }

    // Admins can look at the page with the saved settings at any time.
    if ( isset( $_GET['blackout_preview'] ) && current_user_can( 'manage_options' ) ) {
        blackout_mode_render( blackout_mode_get_options(), true );
    }

    if ( ! blackout_mode_is_active() ) {
        return;
    }

    $options = blackout_mode_get_options();
    if ( ! empty( $options['bypass_admins'] ) && current_user_can( 'manage_options' ) ) {
        return;
    }

    blackout_mode_render( $options );
}
add_action( 'template_redirect', 'blackout_mode_template_redirect', 0 );

function blackout_mode_css( $options ) {
    $stacks = blackout_mode_font_stacks();
    $font   = $stacks[ $options['font_family'] ]['stack'] ?? $stacks['system']['stack'];
    if ( '' !== $options['google_font'] ) {
        $font = '"' . $options['google_font'] . '", ' . $font;
    }

    $vars = [
        '--blackout-bg'        => $options['bg_color'],
        '--blackout-text'      => $options['text_color'],
        '--blackout-heading'   => $options['heading_color'],
        '--blackout-link'      => $options['link_color'],
        '--blackout-font'      => $font,
        '--blackout-font-size' => absint( $options['font_size'] ) . 'px',
        '--blackout-width'     => absint( $options['content_width'] ) . 'px',
    ];

    $css = ':root{';
    foreach ( $vars as $name => $value ) {
        $css .= $name . ':' . $value . ';';
    }
    $css .= '}';

    $css .= '
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0}
body{
    min-height:100vh;
    min-height:100dvh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:clamp(24px,6vw,64px) clamp(20px,5vw,48px);
    background:var(--blackout-bg);
    color:var(--blackout-text);
    font-family:var(--blackout-font);
    font-size:var(--blackout-font-size);
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
}
.blackout-content{width:100%;max-width:var(--blackout-width);text-align:center;overflow-wrap:break-word}
.blackout-logo{display:block;width:auto;height:auto;max-width:min(100%,400px);max-height:35vh;margin:0 auto 2rem}
.blackout-text h1,.blackout-text h2,.blackout-text h3,.blackout-text h4{color:var(--blackout-heading);line-height:1.2;margin:0 0 .6em}
.blackout-text h1{font-size:clamp(2rem,6vw,3rem)}
.blackout-text h2{font-size:clamp(1.5rem,4.5vw,2rem)}
.blackout-text h3{font-size:clamp(1.25rem,3.5vw,1.5rem)}
.blackout-text p,.blackout-text ul,.blackout-text ol{margin:0 0 1em}
.blackout-text>:last-child{margin-bottom:0}
.blackout-text a{color:var(--blackout-link)}
.blackout-text img{max-width:100%;height:auto}';

    if ( '' !== $options['custom_css'] ) {
        $css .= "\n/* Custom CSS */\n" . $options['custom_css'];
    }

    return $css;
}

function blackout_mode_render( $options, $is_preview = false ) {
    // Ask page-caching plugins not to store this response.
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }

    if ( ! headers_sent() ) {
        if ( $is_preview ) {
            status_header( 200 );
        } else {
            // 503 tells search engines and proxies the outage is temporary.
            status_header( 503 );
            $end = blackout_mode_to_timestamp( $options['end_time'] );
            if ( $end && $end > time() ) {
                header( 'Retry-After: ' . gmdate( 'D, d M Y H:i:s', $end ) . ' GMT' );
            }
        }
        nocache_headers();
        header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
    }

    $site_name = get_bloginfo( 'name' );
    $font_url  = '';
    if ( '' !== $options['google_font'] ) {
        $font_url = 'https://fonts.googleapis.com/css2?family=' . str_replace( ' ', '+', $options['google_font'] ) . '&display=swap';
    }
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $site_name ); ?></title>
<?php if ( function_exists( 'wp_site_icon' ) ) { wp_site_icon(); } ?>
<?php if ( $font_url ) : ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?php echo esc_url( $font_url ); ?>">
<?php endif; ?>
<style><?php echo blackout_mode_css( $options ); // Values are sanitized on save. ?></style>
</head>
<body class="blackout-mode<?php echo $is_preview ? ' blackout-mode-preview' : ''; ?>">
<main class="blackout-content">
<?php if ( $options['logo'] ) : ?>
<img class="blackout-logo" src="<?php echo esc_url( $options['logo'] ); ?>" alt="<?php echo esc_attr( $site_name ); ?>">
<?php endif; ?>
<div class="blackout-text"><?php echo wp_kses_post( wpautop( $options['custom_text'] ) ); ?></div>
</main>
</body>
</html>
    <?php
    exit;
}

// Admin bar reminder for administrators who bypass the blackout.
function blackout_mode_admin_bar( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) || ! blackout_mode_is_active() ) {
        return;
    }
    $wp_admin_bar->add_node( [
        'id'    => 'blackout-mode',
        'title' => '<span style="color:#ff6b6b">&#9679;</span> ' . esc_html__( 'Blackout active', 'blackout-mode' ),
        'href'  => admin_url( 'options-general.php?page=blackout-mode' ),
    ] );
}
add_action( 'admin_bar_menu', 'blackout_mode_admin_bar', 100 );

/* -------------------------------------------------------------------------
 * Admin
 * ---------------------------------------------------------------------- */

function blackout_mode_admin_menu() {
    add_options_page(
        __( 'Blackout Mode Settings', 'blackout-mode' ),
        __( 'Blackout Mode', 'blackout-mode' ),
        'manage_options',
        'blackout-mode',
        'blackout_mode_settings_page'
    );
}
add_action( 'admin_menu', 'blackout_mode_admin_menu' );

function blackout_mode_register_settings() {
    register_setting( 'blackout_mode_options', BLACKOUT_MODE_OPTION, [
        'type'              => 'array',
        'sanitize_callback' => 'blackout_mode_sanitize',
    ] );
}
add_action( 'admin_init', 'blackout_mode_register_settings' );

function blackout_mode_sanitize( $input ) {
    $input    = is_array( $input ) ? $input : [];
    $defaults = blackout_mode_defaults();
    $output   = [];

    // Unchecked checkboxes are not submitted, so store them explicitly.
    $output['enabled']       = empty( $input['enabled'] ) ? 0 : 1;
    $output['bypass_admins'] = empty( $input['bypass_admins'] ) ? 0 : 1;

    foreach ( [ 'start_time', 'end_time' ] as $key ) {
        $value          = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
        $output[ $key ] = preg_match( '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})(:\d{2})?$/', $value, $m ) ? $m[1] : '';
    }

    $start = blackout_mode_to_timestamp( $output['start_time'] );
    $end   = blackout_mode_to_timestamp( $output['end_time'] );
    if ( $start && $end && $end <= $start ) {
        add_settings_error( BLACKOUT_MODE_OPTION, 'blackout_mode_time', __( 'End time must be after the start time. The blackout will not be shown until this is fixed.', 'blackout-mode' ) );
    }

    $output['custom_text'] = wp_kses_post( $input['custom_text'] ?? '' );
    $output['logo']        = esc_url_raw( $input['logo'] ?? '' );

    foreach ( [ 'bg_color', 'text_color', 'heading_color', 'link_color' ] as $key ) {
        $color          = sanitize_hex_color( $input[ $key ] ?? '' );
        $output[ $key ] = $color ? $color : $defaults[ $key ];
    }

    $font                  = $input['font_family'] ?? '';
    $output['font_family'] = array_key_exists( $font, blackout_mode_font_stacks() ) ? $font : $defaults['font_family'];
    $output['google_font'] = substr( trim( preg_replace( '/[^A-Za-z0-9 ]/', '', $input['google_font'] ?? '' ) ), 0, 60 );

    $output['font_size']     = min( 32, max( 12, absint( $input['font_size'] ?? $defaults['font_size'] ) ) );
    $output['content_width'] = min( 1600, max( 320, absint( $input['content_width'] ?? $defaults['content_width'] ) ) );

    // Plain CSS only: no markup, so nothing can close the <style> tag.
    $output['custom_css'] = trim( str_replace( '<', '', wp_strip_all_tags( $input['custom_css'] ?? '' ) ) );

    return $output;
}

function blackout_mode_settings_link( array $links ) {
    $links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=blackout-mode' ) ) . '">' . esc_html__( 'Settings', 'blackout-mode' ) . '</a>';
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'blackout_mode_settings_link' );

function blackout_mode_admin_assets( $hook ) {
    if ( 'settings_page_blackout-mode' !== $hook ) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'blackout-mode-admin', plugins_url( 'assets/admin.js', __FILE__ ), [ 'jquery', 'wp-color-picker' ], BLACKOUT_MODE_VERSION, true );
    wp_localize_script( 'blackout-mode-admin', 'blackoutMode', [
        'selectLogo' => __( 'Select Logo', 'blackout-mode' ),
        'useLogo'    => __( 'Use this logo', 'blackout-mode' ),
    ] );
    wp_add_inline_style( 'wp-color-picker', '#blackout_mode_logo_preview img{display:block;max-width:300px;height:auto;margin-top:10px;padding:10px;background:#000}.blackout-status{font-weight:600}.blackout-status.is-active{color:#d63638}.blackout-status.is-scheduled{color:#996800}' );
}
add_action( 'admin_enqueue_scripts', 'blackout_mode_admin_assets' );

function blackout_mode_status_text( $options ) {
    $now    = time();
    $start  = blackout_mode_to_timestamp( $options['start_time'] );
    $end    = blackout_mode_to_timestamp( $options['end_time'] );
    $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

    if ( empty( $options['enabled'] ) ) {
        return [ 'is-off', __( 'Off', 'blackout-mode' ) ];
    }
    if ( $start && $end && $end <= $start ) {
        return [ 'is-off', __( 'Invalid period (end is before start)', 'blackout-mode' ) ];
    }
    if ( $start && $now < $start ) {
        /* translators: 1: start date, 2: relative time */
        return [ 'is-scheduled', sprintf( __( 'Scheduled for %1$s (in %2$s)', 'blackout-mode' ), wp_date( $format, $start ), human_time_diff( $now, $start ) ) ];
    }
    if ( $end && $now >= $end ) {
        /* translators: %s: end date */
        return [ 'is-off', sprintf( __( 'Ended on %s', 'blackout-mode' ), wp_date( $format, $end ) ) ];
    }
    if ( $end ) {
        /* translators: %s: end date */
        return [ 'is-active', sprintf( __( 'Active until %s', 'blackout-mode' ), wp_date( $format, $end ) ) ];
    }
    return [ 'is-active', __( 'Active until switched off', 'blackout-mode' ) ];
}

function blackout_mode_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $options = blackout_mode_get_options();
    $name    = BLACKOUT_MODE_OPTION;
    $stacks  = blackout_mode_font_stacks();
    $preview = add_query_arg( 'blackout_preview', '1', home_url( '/' ) );

    list( $status_class, $status_text ) = blackout_mode_status_text( $options );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Blackout Mode Settings', 'blackout-mode' ); ?></h1>

        <p>
            <?php esc_html_e( 'Status:', 'blackout-mode' ); ?>
            <span class="blackout-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span>
            &nbsp;<a class="button" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'blackout-mode' ); ?></a>
        </p>

        <form method="post" action="options.php">
            <?php settings_fields( 'blackout_mode_options' ); ?>

            <h2 class="title"><?php esc_html_e( 'Schedule', 'blackout-mode' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Blackout Mode', 'blackout-mode' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $options['enabled'], 1 ); ?>>
                            <?php esc_html_e( 'Show the blackout page during the period below', 'blackout-mode' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="blackout_mode_start"><?php esc_html_e( 'Start Time', 'blackout-mode' ); ?></label></th>
                    <td>
                        <input type="datetime-local" id="blackout_mode_start" name="<?php echo esc_attr( $name ); ?>[start_time]" value="<?php echo esc_attr( $options['start_time'] ); ?>">
                        <p class="description"><?php esc_html_e( 'Leave empty to start immediately.', 'blackout-mode' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="blackout_mode_end"><?php esc_html_e( 'End Time', 'blackout-mode' ); ?></label></th>
                    <td>
                        <input type="datetime-local" id="blackout_mode_end" name="<?php echo esc_attr( $name ); ?>[end_time]" value="<?php echo esc_attr( $options['end_time'] ); ?>">
                        <p class="description">
                            <?php esc_html_e( 'Leave empty to keep it on until you switch it off.', 'blackout-mode' ); ?>
                            <?php
                            /* translators: %s: timezone name */
                            printf( esc_html__( 'Times use the site timezone (%s), set under Settings → General.', 'blackout-mode' ), esc_html( wp_timezone_string() ) );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Administrators', 'blackout-mode' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[bypass_admins]" value="1" <?php checked( $options['bypass_admins'], 1 ); ?>>
                            <?php esc_html_e( 'Logged-in administrators see the normal site (use Preview to see the blackout page)', 'blackout-mode' ); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <h2 class="title"><?php esc_html_e( 'Content', 'blackout-mode' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Custom Text', 'blackout-mode' ); ?></th>
                    <td>
                        <?php
                        wp_editor( $options['custom_text'], 'blackout_mode_custom_text', [
                            'textarea_name' => $name . '[custom_text]',
                            'textarea_rows' => 6,
                            'media_buttons' => false,
                        ] );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Logo', 'blackout-mode' ); ?></th>
                    <td>
                        <input type="hidden" id="blackout_mode_logo" name="<?php echo esc_attr( $name ); ?>[logo]" value="<?php echo esc_url( $options['logo'] ); ?>">
                        <button type="button" class="button" id="blackout_mode_logo_button"><?php esc_html_e( 'Select Logo', 'blackout-mode' ); ?></button>
                        <button type="button" class="button" id="blackout_mode_clear_logo_button"><?php esc_html_e( 'Clear Logo', 'blackout-mode' ); ?></button>
                        <div id="blackout_mode_logo_preview">
                            <?php if ( $options['logo'] ) : ?>
                                <img src="<?php echo esc_url( $options['logo'] ); ?>" alt="">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>

            <h2 class="title"><?php esc_html_e( 'Appearance', 'blackout-mode' ); ?></h2>
            <table class="form-table" role="presentation">
                <?php
                $colors = [
                    'bg_color'      => __( 'Background color', 'blackout-mode' ),
                    'text_color'    => __( 'Text color', 'blackout-mode' ),
                    'heading_color' => __( 'Heading color', 'blackout-mode' ),
                    'link_color'    => __( 'Link color', 'blackout-mode' ),
                ];
                $defaults = blackout_mode_defaults();
                foreach ( $colors as $key => $label ) :
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html( $label ); ?></th>
                        <td>
                            <input type="text" class="blackout-color" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $options[ $key ] ); ?>" data-default-color="<?php echo esc_attr( $defaults[ $key ] ); ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th scope="row"><label for="blackout_mode_font"><?php esc_html_e( 'Font', 'blackout-mode' ); ?></label></th>
                    <td>
                        <select id="blackout_mode_font" name="<?php echo esc_attr( $name ); ?>[font_family]">
                            <?php foreach ( $stacks as $key => $font ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $options['font_family'], $key ); ?>><?php echo esc_html( $font['label'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="blackout_mode_google_font"><?php esc_html_e( 'Google Font', 'blackout-mode' ); ?></label></th>
                    <td>
                        <input type="text" id="blackout_mode_google_font" class="regular-text" name="<?php echo esc_attr( $name ); ?>[google_font]" value="<?php echo esc_attr( $options['google_font'] ); ?>" placeholder="Roboto">
                        <p class="description"><?php esc_html_e( 'Optional. Exact family name from fonts.google.com (e.g. Roboto, Montserrat, Lora). The font above is used as the fallback. Loads the font from Google’s servers.', 'blackout-mode' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="blackout_mode_font_size"><?php esc_html_e( 'Base font size', 'blackout-mode' ); ?></label></th>
                    <td>
                        <input type="number" id="blackout_mode_font_size" class="small-text" min="12" max="32" name="<?php echo esc_attr( $name ); ?>[font_size]" value="<?php echo esc_attr( $options['font_size'] ); ?>"> px
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="blackout_mode_width"><?php esc_html_e( 'Content max width', 'blackout-mode' ); ?></label></th>
                    <td>
                        <input type="number" id="blackout_mode_width" class="small-text" min="320" max="1600" step="10" name="<?php echo esc_attr( $name ); ?>[content_width]" value="<?php echo esc_attr( $options['content_width'] ); ?>"> px
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="blackout_mode_css"><?php esc_html_e( 'Custom CSS', 'blackout-mode' ); ?></label></th>
                    <td>
                        <textarea id="blackout_mode_css" class="large-text code" rows="8" name="<?php echo esc_attr( $name ); ?>[custom_css]" placeholder=".blackout-text h1 { text-transform: uppercase; }"><?php echo esc_textarea( $options['custom_css'] ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Added after the built-in styles. Useful selectors: body, .blackout-content, .blackout-logo, .blackout-text. Variables: --blackout-bg, --blackout-text, --blackout-heading, --blackout-link, --blackout-font, --blackout-font-size, --blackout-width.', 'blackout-mode' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
