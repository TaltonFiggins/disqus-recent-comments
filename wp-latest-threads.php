<?php
   /*
   Plugin Name: Most Recent Comments from Disqus
   Description: A plugin that lists the most recent comments from Disqus.
   Version: v1.0
   Author: Talton
   Author URI: http://prefadedpop.com
   License: 
   */

   	//Sets the date and time and enables error messages
    date_default_timezone_set('America/Los_Angeles');

	ini_set('display_errors', 'on');
	//include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	//trigger table creation and deletion
	register_activation_hook(__FILE__,'rcw_install');
	register_activation_hook(__FILE__,'rcw_install_data');
	register_deactivation_hook(__FILE__, 'rcw_uninstall');

	global $rcw_db_version;
	$rcw_db_version = "1.0";

	//table creation - Tutorial - http://codex.wordpress.org/Creating_Tables_with_Plugins
	function rcw_install(){
		global $wpdb;
		global $rcw_db_version;
		//setting a table name
		$table_name = $wpdb->prefix.'recent_comments';

		//These fields are incorrect and need to be updated
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			thread_title text NOT NULL,
			message text NOT NULL,
			comment_url text NOT NULL,
			thread_url text NOT NULL,
			author_name text NOT NULL,
			comment_date text NOT NULL,
			UNIQUE KEY id (id) 
			  );";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		
		add_option("rcw_db_version", $rcw_db_version);
	}

	//initial install data
	function rcw_install_data(){

		global $wpdb;
		$table_name = $wpdb->prefix.'recent_comments';
		for($max_rows=0; $max_rows < 10; $max_rows++){
			$wpdb->insert( 
				$table_name, 
				array(
					'id' => $max_rows+1,
					'thread_title' => '',
					'message' => '', 
					'comment_url' => '',
					'thread_url' => '',
					'author_name' => '',
					'comment_date' => ''
				)
			);
		}//end 'for' loop	
	}

	//upgrade data for future versions
	function rcw_upgrade(){
		global $wpdb;
		$installed_version = get_option( "rcw_db_version");

		if( $installed_ver != $jal_db_version ) {
			$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			thread_title text NOT NULL,
			message text NOT NULL,
			comment_url text NOT NULL,
			thread_url text NOT NULL,
			author_name text NOT NULL,
			comment_date text NOT NULL,
			UNIQUE KEY id (id) 
			  );";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);

      update_option( "rcw_db_version", $rcw_db_version );
      }


	}


	/*function echoInstance(){
		global $wpdb;
		

		var_dump($checkItOut);

		$mylink = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1");
		echo $mylink->message;

	}
	add_action('plugins_loaded', 'echoInstance');*/

	//Hook for checking for update
	function myplugin_update_db_check() {
    global $rcw_db_version;
	    if (get_site_option('rcw_db_version') != $rcw_db_version) {
	        rcw_install();
	    }
	}
	add_action('plugins_loaded', 'myplugin_update_db_check');

	//Drop data from table
	function rcw_uninstall() {
		global $wpdb;
		$table_name = $wpdb->prefix.'recent_comments';
		$wpdb->query("DROP TABLE {$table_name}");
		delete_option( 'widget_recent_disqus_comments' );
	}

    /**
     * Register with hook 'wp_enqueue_scripts', which can be used for front end CSS and JavaScript
     */
    add_action( 'wp_enqueue_scripts', 'prefix_add_my_stylesheet' );
    /**
     * Enqueue plugin style-file
     */
    function prefix_add_my_stylesheet() {
        // Respects SSL, Style.css is relative to the current file
        wp_register_style( 'prefix-style', plugins_url('disqus-recent-comments/style.css') );
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

	//Adds the schedule option
	add_filter( 'cron_schedules', 'cron_add_weekly' );
 
 	function cron_add_weekly( $schedules ) {
	 	// Adds once weekly to the existing schedules.
	 	$schedules['five_minutes'] = array(
	 		'interval' => 60,
	 		'display' => __( 'Once a Minute' )
	 	);
	 	return $schedules;
 	}

 	//Activates the schedule option
	if ( ! wp_next_scheduled( 'my_task_hook' ) ) {
	  wp_schedule_event( time(), 'five_minutes', 'my_task_hook' );
	}

	add_action( 'my_task_hook', 'my_task_function' );

	add_action( 'widgets_init', 'my_task_function' ); // function to load my widget  

	function my_task_function() {
	  	global $wpdb;
		$table_name = $wpdb->prefix.'recent_comments';

		$max_num_results = 10;

		//var_dump($instance);

		$url = 'http://disqus.com/api/3.0/forums/listPosts.json?';

		$fields = (object) array(
			'api_key' => get_option('disqus_public_key'),
			'forum' => get_option('disqus_forum_url'),
			'related' => 'thread'
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
		//var_dump($post_list);

			//Have you set a forum and API key - Should probably add an additional check that they actually work.
			if($fields->api_key && $fields->forum){
				//Print most recent comments
				for($num_result = 0; $num_result < $max_num_results ; $num_result++)
				{	
					if (is_null($post_list->response[$num_result]->message)){$num_result = $max_num_results;}
					else{
					
					$post_message  = strip_tags($post_list->response[$num_result]->message);
				
					//Checking the length of the comment and trimming it if it's too long.
					if(strlen($post_message)>120){$post_message = substr($post_message, 0 , 120);
						$post_message = substr($post_message, 0 , strripos($post_message, ' ')).' ...';}
					
					$post_title = $post_list->response[$num_result]->thread->title;
					if(strlen($post_title)>30){$post_title = substr($post_title, 0 , 30);
						$post_title = substr($post_title, 0 , strripos($post_title, ' ')).' ...';}

					
					//Converting the timezone brought in through the API. Example pulled from: http://stackoverflow.com/questions/5746531/php-utc-date-time-string-to-timezone
					$UTC = new DateTimeZone("UTC");
					$newTZ = new DateTimeZone(date_default_timezone_get());
					$post_date = new DateTime($post_list->response[$num_result]->createdAt, $UTC );
					$post_date->setTimezone( $newTZ );
					$post_date = $post_date->format('M d, Y');
					
					//Outputting data to the page.
					
					
					//updating the db rows that I havet
					$wpdb->update( 
						$table_name, 
						array(
							'thread_title' => $post_title,
							'message' => $post_message, 
							'comment_url' =>  $post_list->response[$num_result]->url,
							'thread_url' => $post_list->response[$num_result]->thread->link,
							'author_name' => $post_list->response[$num_result]->author->name,
							'comment_date' => $post_date
						),
						array( 'ID' => $num_result+1 )

					);

					}//end 'if' checking for a message
				}//end for loop for printing
			}//end variable check
	}

	add_action( 'widgets_init', 'my_widget' ); // function to load my widget  
	function my_widget() {
		register_widget('recent_disqus_comments'); // function to register my widget

	}                   

	// Class which contains the entire widget script
	class recent_disqus_comments extends WP_Widget {

		function recent_disqus_comments() {

				$widget_ops = array( 'classname' => 'recent_disqus_comments', 'description' => __('Displays the most recent comments from Disqus.','stuffage') );  
		        $control_ops = array( 'width' => '100px', 'height' => '350px', 'id_base' => 'recent_disqus_comments' );  
		        $this->WP_Widget( 'recent_disqus_comments', 'Recent Comments from Disqus', $widget_ops, $control_ops );  
		}   



		// What are args?
		function widget($args, $instance) {
			
			global $wpdb;
			$table_name = $wpdb->prefix.'recent_comments';
			$num_results_selected = $instance['num_results_selected'];


			$title = apply_filters('widget_title', $instance['title'] );  

			echo '<aside class="widget recent_comments">';  
			if ( isset( $instance['show_info'] ) ) {
		    echo '<h1 class="widget-title">'.$before_title . $title . $after_title.'</h1>';
		    }
		    echo '<ul>';


			for($num_result = 0; $num_result < $num_results_selected ; $num_result++){
				$comment = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $num_result+1");

					if (empty($comment->message)){$num_result = $num_results_selected;}
						else{
					//Outputting data to the page.
						echo '<li><span class="thread_title"><a href="'.$comment->thread_url.'">'.$comment->thread_title.'</a></span><p>'.$comment->message.'</p><span claass="author_date"><a href="'.$comment->comment_url.'">'.$comment->author_name.'  -  '.$comment->comment_date.'</a></span></li>';
					}// end if/else
				}
			echo '</ul></aside>';


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
			$defaults = array('num_results_selected' => '5', 'title' => 'Most Recent Comments', 'show_info' => true);
			$instance = wp_parse_args( (array) $instance, $defaults); ?>
			
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