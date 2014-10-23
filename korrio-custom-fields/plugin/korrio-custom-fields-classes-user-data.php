<?php
class KCF_UserData extends KorrioDataClass {

	/**
	 * constructor
	 *
	 * @param array $args
	 */
	public function __construct($args = array()) {
		$this->args = $args;
		$this->map();
	}

	/**
	 * maps the data to an internal
	 */
	protected function map() {
		$this->map['id']                  = $this->args['id'];
		$this->map['parent_group_id']     = $this->args['parent_group_id'];
		$this->map['program_id']          = $this->args['program_id'];
		$this->map['user_id']             = $this->args['user_id'];
		$this->map['custom_field_id']     = $this->args['custom_field_id'];
		$this->map['custom_field_answer'] = $this->args['custom_field_answer'];
		$this->map['updated']			  = $this->args['updated'];
		$this->map['created']			  = $this->args['created'];

	    // valueproperties
	    $this->prop['id']                  = array('update' => false);
		$this->prop['parent_group_id']     = array('update' => true);
		$this->prop['program_id']          = array('update' => true);
		$this->prop['user_id']             = array('update' => true);
		$this->prop['custom_field_id']     = array('update' => true);
		$this->prop['custom_field_answer'] = array('update' => true);
		$this->prop['updated']             = array('update' => false);
		$this->prop['created']             = array('update' => false);
	}

}