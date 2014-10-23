<?php
/**
 * Generates HTML for the custom fields list for a club
 */
function korrio_customfields_render_list_html()
{
	global $bp;
	$group_id = $bp->groups->current_group->id;
	
	$cf = new Korrio_Custom_Field();
	$customfields = $cf->get_customfields_details( array( $group_id ) );

	/* debug
	echo '<pre>';
	print_r($customfields);
	echo '</pre>';*/
		
	$html_form = new KorrioTemplate('korrio_customfields_render_list_html','korrio-custom-fields',array(
					'customfields' => $customfields,
				 ));
				 
	echo $html_form->render();

}

/**
 * build the edit custom field form
 */
function korrio_customfields_render_edit_form()
{
	global $bp;

	$group_id		= $bp->groups->current_group->id;
	$user_id		= $bp->loggedin_user->id;
	
	$html_form = new KorrioTemplate( 'korrio_customfields_render_edit_form','korrio-custom-fields', array(
					'group_id'		=> $group_id,
					'user_id'		=> $user_id,
				 ));
				 
	echo $html_form->render();
}

