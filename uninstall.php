<?php

/**
 * Trigger this file on Plugin uninstall 
 * 
 * @package Saksenengmu
 */

if ( ! defined('WP_UNINSTALL_PLUGIN')){ die; }


// Clear database storage data

/** opt 1*/
//$packages = get_posts( array( 'post_type' => 'package', 'numberposts' => -1 ) );
//foreach ($packages as $package){
//    wp_delete_post($package->ID, true);
//}

/** opt 2
 * Access the database via SQL
 */
global $wpdb;
/** delete post with deleted post type*/
$wpdb->query( "DELETE FROM wp_posts WHERE post_type = 'package'" );
/** delete postmeta whose posts_id doesnt exist in wp_posts after posts with post_type 'package' got deleted */
$wpdb->query( "DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT id FROM wp_posts)" );
$wpdb->query( "DELETE FROM wp_term_relationships WHERE object_id NOT IN (SELECT id FROM wp_posts)" );
