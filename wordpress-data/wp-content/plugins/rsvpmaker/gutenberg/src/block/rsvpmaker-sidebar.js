import './state.js';
const { withState } = wp.compose;
const { subscribe } = wp.data;
const { DateTimePicker } = wp.components;
const { TimePicker } = wp.components;
const { getSettings } = wp.date;
var el = wp.element.createElement;
const { __ } = wp.i18n; // Import __() from wp.i18n
//var PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;

if(rsvpmaker_type == 'rsvpmaker')
	{
wp.data.dispatch('rsvpevent').setRSVPdate(rsvpmaker_ajax._rsvp_first_date);
var datestring = '';
var dateaction = "action=rsvpmaker_date&nonce="+rsvpmaker_ajax.ajax_nonce+"&post_id="+rsvpmaker_ajax.event_id;

function related_link() {
	if(rsvpmaker_json.projected_url)
		{
		return <div class="rsvp_related_links"><p><a href={rsvpmaker_ajax.rsvpmaker_details}>RSVP / Event Options</a></p><p><a href={rsvpmaker_json.projected_url}>{rsvpmaker_json.projected_label}</a></p></div>;	
		}
	if(rsvpmaker_json.template_url)
		{
		return <div class="rsvp_related_links"><p><a href={rsvpmaker_ajax.rsvpmaker_details}>RSVP / Event Options</a></p><p><a href={rsvpmaker_json.template_url}>{rsvpmaker_json.template_label}</a></p></div>;	
		}
	return <div class="rsvp_related_links"><p><a href={rsvpmaker_ajax.rsvpmaker_details}>RSVP / Event Options</a></p></div>;	
	}

function get_template_prompt () {
	var post_id = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'id' );
	let parts = window.location.href.split('wp-admin/');
	let template_url = parts[0] + 'wp-admin/edit.php?post_type=rsvpmaker&page=rsvpmaker_template_list&t=' + post_id;

	var template_prompt='';
	if(post_id)
		return <p id="template_prompt"><a href={template_url}>Create/update events from template</a></p>;
	return;
}

const RSVPMakerSidebarPlugin = function() {

if(rsvpmaker_ajax.template_msg)
	{//if this is a template
		
	return (
		el(
			wp.editPost.PluginPostStatusInfo,
			{},
<div>
<h3>RSVPMaker Template</h3>
{rsvpmaker_ajax.top_message}
																																	<p>{rsvpmaker_ajax.template_msg}</p>
<p>{__('To change the schedule, follow the link below.')}</p>
<div class="rsvpmaker_related">
{related_link()}
</div>
{rsvpmaker_ajax.bottom_message}
</div>
		)
	);

	}
	return (
		el(
			wp.editPost.PluginPostStatusInfo,
			{},
<div>
<h3>RSVPMaker Date</h3>
{rsvpmaker_ajax.top_message}
<RSVPMakerDateTimePicker />
<div class="rsvpmaker_related">
<p>{related_link()}</p>
</div>
{rsvpmaker_ajax.bottom_message}
</div>
		)
	);
}

const RSVPMakerDateTimePicker = withState( {
	date: new Date(wp.data.select('rsvpevent').getRSVPdate()),
} )( ( { date, setState } ) => {
	const settings = getSettings();
	const is12HourTime = /a(?!\\)/i.test(
		settings.formats.time
			.toLowerCase() // Test only the lower case a
			.replace( /\\\\/g, '' ) // Replace "//" with empty strings
			.split( '' ).reverse().join( '' ) // Reverse the string and test for "a" not followed by a slash
	);
	
	//console.log('datestr '+ datestr);
	console.log('date ' + date);
	
function recordDate(date) {
var xhr = new XMLHttpRequest();
xhr.open("POST", ajaxurl, true);

//Send the proper header information along with the request
xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

xhr.onreadystatechange = function() {//Call a function when the state changes.
    if(this.readyState == XMLHttpRequest.DONE && this.status == 200) {
        // Request finished. Do processing here.
    }
}
wp.data.dispatch('rsvpevent').setRSVPdate(date);
datestring = "&date="+date;
xhr.send(dateaction+datestring);
	}

	var currentdate = wp.data.select('rsvpevent').getRSVPdate();
	return (
		<DateTimePicker
		    currentDate={ currentdate }
		    locale={ settings.l10n.locale }
		    is12Hour={ is12HourTime }
			onChange={ ( date ) => {setState( {date} ),recordDate( date )} }
		    />
	);
} );

wp.plugins.registerPlugin( 'rsvpmaker-sidebar-plugin', {
	render: RSVPMakerSidebarPlugin,
} );	

}// end initial test that rsvpmaker is set