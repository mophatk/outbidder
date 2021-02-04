<?php
/*
  Plugin Name: Outbidder Pierpontsauction Plugin
  Plugin URI: http://pierpontsauction.com
  Description: Awesome plugin to host auctions on your wordpress site and sell anything you want.
  Author: Pierpontsauction
  Author URI: http://pierpontsauction.com
  Version: 4.0.8
  License: GPLv2
  Copyright 2020 pierpontsauction
*/

// Recreate bid & recreate new product for outgoing.

// $buy_price = get_post_meta(esc_attr($_POST['auction_id']), 'wdm_buy_it_now', true);

//echo json_encode(array('stat' => 'inv_bid', 'bid' => 500000 ));
//wp_die();

function g_outbid_customer_auction( $ab_bid , $high_bid = 0 , $auction_id = 0){

    $auction_id = $_POST['auction_id'];
    
      $ab_name = 'Mark Gavi' ;
      $ab_email = 'markg@samplemail.com' ;
      $new_ab_bid = $ab_bid + 500 ;
      $high_bid = $high_bid ;

      //do higher bid recreation
      global $wpdb;
      $buy_price = get_post_meta($auction_id, 'wdm_buy_it_now', true);

      if ($buy_price > $ab_bid) {
          do_action('wdm_extend_auction_time', $auction_id);
          $place_bid = $wpdb->insert(
              $wpdb->prefix.'wdm_bidders',
              array(
                'name' => $ab_name,
                'email' => $ab_email,
                'auction_id' =>  $auction_id,
                'bid' => $new_ab_bid,
                'date' => date("Y-m-d H:i:s", time())
            ),
              array(
                '%s',
                '%s',
                '%d',
                '%f',
                '%s'
            )
          );
      }

				
	return $ab_bid;
}

add_filter( 'wdm_ua_modified_bid_amt', 'g_outbid_customer_auction', 3 );

/*
     * Function for post duplication. Dups appear as drafts. User is redirected to the edit screen
*/
function rd_duplicate_post_as_draft( $post_id ){

        global $wpdb;
       
        /*
         * get the original post id
         */
        //$post_id = $_POST['auction_id'] ;
        /*
         * and all the original post data then
         */
        $post = get_post( $post_id );
       
        /*
         * if you don't want current user to be the new post author,
         * then change next couple of lines to this: $new_post_author = $post->post_author;
         */
        $current_user = wp_get_current_user();
        $new_post_author = $current_user->ID;
       
        /*
         * if post data exists, create the post duplicate
         */
        if (isset( $post ) && $post != null) {
       
          /*
           * new post data array
           */
          $args = array(
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'post_author'    => $new_post_author,
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_name'      => $post->post_name,
            'post_parent'    => $post->post_parent,
            'post_password'  => $post->post_password,
            'post_status'    => 'publish',
            'post_title'     => $post->post_title,
            'post_type'      => $post->post_type,
            'to_ping'        => $post->to_ping,
            'menu_order'     => $post->menu_order
          );
       
          /*
           * insert the post by wp_insert_post() function
           */
          $new_post_id = wp_insert_post( $args );

          //echo $new_post_id ;
       
          /*
           * get all current post terms ad set them to the new post draft
           */
          $taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
          foreach ($taxonomies as $taxonomy) {
            $post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
            wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
          }

       
          /*
           * duplicate all post meta just in two SQL queries
           */
          $post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
          if (count($post_meta_infos)!=0) {
            $sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
            foreach ($post_meta_infos as $meta_info) {
              $meta_key = $meta_info->meta_key;
              if( $meta_key == '_wp_old_slug' ) continue;
              $meta_value = addslashes($meta_info->meta_value);
              $sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
            }
            $sql_query.= implode(" UNION ALL ", $sql_query_sel);
            $wpdb->query($sql_query);

          }

          $winning_team = get_post_meta( $new_post_id  , 'wdm_this_auction_winner', true);
          delete_post_meta( $new_post_id , 'wdm_this_auction_winner' , $winning_team  );
    
        } else {
          wp_die('Post creation failed, could not find original post: ' . $post_id);
        }
      }
      
function g_recreate_product( $post_data ){

    $post_id = $post_data['object_id'];

    //$winning_team = get_post_meta( $post_id  , 'wdm_this_auction_winner', true);
    // delete_post_meta( $post_id, 'wdm_this_auction_winner' , $winning_team  );
    //if( !empty( $winning_team ) ){
    // Do product recreation protocol
    rd_duplicate_post_as_draft( $post_id  );
    // recreate
    update_post_meta( $post_id , 'wdm-auth-key',md5(time().rand() ) );
    add_post_meta( $post_id , 'wdm_creation_time', date("Y-m-d H:i:s", time() ) );
    
            //schedule cleanup of old product // after a week// delete post in a week
    
        //}
}
//add_action('wdm_ua_modified_bid_place','g_recreate_product', 10000 );
function duplicate_post_listener($mid, $object_id, $meta_key, $_meta_value ){

    if( $meta_key === 'wdm_this_auction_winner' ){
  //    $buy_price = get_post_meta(esc_attr($_POST['auction_id']), 'wdm_buy_it_now', true);

 //     if (!empty($buy_price) && $ab_bid >= $buy_price) {
          g_recreate_product([
              'mid' => $mid ,
              'object_id' => $object_id,
              'meta_key' => $meta_key,
              '_meta_value' => $_meta_value
          ]);
   //   }
    }
}

add_action( "added_post_meta" , 'duplicate_post_listener' , 10, 4);