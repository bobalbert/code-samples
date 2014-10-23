<?php
class KCF_User_Repo extends KorrioRepository {

    /**
     * @const KORRIOUSERCUSTOMFIELDS
     * @desc the table name
     */
    CONST KORRIOUSERCUSTOMFIELDS = 'korrio_user_custom_fields';

    /**
     * @var $DATAMODEL
     * @desc which datamodal to use
     */
    public $DATAMODEL = 'KCF_UserData';

	/**
	 * Get user's custom fields by custom field id
	 *
	 * @method	findDataById
	 * @param	string $id
	 * @return	return new $this->DATAMODEL
	 */
	private function findDataById( $id )
	{
		global $wpdb;
		$table = self::KORRIOUSERCUSTOMFIELDS;

		$sql  = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id );
		$data = $wpdb->get_row( $sql, ARRAY_A );

		error_log(__METHOD__ . ' $data ' . print_r($data,1));

		$data = new $this->DATAMODEL($data);

		return $data;
	}

    /**
     * Get cf data for given field id, userid and programid
     *
     * @method	findDataByProgramFieldAndUser
     * @param	string $program_id
     * @param	string $cf_id
     * @param	string $user_id
     * @return	return new $this->DATAMODEL
     */
    private function findDataByProgramFieldAndUser( $program_id, $cf_id, $user_id )
    {
	    global $wpdb;

	    $table = self::KORRIOUSERCUSTOMFIELDS;

	    $sql = $wpdb->prepare("
    	   SELECT *
    	   FROM {$table}
    	   WHERE user_id = %d
    	   AND program_id = %d
    	   AND custom_field_id = %d
    	;", $user_id, $program_id, $cf_id );

	    $data = $wpdb->get_results( $sql, ARRAY_A );

	    if (count($data) > 1)
		    error_log(__METHOD__ . ' ERROR - more than one result returned for ' . $sql);

	    return new $this->DATAMODEL( $data );
    }

    /**
	 * get cf data for user and program
     *
     * @method	korrio_get_users_custom_fields
	 * @param	int or array $user_id
	 * @param	int $program_id
	 * @return	$data array of data
	 */
	public function korrio_get_users_custom_fields( $user_id, $program_id, $parent_group_id )
	{
		global $wpdb;
		if(is_array($user_id)) {
    	    // walk through the array to clean each value
	        array_walk($user_id, function($value, $key) {
    	        $value = mysql_real_escape_string($value);
	        });

    	    $user_id = implode(',',$user_id);

	    } else {
	        $user_id = mysql_real_escape_string($user_id);
	    }

	   $program_id = mysql_real_escape_string($program_id);

		$table = self::KORRIOUSERCUSTOMFIELDS;
    	$sql = $wpdb->prepare("
    	   SELECT *
    	   FROM {$table}
    	   WHERE user_id IN (%s)
    	   AND program_id = %d
    	;", $user_id, $program_id);

		$data = $wpdb->get_results( $sql, ARRAY_A );

    	$kcf = new Korrio_Custom_Field();
    	$cf = $kcf->get_customfields_details( $parent_group_id );

    	// build up an array of matched data
    	if ( !empty( $cf ) ) {
			foreach( $cf as $cf_data ) {
	        	$cfMatch[$cf_data['id']] = $cf_data['title'];
	    	}
		}
		
		if ( !empty( $data ) ) {
	    	foreach( $data as $k=> $cfd ) {
	        	$returned_data[$cfd['user_id']][$cfd['custom_field_id']] = $cfd;
	    	}
		}
		
    	return $returned_data;
	}

    /**
     * @method save
     * @param object/array $data / new $this->DATAMODEL
     * @return return update id
     */
    public function save( $data ) {
        // pass the data we have into the assets object
        $newdata = $data;
        if( !is_a( $newdata, $this->DATAMODEL ) ) {
            $newdata = new $this->DATAMODEL( $data) ;
        }

        $existing = $this->findDataByProgramFieldAndUser( $newdata->program_id, $newdata->custom_field_id, $newdata->user_id);

        if ( is_null( $existing->id ) ) {
            $result = $this->addData( $newdata );
        } else {
            $result = $this->updateData( $newdata );
        }

        return $result;
    }

    /**
	 * @method	addData
	 * @param	object/array $args / new $this->DATAMODEL
	 * @return	return new id;
	 */
	public function addData($args) {

		$data = $args;
	    if( !is_a( $data, $this->DATAMODEL ) ) {
	    	$data = new $this->DATAMODEL( $args );
	    }

		$errors = array();
		try {
			if ( $data->parent_group_id == '' ) {
				throw new KCF_Exception('parent_group_id is required');
			}
		} catch (KCF_Exception $e) {
			 array_push( $errors, $e->getMessage() );
		}

		try {
			if ( $data->program_id == '' ) {
				throw new KCF_Exception('program_id is required');
			}
		} catch (KCF_Exception $e) {
			 array_push( $errors, $e->getMessage() );
		}

		try {
			if ( $data->user_id == '' ) {
				throw new KCF_Exception('user_id is required');
			}
		} catch ( KCF_Exception $e ) {
			 array_push( $errors, $e->getMessage() );
		}

		try {
			if ( $data->user_id == '' ) {
				throw new KCF_Exception('custom_field_id is required');
			}
		} catch ( KCF_Exception $e) {
			 array_push( $errors, $e->getMessage() );
		}

		try {
			if ( $data->custom_field_answer == '' ) {
				throw new KCF_Exception( 'custom_field_answer is required' );
			}
		} catch ( KCF_Exception $e ) {
			 array_push( $errors, $e->getMessage() );
		}

		if( sizeof( $errors) > 0 ) {
			return $errors;
		}

		$payload = $this->insertRepository( self::KORRIOUSERCUSTOMFIELDS, $data->toInsertArray() );
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
			if ( $data->id == '' ) {
				throw new KCF_Exception('Id is required');
			}
		} catch ( KCF_Exception $e ) {
			 array_push( $errors, $e->getMessage() );
		}

		if ( sizeof( $errors) > 0 ) {
			return $errors;
		}

		// get the original asset we need to compare against
		$compare = $this->findDataById($newdata->id);

		// Only update properties that have changed since the object was created.
		$delta = array();

		if ( !empty( $newdata->args ) ) {
			foreach ( $newdata->args as $key => $value ) {
				// the properties for the value
				$prop = $compare->getProperty( $key );

				/* we are doing a few checks.
				 1. make sure the keys in the update array exist in the row.
				 2. make sure the data has changed.
				 3. make sure the value can be updated.
				*/
				if ( array_key_exists($key, $compare->map)
					&& $newdata->$key != $compare->$key
					&& $prop['update'] == true ) {

					$delta[$key] = $value;
				}
			}
		}

		error_log(__METHOD__ . ' $compare ' . print_r($compare,1));

		// there has been some changes
		if ( sizeof( $delta ) > 0 ) {
			$numRows = $this->updateRepository( self::KORRIOUSERCUSTOMFIELDS, $delta, $compare->id) ;
			return $numRows;
		}

		return false;
	}

	/**
	 * @method	actuallyDeleteItem
	 * @param	string $cartid
	 * @return	return bool / rows updated
	 */
	public function actuallyDeleteItem( $id )
	{
		try {
			$this->deleteRepository( self::KORRIOUSERCUSTOMFIELDS, $id, 'id' );
		} catch ( KCF_Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * @method	deleteItem
	 * @param	string $cartid
	 * @return	return bool / rows updated
	 */
	public function deleteItem( $id )
	{
		try {
			$this->updateRepository( self::KORRIOUSERCUSTOMFIELDS, array('state'=>'deleted'), $id );
		} catch ( KCF_Exception $e ) {
			 return false;
		}

		return true;
	}

}
