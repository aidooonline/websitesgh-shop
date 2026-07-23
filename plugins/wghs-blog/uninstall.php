<?php
/**
 * Uninstall cleanup. Removes the plugin option only.
 * The Blog hub page and any created menu are intentionally left in place so
 * removing the plugin never deletes published content or breaks navigation.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
delete_option( 'tpgb_page_id' );
