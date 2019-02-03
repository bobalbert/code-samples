<?php

/**
 * Handles server side AJAX for customfields view.
 */
if ( isset( $_REQUEST['korrio_customfield_details_ajax'] ) ) add_action( 'init', 'korrio_customfield_details_ajax' );
function korrio_customfield_details_ajax()
{
	$customfield_id = $_REQUEST['id'];
	$cf = new Korrio_Custom_Field();
    $customfield = $cf->get_customfield_details_by_id( $customfield_id );

	echo json_encode($customfield[0]);
	exit();
}

/**
 * Handles returning JSON customfield names for a given program_id ($_REQUEST['program_id'])
 */
if( isset($_REQUEST['korrio_customfields_get_program_customfield_names_ajax'] ) ) add_action( 'init', 'korrio_customfields_get_program_customfield_names_ajax' );
function korrio_customfields_get_program_customfield_names_ajax() {
	$program_id = $_REQUEST['program_id'];
	$cf = new Korrio_Custom_Field();

	// Get the field names of all the other fields (but not this one), so we can validate that the field 
	// being edited still has a unique name.
	$fieldnames = array();
	$fields = $cf->get_customfields_details( $program_id );
	if ( $fields ) {
		foreach ( $fields as $field ) {
			$fieldnames[] = array(
				'id'   => $field['id'],
				'name' => $field['title'],
			);
		}
	}

	echo json_encode( $fieldnames );
	exit();
}

/**
 * Handles server side AJAX for customfields view.
 */
if ( isset( $_REQUEST['korrio_customfield_reorder_list_ajax'] ) ) add_action( 'init', 'korrio_customfield_reorder_list' );
function korrio_customfield_reorder_list() {
	$list = explode( ',', $_REQUEST['list'] );
	$neworder=1;
	foreach ($list as $customfield_id)
	{
		$customfield = new Korrio_customfield($customfield_id);
		$customfield->display_order = $neworder;
		$neworder++;
		$customfield->save();
	}
	echo json_encode('ok');
	exit();
}

/**
 * Handles server side AJAX for customfields view.
 */
if ( isset( $_REQUEST['korrio_customfields_edit_customfields_ajax'] ) ) add_action( 'init', 'korrio_customfields_edit_customfields' );
function korrio_customfields_edit_customfields() {
	$action = $_REQUEST['action'];
    $custom_field_id = (int) $_REQUEST['customfield_id'];

    if ( $action == 'update' || $action == 'new' ) {
        $radio_options = null;
        if ( isset($_REQUEST['radio_option'] ) ) {
            $radio_options = json_encode( array_values( $_REQUEST['radio_option'] ) );
        }
        $customfield['id']          = $custom_field_id;
        $customfield['title']	    = substr(trim($_REQUEST['name']), 0, 100); // trimmed & limited to first 100 chars.
        $customfield['text']		= trim($_REQUEST['text']);
        $customfield['description']	= trim($_REQUEST['description']);
        $customfield['type']		= $_REQUEST['type'];
        $customfield['is_visible']	= $_REQUEST['is_visible'];
        $customfield['is_required']	= $_REQUEST['is_required'];
        $customfield['value']		= $radio_options;
        $customfield['group_id']    = $_REQUEST['group_id'];
        $customfield['state']       = 'active';
    }

	$cf_repo = new Korrio_Custom_Field();

    switch ( $action )
    {
        case 'new':
            $cf_data = new KCF_Data( $customfield );
            $result = $cf_repo->addData( $cf_data );

            if ( $result ){
                $new_customfield = $cf_repo->get_customfield_details_by_id( $result );
                $result = $new_customfield[0];
            }
        break;
        case 'update':
            $cf_data = new KCF_Data($customfield);
            $update_result = $cf_repo->updateData( $cf_data );
            $result = $cf_data->map;
            break;
        case 'inactive':
        case 'deleted':
        case 'active':
            $cf_current_data = $cf_repo->get_customfield_details_by_id( $custom_field_id );
            $cf_current_data[0]['state'] = $action;

            $cf_data = new KCF_Data( $cf_current_data[0] );
            $update_result = $cf_repo->updateData( $cf_data );

            $result = $cf_data->map;
            break;
    }
	
	exit ( json_encode( $result ) );
}

