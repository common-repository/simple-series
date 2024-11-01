<?php
/*
Plugin Name: Simple Post Series with SEO!
Plugin URI: wordpress.org/extend/plugins/simple-series/
Description: Super simple post series organization
Version: 1.4.7
Date: 08-06-2018
Author: MakerBlock
Author URI: http://www.makerblock.com
**************************************************************
*/
/*
To do
	- Create a single option that rules them all, instead of post-meta
	- Create an upgrade/uninstall method - so that the post meta will be deleted and then replaced with the single option to rule them all
	- Create a single function which creates an array of all series titles used, any where not just the published posts
*/

//	Initialize Plugin
	add_filter('the_content', 'mbk_simple_series');
	add_filter('the_content_feed', 'mbk_simple_series');
	//	This adds a button to the text editor
	add_action( 'admin_print_footer_scripts', 'MBSS_quicktags', 100 );
	//	This adds a button to the visual editor

function add_youtube_button() 
	{
	if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
		{ return; }
	if ( get_user_option('rich_editing') == 'true') 
		{
		add_filter('mce_external_plugins', 'add_youtube_tinymce_plugin');
		add_filter('mce_buttons', 'register_youtube_button');
		}
	}

add_action('init', 'add_youtube_button');

function register_youtube_button($buttons) 
	{
	array_push($buttons, "|", "mbksimpleseries");
	return $buttons;
	}

function add_youtube_tinymce_plugin($plugin_array) 
	{
	$plugin_array['mbksimpleseries'] = plugins_url() .'/simple-series/editor_plugin.php';
	return $plugin_array;
	}

function my_refresh_mce($ver) 
	{
	$ver += 3;
	return $ver;
	}

add_filter( 'tiny_mce_version', 'my_refresh_mce');
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	

//	Main Plugin Function
function mbk_simple_series($content)
	{ add_shortcode('simple_series', 'mbk_simple_series_shortcode'); return $content; }	//	Create Shortcode

//	Shortcode Function
function mbk_simple_series_shortcode($atts) 
	{
	extract(shortcode_atts(array( 'title' => '', 'renameall' => '', 'delete' => ''), $atts));	//	Pulls the variables from the shortcode
	//	1. We need to find the current post ID
		global $wp_query, $wpdb;
			$postID = $wp_query->post->ID;
	//	2. We need to assign a post-meta for this post
		update_post_meta($postID, 'mbk_simple_series', $title);
	//	3. Let's split up the title, if there were multiple titles
		$title = explode(",",$title);
	//	4. Let's create a loop that will run - once for each title.  This is NOT the most elegant way to run this query!
		for ($h=0;$h<count($title);$h++)
			{
			$titleh = $title[$h];
		//	4.a. Find ALL posts with that same post-meta tag, sorted by the publish date
			$querystr = "
				SELECT $wpdb->posts.* 
				FROM $wpdb->posts, $wpdb->postmeta
				WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id 
				AND $wpdb->postmeta.meta_key = 'mbk_simple_series' 
				AND $wpdb->postmeta.meta_value = '$titleh' 
				AND $wpdb->posts.post_status = 'publish' 
				AND ( $wpdb->posts.post_type = 'post' OR $wpdb->posts.post_type = 'page' )
				AND $wpdb->posts.post_date < NOW()
				ORDER BY $wpdb->posts.post_date ASC";
			$pageposts = $wpdb->get_results($querystr, OBJECT);
		//	4.b. Create an OL list for the posts - appended to the end of the post
			$text .= "<ol>";
			for ($i=0; $i<count($pageposts);$i++)
				{ 
				if ($pageposts[$i]->ID == $postID)
					{ 
					$linkClass = " class='mbk_simple_series_list_current_item'"; 
					$prev = $i-1; 
					$next = $i+1; 
					}
				else { $linkClass = ""; }
				$text .= "<li class='mbk_simple_series_list_item'><a href='". get_permalink($pageposts[$i]->ID) ."' title='". $pageposts[$i]->post_title ."' $linkClass>". $pageposts[$i]->post_title ."</a></li>"; 
				}
			$text .= "</ol>";
			//	4.c. Add in the title
			$title = "<div class='mbk_simple_series_wrapper'><span class='mbk_simple_series_title'>$titleh</span>";
			$prevnext = "<div class='mbk_simple_series_prevnext'>";
				$prevnext .= "<a href='". get_permalink($pageposts[$prev]->ID) ."' title='". $pageposts[$prev]->post_title ."' class='mbk_simple_series_link_prev'>&larr; Previous</a>";  
				if ($next < $i)
					{ $prevnext .= " <a href='". get_permalink($pageposts[$next]->ID) ."' title='". $pageposts[$next]->post_title ."' class='mbk_simple_series_link_next'>Next &rarr;</a>"; }
			$text = $title. $text. $prevnext . "</div>" ."</div>";
			}
	//	5. Does the user want to delete the post from the series?
		if ($delete == "true")
			{  delete_post_meta($postID, "mbk_simple_series"); $text = ""; }
	//	6. Return the series!
	return $text;
	}

//	Javascript to add series with just a button click!
function MBSS_quicktags() 
	{
	//	Collect all series used
		global $wp_query, $wpdb;
			$postID = $wp_query->post->ID;
		//	Form the query
			$querystr = "
				SELECT DISTINCT $wpdb->postmeta.meta_value
				FROM $wpdb->posts, $wpdb->postmeta
				WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id 
				AND $wpdb->postmeta.meta_key = 'mbk_simple_series' 
				AND $wpdb->posts.post_status = 'publish' 
				AND $wpdb->postmeta.meta_value != ''
				AND ( $wpdb->posts.post_type = 'post' OR $wpdb->posts.post_type = 'page' )
				AND $wpdb->posts.post_date < NOW()
				ORDER BY $wpdb->posts.post_date ASC";
		//	Query the database
		$pageposts = $wpdb->get_results($querystr, OBJECT);
		//	Create an array of the titles
		$titles = array();
			for ($i=0;$i<count($pageposts);$i++)
			{ $titles[] = $pageposts[$i] ->meta_value; }
		$titles = substr(json_encode($titles), 1,-1);	
	//	Create the javascript to add the button and action
	?>
    <script type="text/javascript">
	//	Creates button in the text editor
	QTags.addButton( 'MBSS_tag_id', 'Add Series', MBSS_add_series_js );
	//	Function to append the series shortcode to the end of the post
	function MBSS_add_series_js() 
		{ 
		//	Set up array of all series
		var titles = new Array(<?php echo $titles; ?>);
		var user_choices = 'Enter the number of the series title you would like to add:\n';
		for (i=0;i<titles.length;i++) { user_choices+='['+(i+1)+']'+titles[i]+'\n'; }
		var user_chose = prompt(user_choices, 'New series');
		if (user_chose == 'New series' || user_chose < 1)
			{ series_insert = '[simple_series title=""]'; }
		else 
			{ series_insert = '[simple_series title="'+ titles[user_chose-1] +'"]'; }
		editor = document.getElementById('content');
			editor.value = editor.value + "\n" +series_insert;
		}
	</script>
	<?php
	}
?>