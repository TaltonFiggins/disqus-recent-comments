<?php
   /*
   Plugin Name: Unofficial Disqus Most Popular Comments
   Plugin URI: http://prefadedpop.com
   Description: A plugin that list the most commented threads
   Version: v1.0
   Author: MediaMisfit
   Author URI: http://mrtotallyawesome.com
   License: 
   */

   	//Sets the date and time and enables error messages
    date_default_timezone_set('America/Los_Angeles');
	ini_set('display_errors', 'on');
	//include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    /**
     * Register with hook 'wp_enqueue_scripts', which can be used for front end CSS and JavaScript
     */
    add_action( 'wp_enqueue_scripts', 'prefix_add_my_stylesheet' );

    /**
     * Enqueue plugin style-file
     */
    function prefix_add_my_stylesheet() {
        // Respects SSL, Style.css is relative to the current file
        wp_register_style( 'prefix-style', plugins_url('latest-threads/style.css') );
        wp_enqueue_style( 'prefix-style' );
    }
	//Adds an action to display an admin notice if the forum or API keys aren't present
	add_action('admin_notices','showAdminMessages');
	function showMessage($message)
	{
		echo '<div id="message" class="updated fade"><p><strong>'.$message.'</strong></p></div>';
	}

	function showAdminMessages()
	{
		
		$key=get_option('disqus_public_key'); 
		$forum=get_option('disqus_forum_url');
	    
		if(!$key || !is_plugin_active('disqus-comment-system/disqus.php')){
		    if (!$key && $forum){
		    	$message = "You must configure the <a href='http://localhost/~helpdesk3/wordpress/wp-admin/edit-comments.php?page=disqus&step=3' target='_blank'>Disqus plugin</a> with a forum and <a href='http://help.disqus.com/customer/portal/articles/787016-how-to-create-an-api-application' target='_blank'>Public and Secret API key</a> before enabling the Latest Threads widget.";
		    }
		    elseif (!is_plugin_active('disqus-comment-system/disqus.php')) {
    			$message = "The <a href='http://wordpress.org/extend/plugins/disqus-comment-system/' target='_blank'>Disqus WordPress plugin</a> is required in order to use the Latest Threads plugin.";
		    }
		    showMessage($message);
		}   
	}

	add_action( 'widgets_init', 'my_widget' ); // function to load my widget  
	function my_widget() {
		register_widget('latest_threads'); // function to register my widget
	}                   

	// Class which contains the entire widget script * I should probably abstract most of this stuff. *
	class latest_threads extends WP_Widget { 

		function latest_threads() {
				$widget_ops = array( 'classname' => 'latest_threads', 'description' => __('Displays the latest comments for your website made through Disqus.','stuffage') );  
		        $control_ops = array( 'width' => '100px', 'height' => '350px', 'id_base' => 'latest_threads' );  
		        $this->WP_Widget( 'latest_threads', __('Latest Comments', 'stuff'), $widget_ops, $control_ops );  
		}                     

		// What are args?
		function widget($args, $instance) {

		extract($args);
		$title = apply_filters('widget_title', $instance['title'] );  
		$name = $instance['name'];  

		//$show_info = isset( $instance['show_info'] ) ? $instance['show_info'] : false; //? 
		$num_results_selected = $instance['num_results_selected']; 
		echo $before_widget;  
		if ( isset( $instance['show_info'] ) )  
	    echo $before_title . $title . $after_title;  
		
		$url = 'http://disqus.com/api/3.0/forums/listPosts.json?';

		$fields = (object) array(
			'api_key' => get_option('disqus_public_key'),
			'forum' => get_option('disqus_forum_url')
		);

		//Build the endpiont from the fields selected and put add it to the string.
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		$fields_string = rtrim($fields_string, "&");

			function getData($url, $fields_string){
			// setup curl to make a call to the endpoint
			$url .= $fields_string;

			$session = curl_init($url);

			// indicates that we want the response back rather than just returning a "TRUE" string
			curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

			// execute GET and get the session back
			$results = curl_exec($session);

			// close connection
			curl_close($session);

			// show the response in the browser
			return  $data = json_decode($results);

			}

		//Getting list of most recent posts
		$post_list = getData($url, $fields_string);

		//Have you set a forum and API key
			if($fields->api_key && $fields->forum){
				//Print Latest Threads Created
				for($num_result = 0; $num_result < $num_results_selected ; $num_result++)
				{	
					if (is_null($post_list->response[$num_result]->message)){$num_result = $num_results_selected;}
					else{
						// Building a new string. Seems repetiive. May need to condense this into a single function.
						$fields_string = NULL;
						$url = 'http://disqus.com/api/3.0/threads/details.json?';

						$fields = (object) array(
							'api_key' => get_option('disqus_public_key'),
							'forum' => get_option('disqus_forum_url'),
							'thread' => $post_list->response[$num_result]->thread
						);

						//Build the endpiont from the fields selected and put add it to the string.
						foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
						$fields_string = rtrim($fields_string, "&");
						
					$thread_info = getData($url, $fields_string);
					$comment_link = $thread_info->response->link.'#comment-'.$post_list->response[$num_result]->id;
					// Setting the date Month day, Year
					$post_date  = date('M d, Y', strtotime($post_list->response[$num_result]->createdAt));
					echo '<div class="recent_post">'.$post_list->response[$num_result]->raw_message.'</br><a href="'.$comment_link.'">'.$post_list->response[$num_result]->author->name.'  -  '.$post_date.'</a></div>';
					}
				}
			}//end variable check

		}// End of the widget function

	// UPDATING WIDGET OPTIONS
	function update( $new_instance, $old_instance ) {  
	    $instance = $old_instance;  
	    //Strip tags from title and name to remove HTML  
	    $instance['num_results_selected'] = $new_instance['num_results_selected'];
	    $instance['title'] = strip_tags( $new_instance['title'] );
	    $instance['show_info'] = isset( $new_instance['show_info'] );  
	    return $instance;  
	}                    // update the widget  

	// WIDGET OPTIONS
	function form( $instance ) {

			//Set up some default widget settings.
			$defaults = array('num_results_selected' => '5', 'title' => 'Most Recent Threads', 'show_info' => true);
			$instance = wp_parse_args( (array) $instance, $defaults ); ?>
			
			<!--Assign a Title -->
			 <p>
	               <label for="<?php echo $this->get_field_id( 'title' ); ?>">Title</label>
	               <input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:100%;" />
	          </p>


			<!-- Number of Results to show -->
			

			<label for="<?php echo $this->get_field_id( 'num_results_selected' ); ?> ">Number of Results:</label>
			<select id="<?php echo $this->get_field_id( 'num_results_selected' ); ?>" name="<?php echo $this->get_field_name( 'num_results_selected' ); ?>">
	    		 <option value="5" <?php selected($instance['num_results_selected'], '5'); ?>>5</option>
	    		 <option value="6" <?php selected($instance['num_results_selected'], '6'); ?>>6</option>
	    		 <option value="7" <?php selected($instance['num_results_selected'], '7'); ?>>7</option>
	    		 <option value="8" <?php selected($instance['num_results_selected'], '8'); ?>>8</option>
	    		 <option value="9" <?php selected($instance['num_results_selected'], '9'); ?>>9</option>
	    		 <option value="10" <?php selected($instance['num_results_selected'], '10'); ?>>10</option>
			</select>

			<!-- Opt to display the title -->
			 <p>
	             <input class="checkbox" type="checkbox" <?php checked( $instance['show_info'], true ); ?> id="<?php echo $this->get_field_id( 'show_info' ); ?>" name="<?php echo $this->get_field_name( 'show_info' ); ?>" />
	             <label for="<?php echo $this->get_field_id( 'show_info' ); ?>">Display Title?</label>
	          </p>


		<?php
		}// ending the form  

	}//ending the class - It's contains everything.

?>