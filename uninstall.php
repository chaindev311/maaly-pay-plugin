<?php
if (! defined('WP_UNINSTALL_PLUGIN')) {
  exit;
}

// Remove stored options
delete_option('maaly_api_key');
