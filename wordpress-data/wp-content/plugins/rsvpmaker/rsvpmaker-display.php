<?php

//event_content defined in rsvpmaker-pluggable.php to allow for variations

add_filter('the_content','event_content_anchor',50);

function event_content_anchor ($content) {
global $post;
if(!is_single() || ($post->post_type != 'rsvpmaker') )
	return $content;
return '<div id="rsvpmaker_top"></div>'.$content;
}

add_filter('the_content','event_content',5);

function event_js($content) {
global $post;
if(is_email_context ())
	return $content;
if(!is_single())
	return $content;
if(!strpos($content,'id="rsvpform"') )
	return $content;
if($post->post_type != 'rsvpmaker')
	return $content;
return $content . rsvp_form_jquery();
}

function rsvp_form_jquery() {
global $rsvp_required_field; // todo
global $post;
ob_start();
?>
<script type="text/javascript">
jQuery(document).ready(function($) {

<?php
$hide = get_post_meta($post->ID,'_hiddenrsvpfields',true);
if(!empty($hide))
	{
	printf('var hide = %s;',json_encode($hide));
	echo "\n";
?>

$('#guest_count_pricing select').change(function() {
  //reset hidden fields
  $('#rsvpform input').prop( "disabled", false );
  $('#rsvpform select').prop( "disabled", false );
  $('#rsvpform div').show();
  $('#rsvpform p').show();
  var pricechoice = $(this).val();
  //alert( "Price choice" + hide[pricechoice] );
  var hideit = hide[pricechoice];
  $.each(hideit, function( index, value ) {
  //alert( index + ": " + value );
  $('div.'+value).hide();
  $('p.'+value).hide();
  $('.'+value).prop( "disabled", true );
});
  
});

<?php
	}
?>
var max_guests = $('#max_guests').val();
var blank = $('#first_blank').html();
if(blank)
	{
	$('#first_blank').html(blank.replace(/\[\]/g,'['+guestcount+']').replace('###',guestcount) );
guestcount++;
	}
$('#add_guests').click(function(event){
	event.preventDefault();
if(guestcount >= max_guests)
	{
	$('#first_blank').append('<p><em><?php _e('Guest limit reached','rsvpmaker'); ?></em></p>');
	return;
	}
var guestline = '<' + 'div class="guest_blank">' +
	blank.replace(/\[\]/g,'['+guestcount+']').replace('###',guestcount) +
	'<' + '/div>';
guestcount++;
$('#first_blank').append(guestline);

if(hide)
{
  var pricechoice = $("#guest_count_pricing select").val();
  var hideit = hide[pricechoice];
  $.each(hideit, function( index, value ) {
  //alert( index + ": " + value );
  $('div.'+value).hide();
  $('p.'+value).hide();
  $('.'+value).prop( "disabled", true );
});
}

});

    jQuery("#rsvpform").submit(function() {
	var leftblank = '';
	var required = jQuery("#required").val();
	var required_fields = required.split(',');
	$.each(required_fields, function( index, value ) {
		if(value == 'privacy_consent')
			{
			if(!jQuery('#privacy_consent:checked').val())
			leftblank = leftblank + '<' + 'div class="rsvp_missing">privacy policy consent checkbox<' +'/div>';				
			}
		else if(jQuery("#"+value).val() === '') leftblank = leftblank + '<' + 'div class="rsvp_missing">'+value+'<' +'/div>';
	});
	if(leftblank != '')
		{
		jQuery("#jqerror").html('<' +'div class="rsvp_validation_error">' + "Required fields left blank:\n" + leftblank + ''+'<' +'/div>');
		return false;
		}
	else
		return true;
});

//search for previous rsvps
var searchRequest = null;

$(function () {
    var minlength = 3;

    $("#email").keyup(function () {
        var that = this,
        value = $(this).val();
		var post_id = $('#event').val();
        if (value.length >= minlength ) {
            if (searchRequest != null) 
                searchRequest.abort();
            searchRequest = $.ajax({
                type: "POST",
                url: '<?php echo site_url('?rsvp_email_lookup=1'); ?>',
                data: {
                    'action' : 'rsvp_email_lookup',
					'post_id' : post_id,
                    'email_search' : value
                },
                success: function(msg){
                    //we need to check if the value is the same
					if (value==$(that).val()) {
						$('#rsvp_email_lookup').html(msg);
                    //Receiving the result of search here
                    }
                }
            });
        }
    });
});	

});
</script>
<?php
return ob_get_clean();
}

add_filter('the_content','event_js',15);

add_shortcode('event_listing', 'event_listing');

function event_listing($atts) {

global $wpdb;
global $rsvp_options;
$eventlist = array();
$listings = '';
$date_format = (isset($atts["date_format"])) ? $atts["date_format"] : $rsvp_options["short_date"];
$time_format = (isset($atts["time_format"])) ? $atts["time_format"] : $rsvp_options["time_format"];
fix_timezone();

if(isset($atts["past"]))
	$sql = "SELECT *, $wpdb->posts.ID as postID, 1 as current, meta_value as datetime
FROM `".$wpdb->postmeta."`
JOIN $wpdb->posts ON ".$wpdb->postmeta.".post_id = $wpdb->posts.ID AND meta_key='_rsvp_dates'
WHERE meta_value < CURDATE( ) AND $wpdb->posts.post_status = 'publish'
ORDER BY meta_value DESC";
elseif(isset($_GET["cy"]))
	$sql = "SELECT *, $wpdb->posts.ID as postID, 1 as current, meta_value as datetime
FROM `".$wpdb->postmeta."`
JOIN $wpdb->posts ON ".$wpdb->postmeta.".post_id = $wpdb->posts.ID AND meta_key='_rsvp_dates'
WHERE meta_value >= '".$_GET["cy"] .'-'. $_GET["cm"].'-01'."' AND $wpdb->posts.post_status = 'publish'
ORDER BY meta_value";
else
	$sql = "SELECT *, $wpdb->posts.ID as postID, meta_value > CURDATE( ) as current, meta_value as datetime
FROM `".$wpdb->postmeta."`
JOIN $wpdb->posts ON ".$wpdb->postmeta.".post_id = $wpdb->posts.ID AND meta_key='_rsvp_dates'
WHERE meta_value >= '".date('Y-m')."-01' AND $wpdb->posts.post_status = 'publish'
ORDER BY meta_value";

if(isset($atts["limit"]))
	$sql .= " LIMIT 0,".$atts["limit"];

$results = $wpdb->get_results($sql,ARRAY_A);

foreach($results as $row)
	{
	$t = strtotime($row["datetime"]);
	if(empty($dateline[$row["postID"]]))
		$dateline[$row["postID"]] = '';
	else
		$dateline[$row["postID"]] .= ", ";
	$dateline[$row["postID"]] .= strftime($date_format,$t);
	if(!empty($row["current"]) && empty($eventlist[$row["postID"]]))
		$eventlist[$row["postID"]] = $row;
	}

if(isset($atts["calendar"]) || (isset($atts["format"]) && $atts["format"] == 'calendar') )
	$listings .= rsvpmaker_calendar($atts);

//strpos test used to catch either "headline" or "headlines"
if($eventlist && isset($atts["format"]) && ( $atts["format"] == 'headline' || $atts["format"] == 'headlines') )
{
foreach($eventlist as $event)
	{
	if(!empty($atts["permalinkhack"]))
		$permalink = site_url() ."?p=".$event["postID"];
	else
		$permalink = get_post_permalink($event["postID"]);
	$listings .= sprintf('<li><a href="%s">%s</a> %s</li>'."\n",$permalink,$event["post_title"],$dateline[$event["postID"]]);
	}	

	if(!empty($atts["limit"]) && !empty($rsvp_options["eventpage"]))
		$listings .= '<li><a href="'.$rsvp_options["eventpage"].'">'.__("Go to Events Page",'rsvpmaker')."</a></li>";

	if(!empty($atts["title"]))
		$listings = "<p><strong>".$atts["title"]."</strong></p>\n<ul id=\"eventheadlines\">\n$listings</ul>\n";
	else
		$listings = "<ul id=\"eventheadlines\">\n$listings</ul>\n";
}//end if $eventlist

	return $listings;
}

/**
 * CPEventsWidget Class
 */
class CPEventsWidget extends WP_Widget {
    function __construct() {
        parent::__construct(false, $name = 'RSVPMaker Events');
    }

    function widget($args, $instance) {		
        extract( $args );
		global $rsvpwidget;
		$rsvpwidget = true;
		$title = (isset($instance['title'])) ? $instance['title'] : __('Events','rsvpmaker');
        $title = apply_filters('widget_title', $title);
		$atts["limit"] = (isset($instance["limit"])) ? $instance["limit"] : 10;
		if(!empty($instance["event_type"]))
		$atts["type"] = (isset($instance["event_type"])) ? $instance["event_type"] : NULL;
		$dateformat = (isset($instance["dateformat"])) ? $instance["dateformat"] : 'M. j';
        global $rsvp_options;
		;?>
              <?php echo $before_widget;?>
                  <?php if ( $title )
                        echo $before_title . $title . $after_title;?>
              <?php 
			  
			  $events = rsvpmaker_upcoming_data($atts);
			  if(!empty($events))
			  {
			  echo "\n<ul>\n";
			  foreach($events as $event)
			  	{
				$datestr = '';
				foreach($event['dates'] as $date)
					{
						if(!empty($datestr))
							$datestr .= ', ';
						$datestr .= date($dateformat,strtotime($date["datetime"]));
					}
				printf('<li><a href="%s">%s</a> - %s</li>',$event['permalink'],$event['title'],$datestr);
					
				}
			
			if(!empty($rsvp_options["eventpage"]))
			  	echo '<li><a href="'.$rsvp_options["eventpage"].'">'.__("Go to Events Page",'rsvpmaker')."</a></li>";
			
			  echo "\n</ul>\n";
			  }
			  			  
			  echo $after_widget;?>
        <?php
		$rsvpwidget = false;
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {				
	$instance = $old_instance;
	$instance['title'] = strip_tags($new_instance['title']);
	$instance['dateformat'] = strip_tags($new_instance['dateformat']);
	$instance['limit'] = (int) $new_instance['limit'];
	$instance['event_type'] = $new_instance['event_type'];
        return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {				
        $title = (isset($instance['title'])) ? esc_attr($instance['title']) : __('Events','rsvpmaker');
		$limit = (isset($instance["limit"])) ? $instance["limit"] : 10;
		$dateformat = (isset($instance["dateformat"])) ? $instance["dateformat"] : 'M. j';
		$event_type = (!empty($instance["event_type"])) ? $instance["event_type"] : '';
        ?>
            <p><label for="<?php echo $this->get_field_id('title');?>"><?php _e('Title:','rsvpmaker');?> <input class="widefat" id="<?php echo $this->get_field_id('title');?>" name="<?php echo $this->get_field_name('title');?>" type="text" value="<?php echo $title;?>" /></label></p>
            <p><label for="<?php echo $this->get_field_id('limit');?>"><?php _e('Number to Show:','rsvpmaker');?> <input class="widefat" id="<?php echo $this->get_field_id('limit');?>" name="<?php echo $this->get_field_name('limit');?>" type="text" value="<?php echo $limit;?>" /></label></p>

            <p><label for="<?php echo $this->get_field_id('dateformat');?>"><?php _e('Date Format:','rsvpmaker');?> <input class="widefat" id="<?php echo $this->get_field_id('dateformat');?>" name="<?php echo $this->get_field_name('dateformat');?>" type="text" value="<?php echo $dateformat;?>" /></label> (PHP <a target="_blank" href="http://us2.php.net/manual/en/function.date.php">date</a> format string)</p>

<p><label for="<?php echo $this->get_field_id('event_type');?>">
<?php
$tax_terms = get_terms('rsvpmaker-type');
?>
<select class="widefat" id="<?php echo $this->get_field_id('event_type');?>" name="<?php echo $this->get_field_name('event_type');?>" ><option value=""><?php _e('All','rsvpmaker'); ?></option>
<?php
if(!empty($tax_terms))
	{
		foreach ($tax_terms as $tax_term) {
		$s = ($tax_term->name == $event_type) ? ' selected="selected" ' : '';
		echo '<option value="'.$tax_term->name . '" ' . $s . '>' . $tax_term->name.'</option>';
		}
	}
?>
</select>
</p>

        <?php 
    }

} // class CPEventsWidget

// register CPEventsWidget widget
add_action('widgets_init', create_function('', 'return register_widget("CPEventsWidget");'));

/**
 * RSVPTypeWidget Class
 */
class RSVPTypeWidget extends WP_Widget {
    /** constructor */
    function __construct() {
        parent::__construct('rsvpmaker_type_widget', $name = 'RSVPMaker Events by Type');	
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {		
        extract( $args );
        $title = apply_filters('widget_title', $instance['title']);
		if(empty($title))
			$title = __('Events by Type','rsvpmaker');
		$atts["limit"] = ($instance["limit"]) ? $instance["limit"] : 10;
		if(!empty($instance["event_type"]))
        global $rsvp_options;
		;?>
              <?php echo $before_widget;?>
                  <?php if ( $title )
                        echo $before_title . $title . $after_title;?>
              <?php 


$args = array( 'hide_empty=0' );
 
$terms = get_terms( 'rsvpmaker-type', $args );
echo '<ul>';
if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
    $count = count( $terms );
    $i = 0;
    foreach ( $terms as $term ) {
        $i++;
		$atts["type"] = $term->name;
	  	$events = rsvpmaker_upcoming_data($atts);
		$count = sizeof($events);
		$countstr = ($count) ? '('.$count.')' : '';
		printf('<li><a href="%s">%s</a> %s</li>',esc_url( get_term_link( $term ) ),$term->name,$countstr);
    }
}
	  echo "\n</ul>\n";
		
			  echo $after_widget;?>
        <?php
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {				
	$instance = $old_instance;
	$instance['title'] = strip_tags($new_instance['title']);
	$instance['limit'] = (int) $new_instance['limit'];
        return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {				
        $title = (isset($instance['title'])) ? esc_attr($instance['title']) : '';
		$limit = (isset($instance["limit"])) ? $instance["limit"] : 10;
        ?>
            <p><label for="<?php echo $this->get_field_id('title');?>"><?php _e('Title:','rsvpmaker');?> <input class="widefat" id="<?php echo $this->get_field_id('title');?>" name="<?php echo $this->get_field_name('title');?>" type="text" value="<?php echo $title;?>" /></label></p>
            <p><label for="<?php echo $this->get_field_id('limit');?>"><?php _e('Number to Show:','rsvpmaker');?> <input class="widefat" id="<?php echo $this->get_field_id('limit');?>" name="<?php echo $this->get_field_name('limit');?>" type="text" value="<?php echo $limit;?>" /></label></p>

        <?php 
    }

}

add_action('widgets_init', create_function('', 'return register_widget("RSVPTypeWidget");'));


function get_next_events_link( $label = '', $no_events = '' ) {
global $last_time;
global $wpdb;

$sql = "SELECT post_id from $wpdb->postmeta JOIN $wpdb->posts ON $wpdb->postmeta.post_id = $wpdb->posts.ID WHERE meta_key='_rsvp_dates' AND meta_value > '".date("Y-m-d H:i:s",$last_time)."' AND post_status='publish' ";

$at_least_one = $wpdb->get_var($sql);
if(!$at_least_one)
	{
	if(!empty($no_events))
		return '<p class="no_events">'.$no_events.'</p>';;
	}
	
	$link = get_rsvpmaker_archive_link();
	$link .= (strpos($link,'?')) ? "&" : '?';
	$link .= 'cd='.date('d',$last_time).'&cm='.date('m',$last_time).'&cy='.date('Y',$last_time);
		$attr = apply_filters( 'next_posts_link_attributes', '' );
		$link = '<a href="' . $link ."\" $attr>" . $label . ' &raquo;</a>';
	if(isset($link))
		return "<p class=\"more_events\">$link</p>";
}

function rsvpmaker_select($select) {
  global $wpdb;

    $select .= ", rsvpdates.meta_value as datetime, meta_id";
  return $select;
}

function rsvpmaker_join($join) {
  global $wpdb;
    return $join." JOIN ".$wpdb->postmeta." rsvpdates ON rsvpdates.post_id = $wpdb->posts.ID AND rsvpdates.meta_key='_rsvp_dates'";
}

function rsvpmaker_groupby($groupby) {
  global $wpdb;
  return " $wpdb->posts.ID ";

}

function rsvpmaker_distinct($distinct){
  return 'DISTINCT';
}

function rsvpmaker_where($where) {

global $startday;
global $datelimit;

$where .= " AND rsvpdates.meta_key='_rsvp_dates' ";

if(isset($_REQUEST["cd"]))
	{
	$where .= " AND rsvpdates.meta_value >= '".$_REQUEST["cy"]."-".$_REQUEST["cm"].'-'.$_REQUEST["cd"]."' ";
	if(!empty($datelimit))
	$where .= "AND rsvpdates.meta_value < DATE_ADD('".$_REQUEST["cy"]."-".$_REQUEST["cm"]."-01',INTERVAL $datelimit) ";
	return $where;
	}
elseif(isset($_REQUEST["cm"]))
	{
	$where .= " AND rsvpdates.meta_value >= '".$_REQUEST["cy"]."-".$_REQUEST["cm"]."-01' ";
	if(!empty($datelimit))
	$where .= "AND rsvpdates.meta_value < DATE_ADD('".$_REQUEST["cy"]."-".$_REQUEST["cm"]."-01',INTERVAL $datelimit) ";
	return $where;
	}
elseif(isset($startday) && $startday)
	{
		$t = strtotime($startday);
		$d = date('Y-m-d',$t);
		 $where .= " AND rsvpdates.meta_value > '$d' ";
		 if(!empty($datelimit))
		 	$where .= " AND rsvpdates.meta_value < DATE_ADD('$d',INTERVAL $datelimit) ";
		 return $where;
	}
elseif(!empty($startday))
	{
		$t = strtotime($startday);
		$d = date('Y-m-d',$t);
		$where .= " AND rsvpdates.meta_value > '$d' AND rsvpdates.meta_value > '$d' ";
		if(!empty($datelimit))
			$where .= "AND rsvpdates.meta_value < DATE_ADD('$d',INTERVAL $datelimit) ";
		return $where;
	}
elseif(isset($_GET["startdate"]))
	{
		$d = $_GET["startdate"];
		$where .= " AND rsvpdates.meta_value > '$d' ";
		if(!empty($datelimit))
			$where .=  " AND rsvpdates.meta_value < DATE_ADD('$d',INTERVAL $datelimit) ";		
		return $where;
	}
else
	{
		$where .= " AND rsvpdates.meta_value > CURDATE( ) ";
		if(!empty($datelimit))
			$where .=  " AND rsvpdates.meta_value < DATE_ADD(CURDATE( ),INTERVAL $datelimit) ";		
		return $where;
	}
}

function rsvpmaker_orderby($orderby) {
  return " rsvpdates.meta_value ";
}

// if listing past dates
function rsvpmaker_where_past($where) {

global $startday;
$where .= " AND rsvpdates.meta_key='_rsvp_dates' ";

if(isset($_REQUEST["cm"]))
	return $where . " AND rsvpdates.meta_value < '".$_REQUEST["cy"]."-".$_REQUEST["cm"]."-31'";
elseif(isset($startday) && $startday)
	{
		$t = strtotime($startday);
		$d = date('Y-m-d',$t);
		return $where . " AND rsvpdates.meta_value < '$d'";
	}
elseif(isset($_GET["startday"]))
	{
		$t = strtotime($_GET["startday"]);
		$d = date('Y-m-d',$t);
		return $where . " AND rsvpdates.meta_value < '$d'";
	}
else
	return $where . " AND rsvpdates.meta_value < CURDATE( )";

}

function rsvpmaker_orderby_past($orderby) {
  return " rsvpdates.meta_value DESC";
}

function rsvpmaker_upcoming ($atts)
{
$no_events = (isset($atts["no_events"])) ? $atts["no_events"] : 'No events currently listed.';

if(isset($atts["calendar"]) && ($atts["calendar"] == 2) )
	return rsvpmaker_calendar($atts);
global $post;
$post_backup = $post;
global $wp_query;
global $wpdb;
global $showbutton;
global $startday;
global $rsvp_options;
global $datelimit;
global $last_time;
$last_time = time();
$listings = '';
$showbutton = true;

$backup = $wp_query;

if(isset($_GET["debug_query"]))
	add_filter('posts_request','rsvpmaker_examine_query');

if(isset($atts["startday"]))
	$startday = $atts["startday"];

$limit = isset($atts["limit"]) ? $atts["limit"] : 10;
if(isset($atts["posts_per_page"]))
	$limit = $atts["posts_per_page"];
if(isset($atts["days"]))
		$datelimit = $atts["days"].' DAY';
else
		$datelimit = '180 DAY';

add_filter('posts_join', 'rsvpmaker_join' );
add_filter('posts_groupby', 'rsvpmaker_groupby' );
add_filter('posts_distinct', 'rsvpmaker_distinct' );
add_filter('posts_fields', 'rsvpmaker_select' );
if(isset($atts["past"]) && $atts["past"])
	{
	add_filter('posts_where', 'rsvpmaker_where_past' );
	add_filter('posts_orderby', 'rsvpmaker_orderby_past' );
	}
else
	{
	add_filter('posts_where', 'rsvpmaker_where' );
	add_filter('posts_orderby', 'rsvpmaker_orderby' );
	}

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$querystring = "post_type=rsvpmaker&post_status=publish&paged=$paged";

if(isset($atts["one"]) && !empty($atts["one"]))
	{
	$querystring .= "&posts_per_page=1";
	if(is_numeric($atts["one"]))
		$querystring .= '&p='.$atts["one"];
	elseif($atts["one"] != 'next')
		$querystring .= '&name='.$atts["one"];
	}
if(isset($atts["type"]))
	$querystring .= "&rsvpmaker-type=".$atts["type"];
if($limit)
	$querystring .= "&posts_per_page=".$limit;
if(isset($atts["add_to_query"]))
	{
		if(!strpos($atts["add_to_query"],'&'))
			$atts["add_to_query"] = '&'.$atts["add_to_query"];
		$querystring .= $atts["add_to_query"];
	}

$wpdb->show_errors();

$wp_query = new WP_Query($querystring);

// clean up so this doesn't interfere with other operations
remove_filter('posts_join', 'rsvpmaker_join' );
remove_filter('posts_groupby', 'rsvpmaker_groupby' );
remove_filter('posts_distinct', 'rsvpmaker_distinct' );
remove_filter('posts_fields', 'rsvpmaker_select' );

if(isset($atts["past"]) && $atts["past"])
	{
	remove_filter('posts_where', 'rsvpmaker_where_past' );
	remove_filter('posts_orderby', 'rsvpmaker_orderby_past' );
	}
remove_filter('posts_where', 'rsvpmaker_where' );
remove_filter('posts_orderby', 'rsvpmaker_orderby' );

ob_start();

if(isset($atts["demo"]))
	{
		$demo = "<div><strong>Shortcode:</strong></div>\n<code>[rsvpmaker_upcoming";
		foreach($atts as $name => $value)
			{
			if($name == "demo")
				continue;
			$demo .= ' '.$name.'="'.$value.'"';
			}
		$demo .= "]</code>\n";
		$demo .= "<div><strong>Output:</strong></div>\n";
		echo $demo;
	}

echo '<div class="rsvpmaker_upcoming">';
	
if ( have_posts() ) {
global $events_displayed;
while ( have_posts() ) : the_post();
$events_displayed[] = $post->ID;

if(is_email_context ()) {
	$embed = event_to_embed($post->ID,$post); // simplified formatting for email
	echo $embed["content"];
	continue;
}
?>

<div id="rsvpmaker-<?php the_ID();?>" <?php post_class();?> itemscope itemtype="http://schema.org/Event" >  
<h1 class="rsvpmaker-entry-title"><a href="<?php the_permalink(); ?>"  itemprop="url"><span itemprop="name"><?php the_title(); ?></span></a></h1>
<div class="rsvpmaker-entry-content">

<?php the_content(); ?>

</div><!-- .entry-content -->

<?php

if(!isset($atts["hideauthor"]) || !$atts["hideauthor"])
{
$authorlink = sprintf( '<span class="author vcard"><a class="url fn n" href="%1$s" title="%2$s">%3$s</a></span>',
	get_author_posts_url( get_the_author_meta( 'ID' ) ),
	sprintf( esc_attr__( 'View all posts by %s', 'rsvpmaker' ), get_the_author() ),
	get_the_author());
?>
<div class="event_author"><?php _e('Posted by','rsvpmaker'); echo " $authorlink on ";?><span class="updated" datetime="<?php the_modified_date('c');?>"><?php the_modified_date(); ?></span></div>
<?php 
}
?>
</div>
<?php
if(is_admin() )
	{
		echo '<p><a href="'.admin_url('post.php?action=edit&post='.$post->ID).'">Edit</a></p>';
	}
endwhile;
?>
<p><?php
if(isset($atts['one']) && $atts['one'])
	echo get_next_events_link(__('More Events','rsvpmaker'));
} 
else
	echo get_next_events_link(__('More Events','rsvpmaker'),$no_events);
echo '</div><!-- end rsvpmaker_upcoming -->';

$wp_query = $backup;
wp_reset_postdata();
$post = $post_backup;
if(	( isset($atts["calendar"]) && $atts["calendar"]) || (isset($atts["format"]) && ($atts["format"] == "calendar") ) )
	if(!(isset($atts['one']) && $atts['one']))
		$listings = rsvpmaker_calendar($atts);

$listings .= ob_get_clean();
return $listings;

}

add_shortcode("rsvpmaker_upcoming","rsvpmaker_upcoming");

//get all of the dates for the month
function rsvpmaker_calendar_where($where) {

global $startday;

if(isset($_REQUEST["cm"]))
	$d = "'".$_REQUEST["cy"]."-".$_REQUEST["cm"]."-01'";
elseif(isset($startday) && $startday)
	{
		$t = strtotime($startday);
		$d = "'".date('Y-m-d',$t)."'";
	}
elseif(isset($_GET["startday"]))
	{
		$t = strtotime($_GET["startday"]);
		$d = "'".date('Y-m-d',$t)."'";
	}
else
		$d = "'".date('Y-m')."-01'";
	//$d = ' CURDATE() ';
	return $where . " AND meta_value > ".$d.' AND meta_value < DATE_ADD('.$d.', INTERVAL 5 WEEK) ';
}

function rsvpmaker_calendar_clear($g) {
return '';
}

function rsvpmaker_item_class($post_id,$post_title) {
$tp = preg_split('/[^A-Za-z]{1,5}/',$post_title);
$tp = array_slice($tp,0,4);
$class = implode('_',$tp);

$tax_terms = wp_get_post_terms($post_id, 'rsvpmaker-type');
if(!empty($tax_terms))
	{
		foreach ($tax_terms as $tax_term) {
			$class .= ' '.preg_replace('/[^A-Za-z]{1,5}/','_',$tax_term->name);
		}
	}

return $class;
}

add_shortcode("rsvpmaker_calendar","rsvpmaker_calendar");
function rsvpmaker_calendar($atts) 
{
global $post;
$post_backup = $post;
global $wp_query;
global $wpdb;
global $showbutton;
global $startday;
global $rsvp_options;
$date_format = (isset($atts["date_format"])) ? $atts["date_format"] : $rsvp_options["short_date"];
if(isset($atts["startday"]))
	$startday = $atts["startday"];
$self = $req_uri = get_permalink();
$req_uri .= (strpos($req_uri,'?') ) ? '&' : '?';

$showbutton = true;

$backup = $wp_query;

//removing groupby, which interferes with display of multi-day events
add_filter('posts_join', 'rsvpmaker_join' );
add_filter('posts_where', 'rsvpmaker_calendar_where',99 );
add_filter('posts_orderby', 'rsvpmaker_orderby' );
add_filter('posts_groupby', 'rsvpmaker_calendar_clear' );
add_filter('posts_distinct', 'rsvpmaker_calendar_clear' );
add_filter('posts_fields', 'rsvpmaker_select' );

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$querystring = "post_type=rsvpmaker&post_status=publish&posts_per_page=-1&paged=$paged";

if(isset($atts["type"]))
	$querystring .= "&rsvpmaker-type=".$atts["type"];
if(isset($atts["add_to_query"]))
	{
		if(!strpos($atts["add_to_query"],'&'))
			$atts["add_to_query"] = '&'.$atts["add_to_query"];
		$querystring .= $atts["add_to_query"];
	}

$wpdb->show_errors();

$wp_query = new WP_Query($querystring);

// clean up so this doesn't interfere with other operations
remove_filter('posts_join', 'rsvpmaker_join' );
remove_filter('posts_where', 'rsvpmaker_calendar_where',99 );
remove_filter('posts_orderby', 'rsvpmaker_orderby' );
remove_filter('posts_groupby', 'rsvpmaker_calendar_clear' );
remove_filter('posts_distinct', 'rsvpmaker_calendar_clear' );
remove_filter('posts_fields', 'rsvpmaker_select' );
$eventarray = array();
if ( have_posts() ) {
while ( have_posts() ) : the_post();

//calendar entry
	if(empty($post->post_title))
		$post->post_title = __('Title left blank','rsvpmaker');

	fix_timezone();
	$t = strtotime($post->datetime);
	$post->duration = get_post_meta($post->ID,'_'.$post->datetime, true);
	$time = ($post->duration == 'allday') ? '' : '<br />&nbsp;'.strftime($rsvp_options["time_format"],$t);
	if(strpos($post->duration,'-'))
		{
		$time .= '-'.strftime($rsvp_options["time_format"],strtotime($post->duration));
		}
	if(isset($_GET["debug"]))
		{
			$msg = sprintf('%s %s %s',$post->post_title,$post->datetime,$post->meta_id);
			rsvpmaker_debug_log($msg);
		}	
	$key = date('Y-m-d',$t);
	$eventarray[$key] = (isset($eventarray[$key])) ? $eventarray[$key] . '<div><a class="calendar_item '.rsvpmaker_item_class($post->ID,$post->post_title).'" href="'.get_post_permalink($post->ID).'" title="'.htmlentities($post->post_title).'">'.$post->post_title.$time."</a></div>\n" : '<div><a class="calendar_item '.rsvpmaker_item_class($post->ID,$post->post_title).'" href="'.get_post_permalink($post->ID).'" title="'.htmlentities($post->post_title).'">'.$post->post_title.$time."</a></div>\n";
	if(!isset($atts["noexpand"]))
		$caldetail[$key] = (isset($caldetail[$key])) ? $caldetail[$key].'<div>'.date($date_format,$t).': <a href="'.get_post_permalink($post->ID).'">'.$post->post_title."</a></div>\n" : '<div>'.date($date_format,$t).': <a href="'.get_post_permalink($post->ID).'">'.$post->post_title."</a></div>\n";
endwhile;
}

$wp_query = $backup;
wp_reset_postdata();

// calendar display routine
$nav = isset($atts["nav"]) ? $atts["nav"] : 'bottom';

if (!isset($_REQUEST["cm"]) || $_REQUEST["cm"] == 0)
	{
	$cy = $cm = 0;
	$nowdate = date("Y-m-d");
	}
else
	{
	$cm = $_REQUEST["cm"];
	$cy = $_REQUEST["cy"];
	$nowdate = date("Y-m-d", mktime(0, 0, 1, $cm, 1, $cy) );
	}

// Check if month and year is valid
if ($cm && $cy && !checkdate($cm,1,$cy)) {
   $errors[] = "The specified year and month (".htmlentities("$cy, $cm").") are not valid.";
   unset($cm); unset($cy);
}

// Give defaults for the month and day values if they were invalid
if (!isset($cm) || $cm == 0) { $cm = date("m"); }
if (!isset($cy) || $cy == 0) { $cy = date("Y"); }

// Start of the month date
$date = mktime(0, 0, 1, $cm, 1, $cy);

// Beginning and end of this month
$bom = mktime(0, 0, 1, $cm,  1, $cy);
$eom = mktime(0, 0, 1, $cm+1, 0, $cy);
$eonext = date("Y-m-d",mktime(0, 0, 1, $cm+2, 0, $cy) );

// Link to previous month (but do not link to too early dates)
$lm = mktime(0, 0, 1, $cm, 0, $cy);
   $prev_link = '<a href="' . $req_uri . strftime('cm=%m&cy=%Y">%B %Y</a>', $lm);

// Link to next month (but do not link to too early dates)
$nm = mktime(0, 0, 1, $cm+1, 1, $cy);
   $next_link = '<a href="' . $req_uri . strftime('cm=%m&cy=%Y">%B %Y</a>', $nm);

// Link to next month (but do not link to too early dates)
$current_link = ' &nbsp;&lt;&nbsp; <a href="' . $req_uri . strftime('cm=%m&cy=%Y">%B %Y', time()).'</a> &nbsp;&gt;&nbsp; ';

$monthafter = mktime(0, 0, 1, $cm+2, 1, $cy);

	$page_id = (isset($_GET["page_id"])) ? '<input type="hidden" name="page_id" value="'. (int) $_GET["page_id"].'" />' : '';

// $Id: cal.php,v 1.47 2003/12/31 13:04:27 goba Exp $

// Begin the calendar table
$content = '';
if(($nav == 'top') || ($nav == 'both')) // either it's top or both
$content .= '<div style="width: 100%; text-align: right;" class="nav"><span class="navprev">'. $prev_link. '</span> / <span class="navnext">'.
     '' . $next_link . "</span></div>";

$content .= '

<table id="cpcalendar" width="100%" cellspacing="0" cellpadding="3"><caption>'.strftime('<b>%B %Y</b>', $bom)."</caption>\n".'<tr>'."\n";

if(isset($atts["weekstart"]) && ($atts["weekstart"] == 'Monday'))
{
$content .= '<thead>
<tr>
<th>'.__('Monday','rsvpmaker').'</th> 
<th>'.__('Tuesday','rsvpmaker').'</th> 
<th>'.__('Wednesday','rsvpmaker').'</th> 
<th>'.__('Thursday','rsvpmaker').'</th> 
<th>'.__('Friday','rsvpmaker').'</th> 
<th>'.__('Saturday','rsvpmaker').'</th> 
<th>'.__('Sunday','rsvpmaker').'</th> 
</tr>
</thead>
';
$weekstart = 1;
}
else
{
$content .= '<thead>
<tr>
<th>'.__('Sunday','rsvpmaker').'</th> 
<th>'.__('Monday','rsvpmaker').'</th> 
<th>'.__('Tuesday','rsvpmaker').'</th> 
<th>'.__('Wednesday','rsvpmaker').'</th> 
<th>'.__('Thursday','rsvpmaker').'</th> 
<th>'.__('Friday','rsvpmaker').'</th> 
<th>'.__('Saturday','rsvpmaker').'</th> 
</tr>
</thead>
';
$weekstart = 0;
}

$content .= "\n<tbody><tr id=\"rsvprow1\">\n";
$rowcount = 1;
// Generate the requisite number of blank days to get things started
for ($days = $i = date("w",$bom); $i > $weekstart; $i--) {
   $content .= '<td class="notaday">&nbsp;</td>';
}
$days = $days - $weekstart;// adjust if first day not sunday

if(isset($_GET['debugpast']))
	$todaydate = date('Y-m-d',strtotime('+2 weeks'));
else
$todaydate = date('Y-m-d');
// Print out all the days in this month
for ($i = 1; $i <= date("t",$bom); $i++) {
  
   // Print out day number and all events for the day
	$thisdate = date("Y-m-",$bom).sprintf("%02d",$i);
	$class = ($thisdate == $todaydate ) ? 'today day' : 'day';
	if($thisdate < $todaydate)
		$class .= ' past';
	if($thisdate > $todaydate)
		$class .= ' future';
   $content .= '<td valign="top" class="'.$class.'">';
   if(!empty($eventarray[$thisdate]) )
   {
   $content .= $i;
   $content .= $eventarray[$thisdate];
   $t = strtotime($thisdate);
   }
   else
   	$content .= '<div class="'.$class.'">' . $i . "</div><p>&nbsp;</p>";
   $content .= '</td>';
  
   // Break HTML table row if at end of week
   if (++$days % 7 == 0)
   	{
		$content .= "</tr>\n";
		$rowcount++;
		$content .= '<tr id="rsvprow'.$rowcount.'">';
	}
}

// Generate the requisite number of blank days to wrap things up
for (; $days % 7; $days++) {
   $content .= '<td class="notaday">&nbsp;</td>';
}

$content .= "\n</tr>";
$content .= "<tbody>\n";

// End HTML table of events
$content .= "\n</table>\n";

if($nav != 'top') // either it's bottom or both
$content .= '<div class="rsvpmaker_nav"><span class="navprev">'. $prev_link. '</span> '.$current_link.' <span class="navnext">'.
     '' . $next_link . "</span></div>";

//jump form
$content .= sprintf('<form id="rsvpmaker_jumpform" action="%s" method="post"> %s <input type="text" name="cm" value="%s" size="4" class="jump" />/<input type="text" name="cy" value="%s" size="4" class="jump" /><button>%s</button>%s</form>', $self,__('Month/Year','rsvpmaker'),date('m',$monthafter),date('Y',$monthafter),__('Go','rsvpmaker'),$page_id);

$calj = "

    $( '.calendar_item' ).tooltip({
        show: null, // show immediately 
        position: { my: \"right top\", at: \"left top\" },
        content: $(this).html(),
        hide: { effect: \"\" }, //fadeOut
        close: function(event, ui){
            ui.tooltip.hover(
                function () {
                    $(this).stop(true).fadeTo(400, 1); 
                },
                function () {
                    $(this).fadeOut(\"400\", function(){
                        $(this).remove(); 
                    })
                }
            );
        }  
    });

";

if(!empty($calj))
	{
$content .= '<script>
jQuery(document).ready(function($) {
'.$calj.'

});
</script>
';
	}
$post = $post_backup;
return $content;
}


function rsvpmaker_template_fields($select) {
  $select .= ", tmeta.meta_value as sked";
  return $select;
}

function rsvpmaker_template_join($join) {
  global $wpdb;
  return $join." JOIN $wpdb->postmeta tmeta ON tmeta.post_id = $wpdb->posts.ID ";
}

function rsvpmaker_template_where($where) {

	return " AND tmeta.meta_key='_sked'";

}

function rsvpmaker_template_orderby($orderby) {
  return " post_title ";
}

function rsvpmaker_template_events_where($where) {
global $rsvptemplate;
	if(isset($_GET["t"]))
		$rsvptemplate = (int) $_GET["t"];
	if(!$rsvptemplate)
		return $where;
	return $where . " AND meta_key='_meet_recur' AND meta_value=$rsvptemplate";
}

//utility function, template tag
function is_rsvpmaker() {
global $post;
if($post->post_type == 'rsvpmaker')
	return true;
else
	return false;
}

function rsvpmaker_timed ($atts, $content = '') {
if(!empty($atts['start']))
	{
		$start = strtotime($atts['start']);
		$now = current_time('timestamp');
		if($now < $start)
			{
				if(isset($_GET["debug"]))
					return sprintf('<p>start %s / now %s</p>',date('r',$start), date('r',$now));
				elseif(isset($atts["too_early"]))
					return '<p>'.$atts["too_early"].'</p>';
				else
					return '';
			}
	}
if(!empty($atts['end']))
	{
		$end = strtotime($atts['end']);
		$now = current_time('timestamp');
		if($now > $end)
			{
				if(isset($_GET["debug"]))
					return sprintf('<p>end %s / now %s</p>',date('r',$end), date('r',$now));
				elseif(isset($atts["too_late"]))
					return '<p>'.$atts["too_late"].'</p>';
				else
					return '';
			}
	}

if(!empty($atts["post_id"]))
{
$qs = 'posts_per_page=1&p='. (int) $atts["post_id"];
if($atts["post_type"])
	$qs .= '&post_type='.$atts["post_type"];
$cq = new WP_Query($qs);
if ( $cq->have_posts() ) : $cq->the_post();
ob_start();
global $post;
$post_backup = $post;
?>
<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
<h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
<div class="entry-content">
<?php 
the_content();
?>
</div><!-- .entry-content -->
</div>
<?php 
$content = ob_get_clean();	
$post = $post_backup;
endif;
}

// if we clear these two hurdles, return the content
if(!empty($atts["style"]))
	$content = '<div style="'.$atts["style"].'">'.$content.'</div>';
	
return $content;

}

function debug_request_rsvp($sql) {
	rsvpmaker_debug_log($sql,'request sql rsvp');
	return $sql;
}

add_filter('posts_request','debug_request_rsvp');

add_shortcode('rsvpmaker_timed','rsvpmaker_timed');

add_shortcode('rsvpmaker_looking_ahead','rsvpmaker_looking_ahead');

function rsvpmaker_looking_ahead($atts) {
global $last_time;
global $events_displayed;
$listings = '';
$limit = isset($atts["limit"]) ? $atts["limit"] : 10;
if(isset($atts["days"]))
		$datelimit = $atts["days"].' DAY';
else
		$datelimit = '30 DAY';

if(!$last_time)
	return 'last time not found';

$results = get_future_events("meta_value > '".date('Y-m-d',$last_time)."' AND meta_value < DATE_ADD('".date('Y-m-d',$last_time)."',INTERVAL $datelimit)", $limit, ARRAY_A);

if($results)
foreach($results as $row)
	{
	if(in_array($row["postID"], $events_displayed) )
		continue;
		
	$t = strtotime($row["datetime"]);
	if(empty($dateline[$row["postID"]]))
		$dateline[$row["postID"]] = '';
	else
		$dateline[$row["postID"]] .= ", ";
	$dateline[$row["postID"]] .= date('M. j',$t);
	$eventlist[$row["postID"]] = $row;
	}

//strpos test used to catch either "headline" or "headlines"
if(isset($eventlist) && is_array($eventlist))
{
foreach($eventlist as $event)
	{
	if(isset($atts["permalinkhack"]))
		$permalink = site_url() ."?p=".$event["postID"];
	else
		$permalink = get_post_permalink($event["postID"]);
	$listings .= sprintf('<li><a href="%s">%s</a> %s</li>'."\n",$permalink,$event["post_title"],$dateline[$event["postID"]]);
	}

	if(!empty($rsvp_options["eventpage"]))
		$listings .= '<li><a href="'.$rsvp_options["eventpage"].'">'.__("Go to Events Page",'rsvpmaker')."</a></li>";

	if(isset($atts["title"]))
		$listings = "<p><strong>".$atts["title"]."</strong></p>\n<ul id=\"eventheadlines\">\n$listings</ul>\n";
	else
		$listings = "<ul id=\"eventheadlines\">\n$listings</ul>\n";
}//end if $eventlist
return $listings;
}

function get_adjacent_rsvp_join($join) {
global $post;
if($post->post_type != 'rsvpmaker')
	return $join;
global $wpdb;
return $join .' JOIN '.$wpdb->postmeta.' ON p.ID='.$wpdb->postmeta.".post_id AND meta_key='_rsvp_dates' ";
}

add_filter('get_previous_post_join','get_adjacent_rsvp_join');
add_filter('get_next_post_join','get_adjacent_rsvp_join');

function get_adjacent_rsvp_sort($sort) {
global $post;
if($post->post_type != 'rsvpmaker')
	return $sort;
global $wpdb;
$sort = str_replace('p.post_date',$wpdb->postmeta.'.meta_value',$sort);

return $sort;
}
add_filter('get_previous_post_sort','get_adjacent_rsvp_sort');
add_filter('get_next_post_sort','get_adjacent_rsvp_sort');


function get_adjacent_rsvp_where($where) {
global $post;
if($post->post_type != 'rsvpmaker')
	return $where;
global $wpdb;
$op = strpos($where, '>') ? '>' : '<';
$current_event_date = $wpdb->get_var("select meta_value from ".$wpdb->postmeta." WHERE meta_key='_rsvp_dates' AND post_id=".$post->ID);
//split and modify
$wparts = explode('p.post_type',$where);//

$where = "WHERE ".$wpdb->postmeta.".meta_value $op '$current_event_date' AND p.ID != $post->ID AND p.post_type".$wparts[1];
return $where;
}

add_filter('get_previous_post_where','get_adjacent_rsvp_where');
add_filter('get_next_post_where','get_adjacent_rsvp_where');

// based on https://gist.github.com/hugowetterberg/81747
function rsvp_ical_split($preamble, $value) {
  $value = trim($value);
  $value = strip_tags($value);
  $value = str_replace("\n", "\\n", $value);
  $value = str_replace("\r", "", $value);
  $value = preg_replace('/\s{2,}/', ' ', $value);
  $preamble_len = strlen($preamble);
  $lines = array();
  while (strlen($value)>(75-$preamble_len)) {
    $space = (75-$preamble_len);
    $mbcc = $space;
    while ($mbcc) {
      $line = mb_substr($value, 0, $mbcc);
      $oct = strlen($line);
      if ($oct > $space) {
        $mbcc -= $oct-$space;
      }
      else {
        $lines[] = $line;
        $preamble_len = 1; // Still take the tab into account
        $value = mb_substr($value, $mbcc);
        break;
      }
    }
  }
  if (!empty($value)) {
    $lines[] = $value;
  }
  return join($lines, "\n\t");
}

function rsvpmaker_to_ical () {
global $post;
global $rsvp_options;
global $wpdb;
if(($post->post_type != 'rsvpmaker') )
	return;
header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $post->post_name.'.ics');
$sql = "SELECT *, meta_value as datetime FROM ".$wpdb->postmeta." WHERE meta_key='_rsvp_dates' AND post_id=".$post->ID.' ORDER BY meta_value';
$daterow = $wpdb->get_row($sql);
$duration = get_post_meta($post->ID,'_'.$daterow->datetime, true);
fix_timezone();
$duration = get_utc_ical ( (strpos($duration,'-') ) ? $post->ID : $daterow->datetime . ' +1 hour' );
$start = get_utc_ical ($daterow->datetime);
$hangout = get_post_meta($post->ID, '_hangout',true);
$url = (!empty($hangout)) ? $hangout : get_permalink($post->ID);

$desc = '';
if(!empty($hangout))
	$desc = "Google Hangout: ".$hangout." ";
$desc .= "Event info:" . get_permalink($post->ID);
$desc = rsvp_ical_split("DESCRIPTION:", $desc);

printf('BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//hacksw/handcal//NONSGML v1.0//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
DTEND:%s
UID:%s
DTSTAMP:%s
DESCRIPTION:%s
URL;VALUE=URI:%s
SUMMARY:%s
DTSTART:%s
ORGANIZER;CN=%s:MAILTO:%s
END:VEVENT
END:VCALENDAR
',$duration,$start.'-'.$post->ID.'@'.$_SERVER['SERVER_NAME'],date('Ymd\THis\Z'), $desc, $url, $post->post_title, $start, get_bloginfo('name'), $rsvp_options["rsvp_to"]);
exit;
}

if(isset($_GET["ical"])) 
	add_action('wp','rsvpmaker_to_ical');

function rsvpmaker_to_gcal($post,$datetime,$duration) {
return sprintf('http://www.google.com/calendar/event?action=TEMPLATE&text=%s&dates=%s/%s&details=%s&location=&trp=false&sprop=%s&sprop=name:%s',urlencode($post->post_title),get_utc_ical ($datetime),get_utc_ical ($duration), urlencode(get_bloginfo('name')),get_permalink($post->ID), urlencode(get_bloginfo('name')) );
}

function get_utc_ical ($timestamp) {
return gmdate('Ymd\THis\Z', strtotime($timestamp));
}

function rsvp_row_to_profile($row) {
if(empty($row["details"]) )
	$profile = array();
else
	$profile = unserialize($row["details"]);
foreach($row as $field => $value)
	{
		if(isset($profile[$field]) || ($field == 'details') )
			continue;
		else
			$profile[$field] = $value;
	}
return $profile;
}

function rsvpmaker_type_dateorder ( $sql ) {
echo $sql;
return $sql;
}

function rsvpmaker_archive_pages ($query) {
	if(is_admin())
		return;
	if(is_archive() && isset($query->query["post_type"]) && ($query->query["post_type"] == 'rsvpmaker'))
	{
	add_filter('posts_join', 'rsvpmaker_join' );
	add_filter('posts_groupby', 'rsvpmaker_groupby' );
	add_filter('posts_distinct', 'rsvpmaker_distinct' );
	add_filter('posts_fields', 'rsvpmaker_select' );
	add_filter('posts_where', 'rsvpmaker_where' );
	add_filter('posts_orderby', 'rsvpmaker_orderby' );
	if(isset($_GET["debug_query"]))
		add_filter('posts_request','rsvpmaker_examine_query');
	}
	if(is_archive() && !empty($query->query["rsvpmaker-type"]))
	{
	add_filter('posts_join', 'rsvpmaker_join' );
	add_filter('posts_groupby', 'rsvpmaker_groupby' );
	add_filter('posts_distinct', 'rsvpmaker_distinct' );
	add_filter('posts_fields', 'rsvpmaker_select' );
	add_filter('posts_where', 'rsvpmaker_where' );
	add_filter('posts_orderby', 'rsvpmaker_orderby' );
	if(isset($_GET["debug_query"]))
		add_filter('posts_request','rsvpmaker_examine_query');
	}
}

add_action( 'pre_get_posts', 'rsvpmaker_archive_pages' );

function get_rsvpmaker_archive_link($page = 1) {
$link = get_post_type_archive_link('rsvpmaker');
$link .= (strpos($link,'?')) ? '&paged='.$page : '?paged='.$page;
return $link;
}

function rsvpmaker_examine_query ($request) {
$log = var_export($request,true);
mail('david@carrcommunications.com','query test',$log);
return $request;
}

function rsvpmaker_facebook_meta () {
global $post;
global $rsvp_options;
if(!isset($post->post_type) || ($post->post_type != 'rsvpmaker'))
	return; // don't mess with other post types
fix_timezone();
$tstring = get_rsvp_date($post->ID);
if(empty($tstring)) // might be a replay landing page or other non-calendar item
	return;
$ts = strtotime($tstring);
if(!strpos($rsvp_options["time_format"],'%Z') && get_post_meta($post->ID,'_add_timezone',true) )
	{
	$rsvp_options["time_format"] .= ' %Z';
	}
$date = strftime($rsvp_options["short_date"].' '.$rsvp_options["time_format"],$ts);
$title = get_the_title($post->ID);
$titlestr = $title . ' - '. $date. ' - '.get_bloginfo('name');
printf('<meta property="og:title" content="%s" /><meta property="twitter:title" content="%s" />',$titlestr,$titlestr);
}

if(isset($rsvp_options["social_title_date"]) && $rsvp_options["social_title_date"])
	add_action( 'wp_head', 'rsvpmaker_facebook_meta', 999 );

function ylchat ($atts) {
global $post;
fix_timezone();

preg_match('/(https:\/\/www.youtube.com\/watch\?v=|https:\/\/youtu.be\/)([^\s]+)/',$post->post_content,$matches);

if(!isset($matches[2]))
	return;

$url = sprintf('https://www.youtube.com/live_chat?v=%s&amp;embed_domain=%s',$matches[2],$_SERVER['SERVER_NAME']);

$login_url = 'https://accounts.google.com/ServiceLogin?uilel=3&service=youtube&hl=en&continue=https%3A%2F%2Fwww.youtube.com%2Fsignin%3Ffeature%3Dcomments%26next%3D%252Flive_chat%253Fis_popout%253D1%2526v%253D'.$matches[2].'%26hl%3Den%26action_handle_signin%3Dtrue%26app%3Ddesktop&passive=true';

$test = file_get_contents($url);
if(strpos($test,'live-chat-unavailable'))
	return;

$note = (isset($atts["note"])) ? '<p>'.$atts["note"].'</p>' : '';
$width = (isset($atts["width"])) ? $atts["width"] : '100%';
$height = (isset($atts["height"])) ? $atts["height"] : '200';
if(isset($_GET["height"]))
	$height = (int) $_GET["height"];

return $note . sprintf('<iframe src="%s" width="%s" height="%s"></iframe>',$url,$width,$height) . sprintf('<p>%s <a href="%s" target="_blank">%s</a>. %s</p>',__('If the chat prompt does not appear below,','rsvpmaker'), $login_url, __('please login to your YouTube/Google account','rsvpmaker'), __('Then refresh this window.','rsvpmaker'));
}

add_shortcode('ylchat','ylchat');

function rsvpmaker_next ($atts)
{
$atts["post_id"] = 'next';
return rsvpmaker_one($atts);
}

add_shortcode("rsvpmaker_next","rsvpmaker_next");

function rsvpmaker_one ($atts)
{
global $post;
global $wp_query;
global $wpdb;
global $showbutton;
global $startday;
global $rsvp_options;
$showbutton = (isset($atts["showbutton"])) ? $atts["showbutton"] : 0;
$content = '';

if(isset($atts["one_format"]) && (($atts["one_format"] == 'button') || ($atts["one_format"] == 'button_only')))
	$showbutton = 1;
if(isset($atts["post_id"]))
{
//	if($atts["post_id"] == 'next')
//		return rsvpmaker_next($atts);
	$post_id = $atts["post_id"];	
}
elseif(isset($atts["one"]))
	$post_id = $atts["one"];
else
	return;

$backup_post = $post;
$backup_query = $wp_query;

if($atts["post_id"] == 'next')
{
add_filter('posts_join', 'rsvpmaker_join' );
add_filter('posts_groupby', 'rsvpmaker_groupby' );
add_filter('posts_distinct', 'rsvpmaker_distinct' );
add_filter('posts_fields', 'rsvpmaker_select' );
add_filter('posts_where', 'rsvpmaker_where' );
add_filter('posts_orderby', 'rsvpmaker_orderby' );
$querystring = "post_type=rsvpmaker&post_status=publish&posts_per_page=1";
if(isset($atts["type"]))
	$querystring .= "&rsvpmaker-type=".$atts["type"];
if(isset($atts["add_to_query"]))
	{
		if(!strpos($atts["add_to_query"],'&'))
			$atts["add_to_query"] = '&'.$atts["add_to_query"];
		$querystring .= $atts["add_to_query"];
	}
$wp_query = new WP_Query($querystring);

remove_filter('posts_join', 'rsvpmaker_join' );
remove_filter('posts_groupby', 'rsvpmaker_groupby' );
remove_filter('posts_distinct', 'rsvpmaker_distinct' );
remove_filter('posts_fields', 'rsvpmaker_select' );
remove_filter('posts_where', 'rsvpmaker_where' );
remove_filter('posts_orderby', 'rsvpmaker_orderby' );
}
else
{
$querystring = "post_type=rsvpmaker&post_status=publish&posts_per_page=1&p=". (int) $atts["post_id"];
$wp_query = new WP_Query($querystring);	
}
$wp_query->is_single = true;
	
if ( have_posts() ) {
global $events_displayed;
the_post();
$atts["post_id"] = $post_id = $post->ID;
if(!empty($atts["hide_past"]))
{
$offset = $atts["hide_past"];
if(!is_rsvpmaker_future($post_id, $offset))
	return;
}

if(empty($atts["one_format"]) || ($atts["one_format"] == 'button'))
{//one post loop
ob_start();
?>
<div class="rsvpmaker_embedded">
<div id="rsvpmaker-<?php the_ID();?>" <?php post_class();?> itemscope itemtype="http://schema.org/Event" >  

<?php 
if(!isset($atts["hide_title"]) || !$atts["hide_title"])
{
?>
<h2 class="rsvpmaker-entry-title" itemprop="url"><span itemprop="name"><?php the_title(); ?></span></h2>
<?php
}
?>
<div class="rsvpmaker-entry-content">

<?php 
	
	if($atts["one_format"] == 'button') {
		$content = embed_dateblock($atts);
	if(is_rsvpmaker_future($post_id)) 
		{
		$rsvplink = get_permalink($post_id);
		if(strpos($rsvplink,'?') )
			$rsvp_options["rsvplink"] = str_replace('?','&',$rsvp_options["rsvplink"]);
		$rsvp = sprintf($rsvp_options['rsvplink'],$rsvplink);
		}
	else
		{
		$rsvp = __('Event date is past','rsvpmaker');
		}
	echo $content.'<div style="margin-top: 10px;">'.$rsvp.'</div>';
	}
	else
		the_content(); ?>
</div><!-- .entry-content -->

<?php
if(is_admin() )
	{
		echo '<p><a href="'.admin_url('post.php?action=edit&post='.$post->ID).'">Edit</a></p>';
	}
echo '</div></div><!-- end rsvpmaker_embedded -->';
$content = ob_get_clean();	
}
else
{
	if($atts["one_format"] == 'button_only')
	{
	if(is_rsvpmaker_future($post_id)) 
		{
		$rsvplink = get_permalink($post_id);
		if(strpos($rsvplink,'?') )
			$rsvp_options["rsvplink"] = str_replace('?','&',$rsvp_options["rsvplink"]);
		$content = sprintf($rsvp_options['rsvplink'],$rsvplink);
		}
	else
		{
		$content = __('Event date is past','rsvpmaker');
		}
	}
	elseif($atts["one_format"] == 'embed_dateblock') {
		$content = embed_dateblock($atts);
	}
	elseif($atts["one_format"] == 'form') {
		if(is_rsvpmaker_future($post_id)) 
			$content = rsvpmaker_form($atts);
		else
			$content = __('Event date is past','rsvpmaker');
	}
	elseif($atts["one_format"] == 'compact') {
		$content = rsvpmaker_compact($atts);
	}
	elseif($atts["one_format"] == 'compact_form') {
		$atts["show_form"] = 1;
		$content = rsvpmaker_compact($atts);
	}
}
}

$wp_query = $backup_query;
$post = $backup_post;
wp_reset_postdata();

if(!empty($atts["style"]))
	$content = '<div style="'.$atts["style"].'">'.$content.'</div>';
return $content;
}

function rsvpmaker_compact ($atts)
{
global $post;
global $wp_query;
global $wpdb;
global $showbutton;
global $startday;
global $rsvp_options;
if(isset($atts["post_id"]))
{
	$post_id = (int) $atts["post_id"];	
}
elseif(isset($atts["one"]))
	$post_id = (int) $atts["one"];
else
	return;
	
$backup_post = $post;
$backup_query = $wp_query;

$querystring = "post_type=rsvpmaker&post_status=publish&posts_per_page=1&p=".$post_id;

$wp_query = new WP_Query($querystring);
$wp_query->is_single = false;

global $rsvp_options;
$time_format = $rsvp_options["time_format"];
if(!strpos($time_format,'%Z'))
	{
	if(get_post_meta($post_id,'_add_timezone',true))
		$time_format .= ' %Z';
	}

ob_start();

echo '<div class="rsvpmaker_compact">';

if ( have_posts() ) {
global $events_displayed;
while ( have_posts() ) : the_post();

	$datestamp = get_rsvp_date($post_id);
	$dur = get_post_meta($post_id,'_'.$datestamp, true);
	fix_timezone();
	$t = strtotime($datestamp);
	$dateblock = ', '.utf8_encode(strftime($rsvp_options["short_date"],$t));
	if($dur != 'allday')
		{
		$dateblock .= strftime(', '.$time_format,$t);
		}
	// dchange
	$dateblock = str_replace(':00','',$dateblock);
	
?>
<div id="rsvpmaker-<?php the_ID();?>" <?php post_class();?> itemscope itemtype="http://schema.org/Event" >  
<p class="rsvpmaker-compact-title" itemprop="url"><span itemprop="name"><?php the_title(); echo $dateblock; ?></span></p>
<?php
if(is_rsvpmaker_future($post_id))
{
	if(isset($atts["show_form"]))
		echo rsvpmaker_form($atts);
	else{
	$rsvplink = get_permalink($post_id);
	if(strpos($rsvplink,'?') )
		$rsvp_options["rsvplink"] = str_replace('?','&',$rsvp_options["rsvplink"]);
	printf($rsvp_options['rsvplink'],$rsvplink);	
	}
}
	else
		_e('Event date is past','rsvpmaker');
endwhile;
}
echo '</div></div><!-- end rsvpmaker_upcoming -->';

$wp_query = $backup_query;
wp_reset_postdata();

return ob_get_clean();
}



add_shortcode("rsvpmaker_one","rsvpmaker_one");

function rsvpmaker_form( $atts, $form_content='' ) {
if(isset($atts["post_id"]))
	$post_id = (int) $atts["post_id"];
elseif(isset($atts["one"]))
	$post_id = (int) $atts["one"];
else
	return;
	
if(!is_rsvpmaker_future($post_id)) 
	return __('Event date is past','rsvpmaker');
	
global $post;
global $wp_query;
global $rsvp_options;
$backup_post = $post;
$backup_query = $wp_query;
$post = get_post($post_id);
if(empty($post))
	{
	$post = $backup_post;
	$wp_query = $backup_query;
	return;
	}
$wp_query->is_single = true;
$output = event_content('', true, $form_content); // no content, $formonly true supresses date display
$post = $backup_post;
$wp_query = $backup_query;
return $output;
}

add_shortcode('rsvpmaker_form','rsvpmaker_form');

function rsvpmaker_replay_form($event_id) {
	
if(is_rsvpmaker_future($event_id, 1)) 
	{
		$permalink = get_permalink($event_id);
		return sprintf('<a href="%s">%s</a>',$permalink,__('Please register'));
	}
; // if start time in the future (or within one hour)

	
	
global $post;
$permalink = get_permalink($post->ID);
$form = get_post_meta($event_id,'_rsvp_form',true);
$captcha = get_post_meta($event_id,'_rsvp_captcha',true);
$rsvp_instructions = get_post_meta($event_id,'_rsvp_instructions',true);
ob_start();
if(isset($_GET["err"]))
{
	echo '<div style="padding: 10px; margin: 10px; width: 100%; border: medium solid red;">'.htmlentities($_GET["err"]).'</div>';
}
;?>

<form id="rsvpform" action="<?php echo $permalink;?>" method="post">
<input type="hidden" name="replay_rsvp" value="<?php echo $permalink;?>" />
<h3 id="rsvpnow"><?php echo __('Please Register','rsvpmaker');?></h3> 

  <?php if($rsvp_instructions) echo '<p>'.nl2br($rsvp_instructions).'</p>';?>

<?php 
echo do_shortcode($form);

if($captcha)
{
?>
<p><img src="<?php echo plugins_url('/captcha/captcha_ttf.php',__FILE__);  ?>" alt="CAPTCHA image">
<br />
<?php _e('Type the hidden security message','rsvpmaker'); ?>:<br />                    
<input maxlength="10" size="10" name="captcha" type="text" />
</p>
<?php
do_action('rsvpmaker_after_captcha');
}
if(function_exists('rsvpmaker_recaptcha_output'))
	rsvpmaker_recaptcha_output();
global $rsvp_required_field;
if(isset($rsvp_required_field) )
	echo '<div id="jqerror"></div><input type="hidden" name="required" id="required" value="'.implode(",",$rsvp_required_field).'" />';
?>
        <p
          <input type="submit" id="rsvpsubmit" name="Submit" value="<?php  _e('Submit','rsvpmaker');?>" /> 
        </p> 
<input type="hidden" name="rsvp_id" id="rsvp_id" value="" /><input type="hidden" id="event" name="event" value="<?php echo $event_id;?>" /><input type="hidden" name="landing_id" value="<?php echo $post->ID;?>" /><?php wp_nonce_field('rsvp_replay','rsvp_replay_nonce'); ?>
</form>	
<?php
return ob_get_clean();
}

function rsvpmaker_archive_loop_end () {
global $wp_query;
global $rsvpwidget;
if (!empty($rsvpwidget) || empty($wp_query->query["post_type"]) )
	return;
if(is_archive() && ($wp_query->query["post_type"] == 'rsvpmaker'))
	{
?>
<div class="navigation"><p><?php posts_nav_link(' | ','&laquo; '.__('Previous Events','rsvpmaker'),__('More Events','rsvpmaker').' &raquo;'); ?></p></div>
<?php
	}
}

add_action('loop_end','rsvpmaker_archive_loop_end');

//keep jetpack from messing up
function rsvpmaker_no_related_posts( $options ) {
    global $post;
	if(($post->post_type == 'rsvpmaker' ) || ($post->post_type == 'rsvpemail' ))
	{
        $options['enabled'] = $options['headline'] = false;
    }
    return $options;
}
add_filter( 'jetpack_relatedposts_filter_options', 'rsvpmaker_no_related_posts' );

function rsvp_report_this_post() {
global $wpdb;
global $rsvp_options;
global $post;
if(empty($post->ID))
	return;

	$eventid = $post->ID;
$o = "<h2>".__("RSVPs",'rsvpmaker')."</h2>\n";
	$sql = "SELECT * FROM ".$wpdb->prefix."rsvpmaker WHERE event=$eventid  ORDER BY yesno DESC, last, first";
	$wpdb->show_errors();
	$results = $wpdb->get_results($sql, ARRAY_A);
	if(empty($results))
		return $o . '<p>'.__('None','rsvpmaker').'</p>';
	ob_start();
	format_rsvp_details($results, false);
	$o .= ob_get_clean();
return $o;
}

function rsvpmaker_hide_menu ($menu)
{
	global $post;
	if(isset($post->post_type) && $post->post_type != 'page')
		return $menu;
	if(isset($post->ID) && get_post_meta($post->ID,'rsvpmaker_hide_menu',true))
		return '';
	return $menu;
}
if(!is_admin())
add_filter('wp_nav_menu','rsvpmaker_hide_menu');

function rsvplanding_register_meta_boxes() {
    add_meta_box( 'rsvplanding-box-id', __( 'Hide The Menu on This Page', 'rsvpmaker' ), 'rsvplanding_my_display_callback', 'page', 'advanced', 'low' );
}
add_action( 'add_meta_boxes', 'rsvplanding_register_meta_boxes' );

function rsvplanding_my_display_callback( $post ) {
$on = get_post_meta($post->ID,'rsvpmaker_hide_menu',true);
$checked = ($on) ? ' checked="checked" ' : '';
printf('<input type="checkbox" name="rsvpmaker_hide_menu" value="1" %s> Hide menu (<em>Turn a full-width page template into a landing page</em>)', $checked);
wp_nonce_field( 'rsvpmaker_hide_menu_action', 'rsvpmaker_hide_menu_nonce' );
	// Display code/markup goes here. Don't forget to include nonces!
}

function rsvplanding_save_meta_box( $post_id ) {
	if(isset($_POST['rsvpmaker_hide_menu_nonce']) && wp_verify_nonce( $_POST['rsvpmaker_hide_menu_nonce'], 'rsvpmaker_hide_menu_action' ))
		update_post_meta($post_id,'rsvpmaker_hide_menu',isset($_POST['rsvpmaker_hide_menu']));
}
add_action( 'save_post', 'rsvplanding_save_meta_box' );

function clear_rsvp_cookies () {
	if(isset($_GET['clear']))
	{
		if(isset($_COOKIE))
			foreach($_COOKIE as $name => $value)
			{
				if(strpos($name,'svp_for'))
				{
					setcookie($name,0);//set to no value
					echo ' clear '.$name;
				}
			}
	}
}

add_action('wp','clear_rsvp_cookies');

function sked_to_text ($sked) {
	global $rsvp_options;
		$s = '';
		$weeks = (empty($sked["week"])) ? array(0) : $sked["week"];
		$dows = (empty($sked["dayofweek"])) ? array() : $sked["dayofweek"];

		$dayarray = Array(__("Sunday",'rsvpmaker'),__("Monday",'rsvpmaker'),__("Tuesday",'rsvpmaker'),__("Wednesday",'rsvpmaker'),__("Thursday",'rsvpmaker'),__("Friday",'rsvpmaker'),__("Saturday",'rsvpmaker'));
		$weekarray = Array(__("Varies",'rsvpmaker'),__("First",'rsvpmaker'),__("Second",'rsvpmaker'),__("Third",'rsvpmaker'),__("Fourth",'rsvpmaker'),__("Last",'rsvpmaker'),__("Every",'rsvpmaker'));
		if((int)$weeks[0] == 0)
			$s = __('Schedule Varies','rsvpmaker');
		else
			{
			foreach($weeks as $week)
				{
				if(empty($s))
					$s = '';
				else
					$s .= '/ ';
				$s .= $weekarray[(int) $week].' ';
				}
			foreach($dows as $dow)
				$s .= $dayarray[(int) $dow] . ' ';	
			}
		fix_timezone();
		$t = mktime($sked["hour"],$sked["minutes"]);
		$dateblock = $s.' '.strftime($rsvp_options["time_format"],$t);
	
	return $dateblock;
}


function signed_up_ajax () {
if(empty($_REQUEST['action']) || $_REQUEST['action'] != 'signed_up')
	return;	
if(!isset($_GET['event_count']))
	return;
global $wpdb;
$post_id = $_GET['event_count'];
$sql = "SELECT count(*) FROM ".$wpdb->prefix."rsvpmaker WHERE event=$post_id AND yesno=1 ORDER BY id DESC";
$total = (int) $wpdb->get_var($sql);
$rsvp_max = get_post_meta($post_id,'_rsvp_max',true);
if($total)
{
$output = $total.' '.__('signed up so far.','rsvpmaker');
if($rsvp_max)
	$output .= ' '.__('Limit','rsvpmaker').': '.$rsvp_max;
echo '<p class="signed_up">'.$output.'</p>';
}
die();
}
add_action('init','signed_up_ajax');
?>