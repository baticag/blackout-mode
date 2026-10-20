<?php
// Removes the plugin settings when the plugin is deleted from the Plugins screen.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'blackout_mode_options' );
