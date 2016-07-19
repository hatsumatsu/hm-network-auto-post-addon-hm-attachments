<?php

/*
Plugin Name: HM Network Auto Post Addon: HM Attachments
Version: 0.12
Description: Addon for HM Network Auto Post to support HM Attachments.
Plugin URI: 
Author: Martin Wecke
Author URI: http://martinwecke.de/
GitHub Plugin URI: https://github.com/hatsumatsu/hm-network-auto-post-addon-hm-attachments
GitHub Branch: master
*/


class HMNetworkAutoPostHMAttachments {
	protected $settings;

	public function __construct() {
		// load settings from HMNetworkAutoPost
		add_action( 'after_setup_theme', array( $this, 'loadSettings' ) );

		// hook into custom action from HMNetworkAutoPost
		add_action( 'hmnap/create_post', array( $this, 'createPost' ), 100, 4 );

		// hook into custom action from HMNetworkAutoPost
		add_action( 'hmnap/save_post', array( $this, 'savePost' ), 100, 4 );	
	}


	/**
	 * Load settings from filter 'hmnap/settings'
	 */
	public function loadSettings() {
		$this->settings = apply_filters( 'hmnap/settings', $this->settings );
	}


	/**
	 * Called when HMNetworkAutoPost creates a related Post
	 * @param  int $source_site_id site ID of the source site
	 * @param  int $target_site_id site ID of the target site
	 * @param  int $source_post_id post ID of the source post
	 * @param  int $target_post_id post ID of the target post
	 */
	public function createPost( $source_site_id, $target_site_id, $source_post_id, $target_post_id ) {
		$this->writeLog( 'createPost()' );
		$this->writeLog( 'source_site_id: ' . $source_site_id );
		$this->writeLog( 'target_site_id: ' . $target_site_id );
		$this->writeLog( 'source_post_id: ' . $source_post_id );
		$this->writeLog( 'target_post_id: ' . $target_post_id );		

		// quit if post is post revisions
		if( wp_is_post_revision( $source_post_id ) ) {
			return;
		}

		$this->setHMAttachments( $source_site_id, $target_site_id, $source_post_id, $target_post_id );		
	}


	/**
	 * Called when HMNetworkAutoPost saves a related Post
	 * @param  int $source_site_id site ID of the source site
	 * @param  int $target_site_id site ID of the target site
	 * @param  int $source_post_id post ID of the source post
	 * @param  int $target_post_id post ID of the target post
	 */
	public function savePost( $source_site_id, $target_site_id, $source_post_id, $target_post_id ) {
		// quit if post is post revisions
		if( wp_is_post_revision( $source_post_id ) ) {
			return;
		}

		if( array_key_exists( 'hm-attachments', $this->settings[get_post_type( $source_post_id )]['permanent'] ) && $this->settings[get_post_type( $source_post_id )]['permanent']['hm-attachments'] ) {
			$this->writeLog( 'savePost()' );
			$this->writeLog( 'source_site_id: ' . $source_site_id );
			$this->writeLog( 'target_site_id: ' . $target_site_id );
			$this->writeLog( 'source_post_id: ' . $source_post_id );
			$this->writeLog( 'target_post_id: ' . $target_post_id );

			$this->setHMAttachments( $source_site_id, $target_site_id, $source_post_id, $target_post_id );
		}
	}



	/**
	 * Set HM Attachment data 
	 * @param  int $source_site_id site ID of the source site
	 * @param  int $target_site_id site ID of the target site
	 * @param  int $source_post_id post ID of the source post
	 * @param  int $target_post_id post ID of the target post
	 */
	public function setHMAttachments( $source_site_id, $target_site_id, $source_post_id, $target_post_id ) {
		$this->writeLog( 'setHMAttachments()' );

		if( !function_exists( 'HM\Attachments\get_attachments' ) ) {
			return;
		}

		if( !function_exists( 'mlp_get_linked_elements' ) ) {
			return;
		}		

		// get all attachments from HMAttachments
		if( $source_attachments = HM\Attachments\get_attachments( $source_post_id ) ) {
			// TODO: keep existing titles!

			// get target attachments
			// switch to target site
			switch_to_blog( $target_site_id );
			$_target_attachments = HM\Attachments\get_attachments( $target_post_id );
			// switch back to source site
			restore_current_blog();

			$target_attachments = array();

			foreach( $source_attachments as $key => $source_attachment ) {
				// get MLP relations
                $attachment_relations = mlp_get_linked_elements( $source_attachment['id'], '', $source_site_id );
               	// if related attachment exists
                if( array_key_exists( $target_site_id, $attachment_relations ) ) {
                	// $this->writeLog( 'found remote attachment: ' . $attachment_relations[$target_site_id] );

                	$target_attachment = $source_attachment;
                	// change ID to related attachment ID
                	$target_attachment['id'] = $attachment_relations[$target_site_id];

                	// change title to existing remote title
                	foreach( $_target_attachments as $_target_attachment ) {
                		// found existing target attachment
                		if( $_target_attachment['id'] == $target_attachment['id'] ) {
                			// $this->writeLog( 'found remote attachment ( ' . $_target_attachment['id'] . ' ), keep title: ' . $_target_attachment['fields']['title'] );
							$target_attachment['fields']['title'] = $_target_attachment['fields']['title'];
                		}
                	}

                	$target_attachments[] = $target_attachment;
                }
			}

            // $this->writeLog( 'target_attachments: ' );
            // $this->writeLog( $target_attachments );

			// switch to target site
			switch_to_blog( $target_site_id );
			// delete all existing attachment data
			delete_post_meta( $target_post_id, 'hm-attachment' );
			foreach( $target_attachments as $target_attachment ) {
				// add updated attachment data
				add_post_meta( $target_post_id, 'hm-attachment', json_encode( $target_attachment, JSON_UNESCAPED_UNICODE ) );
			}
			// switch back to source site
			restore_current_blog();
		} else {
			// switch to target site
			switch_to_blog( $target_site_id );			
			// delete all remote attachments 
			delete_post_meta( $target_post_id, 'hm-attachment' );
			// switch back to source site
			restore_current_blog();
		}
	}


	/**
	 * Write log if WP_DEBUG is active
	 * @param  string|array $log 
	 */
	public function writeLog( $log )  {
	    if( true === WP_DEBUG ) {
	        if( is_array( $log ) || is_object( $log ) ) {
	            error_log( 'hmnaphma: ' . print_r( $log, true ) . "\n", 3, trailingslashit( ABSPATH ) . 'wp-content/debuglog.log' );
	        } else {
	            error_log( 'hmnaphma: ' . $log . "\n", 3, trailingslashit( ABSPATH ) . 'wp-content/debuglog.log' );
	        }
	    }
	}
}

new HMNetworkAutoPostHMAttachments();