<?php
/******************************************************************************
Component:		Korrio Custom Fields Component CLASSES
*******************************************************************************/

/**
 * Class Korrio_Custom_Field
 */
class Korrio_Custom_Field extends KorrioRepository
{
    /**
     * @const KORRIOCUSTOMFIELDS
     * @desc the table name
     */
    CONST KORRIOCUSTOMFIELDS = 'wp_korrio_custom_fields';

    /**
     * @const KORRIOCUSTOMFIELDSGROUPS
     * @desc the table name
     */
    CONST KORRIOCUSTOMFIELDSGROUPS = 'wp_korrio_group_custom_fields';

    /**
     * @var $DATAMODEL
     * @desc which datamodal to use
     */
    public $DATAMODEL = 'KCF_Data';

	/**
     * @const ASK_ABOUT_RETURNING_TEAM
     * @desc Magic custom field ID for the ask-about-returning-team question.
	 */
	CONST ASK_ABOUT_RETURNING_TEAM = 999999999;

	/**
	 * get cf data by cf id
	 *
	 * @method	findDataById
	 * @param	string $cartid
	 * @return	return new $this->DATAMODEL
	 */
	private function findDataById($id)
	{
		global $wpdb;
		$table = self::KORRIOCUSTOMFIELDS;

		$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id);
		$data = $wpdb->get_row( $sql, ARRAY_A );

		error_log(__METHOD__ . ' $data ' . print_r($data,1));

		$data = new $this->DATAMODEL( $data );

		return $data;
	}

	/**
	 * Bulk load an array of rows for the custom fields for the specified club(s).
	 *
	 * @method get_customfields_details
	 * @param $group_ids Array or comma-separated list of club IDs.
	 * @return Array of table rows, else an empty array if no custom fields found for the club(s).
	 */
	public function get_customfields_details( $group_ids )
	{
		global $wpdb;

		if (is_array($group_ids)) {
			$group_ids = implode(',', $group_ids);
		}

		$table = self::KORRIOCUSTOMFIELDS;

		$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE group_id IN (%s) AND state != 'deleted';", $group_ids );
		$customfields = $wpdb->get_results( $sql, ARRAY_A );

		return $customfields;
	}

	/**
	 * Get the rows for the custom fields that have the specified ID(s).
	 * 
	 * @method get_customfield_details_by_id
	 * @param  $cf_ids Array or comma-separated list of custom field IDs.
	 * @return Array of table rows, else an empty array if no records are found.
	 */
	public function get_customfield_details_by_id( $cf_ids )
	{
		global $wpdb;

		if ( is_array( $cf_ids ) ) {
			$cf_ids = implode( ',', $cf_ids );
		}

		$table = self::KORRIOCUSTOMFIELDS;

		$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN (%s) AND state != 'deleted';", $cf_ids );
		$customfields = $wpdb->get_results( $sql, ARRAY_A );

		return $customfields;
	}

	/**
	 * Get the IDs of the custom fields that are enabled for the specified program(s).
	 * Was korrio_customfields_get_group_customfields.
	 * 
	 * @method  get_group_customfields
	 * @param   integer|array  The program ID(s) whose enabled custom field IDs to get.
	 * @return  Array of table rows, else an empty array if no custom fields are found for this program, 
	 *			or false if no group ID was specified.
	 */
	public function get_group_customfields ( $group_ids ) {
		global $wpdb;

    	if ( !$group_ids ){
    		return false;
    	}

        if ( is_array( $group_ids ) ) {
            $group_ids = implode( ',', $group_ids );
        }

		$table = self::KORRIOCUSTOMFIELDSGROUPS;
    	$sql = $wpdb->prepare( "SELECT customfield_id FROM {$table} WHERE group_id IN (%s) ORDER BY display_order;", $group_ids);

        $data = $wpdb->get_results( $sql, ARRAY_A );
    	error_log("get_group_customfields " . print_r($data, 1) );

    	return $data;
	}

	/**
	 * Get the custom fields that are enabled for the specified program(s).
	 * 
	 * @method  get_group_customfield_details
	 * @param   integer|array  The program ID(s) whose enabled custom field IDs to get.
	 * @return  Array of table rows, else an empty array if no custom fields are found for this program, 
	 *			or false if no group ID was specified.
	 */
	public function get_group_customfield_details ( $group_ids ) {
		global $wpdb;

    	if ( !$group_ids ){
    		return false;
    	}

        if ( is_array( $group_ids ) ) {
            $group_ids = implode( ',', $group_ids );
        }

		$tablecf    = self::KORRIOCUSTOMFIELDS;
		$tablecfg   = self::KORRIOCUSTOMFIELDSGROUPS;
		$sql = $wpdb->prepare("
			SELECT distinct(kcf.id),
			       kcf.group_id,
			       kcf.title,
			       kcf.text,
			       kcf.description,
			       kcf.type,
			       kcf.is_visible,
			       kcf.is_required,
			       kcf.value,
			       kcf.state,
			       kcf.updated,
			       kcf.created
			FROM {$tablecf} AS kcf
			INNER JOIN {$tablecfg} AS kcfg ON kcf.id = kcfg.customfield_id
			WHERE kcfg.group_id in (%s)
				&& kcfg.deleted = 0
			ORDER BY display_order
		", $group_ids);

		$data = $wpdb->get_results( $sql, ARRAY_A );

		return $data;
	}

	/**
	 * update what cf are assigned to a group
	 * was korrio_customfields_update_group_customfields
	 *
	 * @method  update_group_customfields
	 * @param   $group_id
	 * @param   $customfields
	 *
	 * @return  bool
	 */
	public function update_group_customfields ( $group_id, $customfields ) {

    	if(!$group_id){
    		return false;
    	}

    	$group_id = mysql_real_escape_string($group_id);

    	//first clear all fields for given group
        $d_payload = $this->deleteRepository(self::KORRIOCUSTOMFIELDSGROUPS, $group_id, 'group_id');

    	//now add them back in ;-)
    	if ( !empty( $customfields ) ) {
			foreach ( $customfields as $display_order => $customfield_id ){
				$payload =
					$this->insertRepository( self::KORRIOCUSTOMFIELDSGROUPS, array(
						'group_id'        => $group_id,
						'customfield_id'  => $customfield_id,
						'display_order'   => $display_order
						)
					);
			}
    	}
    	return true;
	}


	/**
	 * @method	addData
	 * @param	object/array $args / new $this->DATAMODEL
	 * @return	return update id;
	 */
	public function addData( $args ) {

		$data = $args;
	    if( !is_a( $data, $this->DATAMODEL ) ) {
	    	$data = new $this->DATAMODEL( $args );
	    }

		$errors = array();
		if ( $data->group_id == '' ) {
			array_push($errors, 'group_id is required');
		}

		if ( $data->title == '' ) {
			array_push( $errors, 'title is required');
		}

        if ( $data->text == '' ) {
            array_push( $errors, 'text is required');
        }

        if ( $data->type == '' ) {
			array_push( $errors, 'type is required');
		}

        if( sizeof( $errors ) > 0 ) {
			return $errors;
		}

		$payload = $this->insertRepository( self::KORRIOCUSTOMFIELDS, $data->toInsertArray() );
		return $payload;
	}

	/**
	 * @method	updateData
	 * @param	object/array $args / new $this->DATAMODEL
	 * @return	return bool / rows updated
	 */
	public function updateData( $data )
	{

		// pass the data we have into the assets object
	    $newdata = $data;
	    if( !is_a( $newdata, $this->DATAMODEL ) ) {
	    	$newdata = new $this->DATAMODEL( $data );
	    }

		// Error checks.
		$errors = array();
		try {
			if ( $newdata->id == '' ) {
				throw new KCF_Exception( 'Id is required' );
			}
		} catch ( KCF_Exception $e ) {
			 array_push( $errors, $e->getMessage() );
		}

		if ( sizeof( $errors) > 0 ) {
			return $errors;
		}

		// get the original asset we need to compare against
		$compare = $this->findDataById( $newdata->id );

		// Only update properties that have changed since the object was created.
		$delta = array();

		if ( !empty( $newdata->args ) ) {
			foreach ( $newdata->args as $key => $value ) {
				// the properties for the value
				$prop = $compare->getProperty($key);

				/* we are doing a few checks.
				 1. make sure the keys in the update array exist in the row.
				 2. make sure the data has changed.
				 3. make sure the value can be updated.
				*/
				if ( array_key_exists( $key, $compare->map )
				&& $newdata->$key != $compare->$key
				&& $prop['update'] == true ) {
					$delta[$key] = $value;
				}
			}
		}

		error_log(__METHOD__ . ' $compare ' . print_r($compare,1));

		// there has been some changes
		if( sizeof( $delta) > 0 ) {
			$numRows = $this->updateRepository( self::KORRIOCUSTOMFIELDS, $delta, $compare->id );
			return $numRows;
		}

		return false;
	}

	/**
	 * @method	actuallyDeleteItem
	 * @param	string $cartid
	 * @return	return bool / rows updated
	 */
	public function actuallyDeleteItem ( $id )
	{
		try {
			$this->deleteRepository( self::KORRIOCUSTOMFIELDS, $id, 'id' );
		} catch ( KCF_Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * @method	deleteItem
	 * @param	string $id
	 * @return	return bool / rows updated
	 */
	public function deleteItem ( $id )
	{
		try {
			$this->updateRepository( self::KORRIOCUSTOMFIELDS, array('state'=>'deleted'), $id );
		} catch ( KCF_Exception $e ) {
			 return false;
		}

		return true;
	}

}

