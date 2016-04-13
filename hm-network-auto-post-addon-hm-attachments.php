<?php

/*
Plugin Name: HM Network Auto Post Addon: HM Attachments
Version: 0.1
Description: 
Plugin URI: Update
Author: Martin Wecke, HATSUMATSU
Author URI: http://hatsumatsu.de/
*/


class HMNetworkAutoPostHMAttachments {

	public function __construct() {
		// hook into custom action from HMNetworkAutoPost
		add_action( 'hmnap/create_post', array( $this, 'create_post' ), 100, 4 );
	}


	/**
	 * Called when HMNetworkAutoPost creates a related Post
	 * @param  int $source_site_id site ID of the source site
	 * @param  int $target_site_id site ID of the target site
	 * @param  int $source_post_id post ID of the source post
	 * @param  int $target_post_id post ID of the target post
	 */
	public function create_post( $source_site_id, $target_site_id, $source_post_id, $target_post_id ) {
		// quit if post is post revisions
		if( wp_is_post_revision( $source_post_id ) ) {
			$this->write_log( 'ignore revision' );
			return;
		}

		$this->write_log( 'create_post' );
		$this->write_log( 'source_site_id: ' . $source_site_id );
		$this->write_log( 'target_site_id: ' . $target_site_id );
		$this->write_log( 'source_post_id: ' . $source_post_id );
		$this->write_log( 'target_post_id: ' . $target_post_id );

		if( function_exists( 'HM\Attachments\get_attachments' ) ) {
			// get all attachments from HMAttachments
			if( $attachments = HM\Attachments\get_attachments( $source_post_id ) ) {
		    	$this->write_log( 'attachmets:' );		
				$this->write_log( $attachments );

				foreach( $attachments as $key => $attachment ) {
					if( function_exists( 'mlp_get_linked_elements' ) ) {
						// get MLP relations
		                $attachment_relations = mlp_get_linked_elements( $attachment['id'], '', $source_site_id );
		               	$this->write_log( $attachment_relations );		
		               	// if related attachment exists
		                if( array_key_exists( $target_site_id, $attachment_relations ) ) {
		                	// change ID to related attachment ID
		                	$attachments[$key]['id'] = $attachment_relations[$target_site_id];
		                } else {
		                	// remove attachment when no relation is found
		                	unset( $attachments[$key] );
		                }
		            }
				}

				$this->write_log( $attachments );				

				// switch to target site
				switch_to_blog( $target_site_id );

				// delete all existing attachment data
				delete_post_meta( $target_post_id, 'hm-attachment' );
				foreach( $attachments as $attachment ) {
					// add updated attachment data
					add_post_meta( $target_post_id, 'hm-attachment', json_encode( $attachment, JSON_UNESCAPED_UNICODE ) );
				}
				// switch back to source site
				restore_current_blog();
			}
		}
	}


	/**
	 * Write log if WP_DEBUG is active
	 * @param  string|array $log 
	 */
	public function write_log( $log )  {
	    if( true === WP_DEBUG ) {
	        if( is_array( $log ) || is_object( $log ) ) {
	            error_log( 'hmnap: ' . print_r( $log, true ) . "\n", 3, trailingslashit( ABSPATH ) . 'wp-content/debuglog.log' );
	        } else {
	            error_log( 'hmnap: ' . $log . "\n", 3, trailingslashit( ABSPATH ) . 'wp-content/debuglog.log' );
	        }
	    }
	}
}

new HMNetworkAutoPostHMAttachments();