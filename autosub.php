<?php 
/**
* Plugin Name: Autosubscribe
* Plugin URI: https://indylug.org
* Description: Automatically subscribe users to forum topics
* Version: 0.01
* Author: Josh Williams
* Author https://indylug.org
* License: GPL2
*/

# TODO: Hook into bbp_remove_topic_from_all_subscriptions

defined ('ABSPATH') or die('');

# This function is called when the plugin is installed.
function autosub_activate() {
 $all_users = get_users('orderby=ID');

 # Save a backup of everyone's subscription preferences so we can restore it if
 # we decide not to use the plugin.
 require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
 global $wpdb;
 $table_name = $wpdb -> prefix . "autosub";

 # Create table for backups if it doesn't already exist
 $charset_collate = $wpdb -> get_charset_collate();
 $sql = "CREATE TABLE IF NOT EXISTS $table_name (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  user_id mediumint(9) NOT NULL,
  type varchar(20) NOT NULL,
  subscriptions longtext,
  UNIQUE KEY id (id)
 ) $charset_collate;";
 dbDelta($sql);

 # Delete old backups if they exist
 $wpdb -> delete($table_name, array('type' => 'topic'));
 $wpdb -> delete($table_name, array('type' => 'forum'));

 # Store backups of subscriptions
 foreach ($all_users as $user) {
  $user_id = $user -> ID;

  # Backup topic subscriptions
  $topic_subscriptions = get_user_option('_bbp_subscriptions', $user_id);
  if (strlen($topic_subscriptions) <= 0) continue;
  $sql = "INSERT INTO $table_name
  (user_id, type,  subscriptions)
  VALUES (
   $user_id,
   'topic',
   '$topic_subscriptions'
  );";
  dbDelta($sql);

  # Backup forum subscriptions
  $forum_subscriptions = get_user_option('_bbp_forum_subscriptions', $user_id);
  if (strlen($forum_subscriptions) <= 0) continue;
  $sql = "INSERT INTO $table_name
  (user_id, type,  subscriptions)
  VALUES (
   $user_id,
   'forum',
   '$forum_subscriptions'
  );";
  dbDelta($sql);
 }

 # Delete subscriptions because they would otherwise turn into unsubscriptions.
 foreach ($all_users as $user) {
  if (!autosub_is_user_an_exception($user -> ID)) {
   delete_user_option($user -> ID, '_bbp_subscriptions');
   delete_user_option($user -> ID, '_bbp_forum_subscriptions');
  }
 }
} register_activation_hook(__FILE__, 'autosub_activate');

# This function is called when the plugin is disabled.
function autosub_deactivate() {
 $all_users = get_users('orderby=ID');
 global $wpdb;
 $table_name = $wpdb -> prefix . "autosub";

 # Restore topic subscriptions from backups
 foreach ($all_users as $user) {
  $user_id = $user -> ID;
  if (autosub_is_user_an_exception($user_id)) continue;
  $SQL = "SELECT subscriptions FROM $table_name
          WHERE user_id = $user_id AND type = 'topic'";
  $result = $wpdb -> get_results($SQL);
 foreach ($result as $row) {
   $subscriptions = $row -> subscriptions;
   if (strlen($subscriptions) > 0) {
    update_user_option($user_id, '_bbp_subscriptions', $subscriptions);
   }
  }
 }

 # Restore forum subscriptions from backups
 foreach ($all_users as $user) {
  $user_id = $user -> ID;
  if (autosub_is_user_an_exception($user_id)) continue;
  $SQL = "SELECT subscriptions FROM $table_name
          WHERE user_id = $user_id AND type = 'forum'";
  $result = $wpdb -> get_results($SQL);
 foreach ($result as $row) {
   $subscriptions = $row -> subscriptions;
   if (strlen($subscriptions) > 0) {
    update_user_option($user_id, '_bbp_forum_subscriptions', $subscriptions);
   }
  }
 }
} register_deactivation_hook(__FILE__, 'autosub_deactivate');

# Return whether user is exempt from the inversion rule.
function autosub_is_user_an_exception($user_id) {
 if (in_array($user_id, autosub_get_exceptions())) {
  return true;
 } else {
  return false;
 }
}

# Return array of all members exempt from inversion rule.
function autosub_get_exceptions() {
 # Add user IDs here for every exception to the autosubscribe.
 return array(-1);
}

function invert_is_user_subscribed_to_topic($is_subscribed, $user_id) {
 if (autosub_is_user_an_exception($user_id)) {
  return $is_subscribed;
 } else {
  return !$is_subscribed;
 }
} add_filter('bbp_is_user_subscribed_to_topic', 'invert_is_user_subscribed_to_topic', 10, 2);

function invert_is_user_subscribed_to_forum($is_subscribed) {
 if (autosub_is_user_an_exception($user_id)) {
  return $is_subscribed;
 } else {
  return !$is_subscribed;
 }
} add_filter('bbp_is_user_subscribed_to_forum', 'invert_is_user_subscribed_to_forum', 10, 2);

function invert_is_user_subscribed($is_subscribed, $user_id, $object_id) {
 # Use this if statement when applying user options later; ie, allow user to
 # decide whether to autosubscribe to forums only, topics only, both, or none.
 #if (get_post_type($object_id) == bbp_get_topic_post_type()) {
 if (autosub_is_user_an_exception($user_id)) {
  return $is_subscribed;
 } else {
  return !$is_subscribed;
 }
}
add_filter('bbp_is_user_subscribed', 'invert_is_user_subscribed', 10, 3);

function invert_get_user_subscribe_link($html, $user_id) {
 if (autosub_is_user_an_exception($user_id)) return $html;
 if (strpos($html, "bbp_unsubscribe") != false) {
  $html = str_replace("bbp_unsubscribe", "bbp_subscribe", $html);
 } else {
  $html = str_replace("bbp_subscribe", "bbp_unsubscribe", $html);
 }
 return $html;
} add_filter('bbp_get_user_subscribe_link', 'invert_get_user_subscribe_link', 10, 2);

function invert_get_topic_subscribers($users) {
 $args = array('fields' => 'id');
 $all_users = get_users($args);
 $all_users = array_diff($all_users, autosub_get_exceptions());
 $send_to_users = array_diff($all_users, $users);
 return $send_to_users;
} add_filter('bbp_get_topic_subscribers', 'invert_get_topic_subscribers', 10, 1);

function invert_get_forum_subscribers($users) {
 $args = array('fields' => 'id');
 $all_users = get_users($args);
 $all_users = array_diff($all_users, autosub_get_exceptions());
 $send_to_users = array_diff($all_users, $users);
 for ($i = 0; $i < count($users); $i++) {
  if (autosub_is_user_an_exception($users[$i])) {
   unset($users[$i]);
  }
 }
 return $send_to_users;
} add_filter('bbp_get_forum_subscribers', 'invert_get_forum_subscribers', 10, 1);

function invert_form_topic_subscribed($checked, $user_id) {
 return "unchecked";
} add_filter('bbp_get_form_topic_subscribed', 'invert_form_topic_subscribed', 10, 2);
