<?php  
/* 
Template Name: Registration page
*/
get_header();
?>
<div class = "panel-content">
<div class="wrap registration_page">
	<?php
	global $wpdb, $current_user, $user_ID;
	$error = '';
	$success = '';
 
	if(isset($_POST['task']) && $_POST['task'] == 'register' ) {
		$password = $wpdb->escape(trim($_POST['password']));
		$first_name = $wpdb->escape(trim($_POST['first_name']));
		$last_name = $wpdb->escape(trim($_POST['last_name']));
		$email = $wpdb->escape(trim($_POST['email']));
		$username = $wpdb->escape(trim($_POST['username']));
		
		if( $email == "" || $password == "" || $username == "" || $first_name == "" || $last_name == "") {
			$error= 'Fill out all fields.';
		} 
		else {
		       	if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$error= 'Check email address.';
			} else if(email_exists($email) ) {
				$error= 'Email used.';
			} else if (username_exists($username) ){
				$error = 'Username taken.';
			} else {
				//https://developer.wordpress.org/reference/functions/wp_insert_user/ 
				$user_id = wp_insert_user( array ('first_name' => apply_filters('pre_user_first_name', $first_name),
					'last_name' => apply_filters('pre_user_last_name', $last_name),
					'user_pass' => apply_filters('pre_user_user_pass', $password),
					'user_login' => apply_filters('pre_user_user_login', $username),
					'user_email' => apply_filters('pre_user_user_email',
				       	$email), 'role' => 'subscriber' ) );
				if( is_wp_error($user_id) ) {
					$error= 'Error on user creation.';
				} else {
					do_action('user_register', $user_id);
					$success = $username. ' succesfully registered!';
				}
			
			}
		}
	}
	?>
	<div id="regmsg">
		<?php 
			if(! empty($error) ) :
				echo '<p class="err">'.$error.'</p>';
			endif;
		
			if(! empty($success) ) :
				echo '<p class="succ">'.$success.'';
			$homeUrl = get_home_url();		
			echo '<p class="error">Thank you for registering to be hacked. Click <a href='.$homeUrl.'>HERE</a> to make things worse.</p>';
				exit;		
			endif;
		?>
	</div>
 
	<form method="post">
		<h2>Create account</h2>
		<p class="reg_entry_label"><label>First Name</label></p>
		<p><input type="text" value="" name="first_name" id="first_name" /></p>
		<p class="reg_entry_label"><label>Last Name</label></p>
		<p><input type="text" value="" name="last_name" id="last_name" /></p>
		<p class="reg_entry_label"><label>Email</label></p>
		<p><input type="text" value="" name="email" id="email" /></p>
		<p class="reg_entry_label"><label>Username</label></p>
		<p><input type="text" value="" name="username" id="username" /></p>
		<p class="reg_entry_label"><label>Password</label></p>
		<p><input type="password" value="" name="password" id="password" /></p>
		<div class="alignleft"><p><?php if($sucess != "") { echo $sucess; } ?> <?php if($error!= "") { echo $error; } ?></p></div>
		<button type="submit" name="btnregister" class="button" >Register</button>
		<input type="hidden" name="task" value="register" />
	</form>
 
</div>
</div>
<?php get_footer() ?>
