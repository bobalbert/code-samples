<?php
class KCF_Data extends KorrioDataClass {

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
		$this->map['id']          = $this->args['id'];
		$this->map['group_id']    = $this->args['group_id'];
		$this->map['title']       = stripslashes(strip_tags($this->args['title']));
		$this->map['text']        = stripslashes(strip_tags($this->args['text']));
		$this->map['description'] = stripslashes(strip_tags($this->args['description'], '<b><strong><em>'));
		$this->map['type']        = $this->args['type'];
		$this->map['is_visible']  = $this->args['is_visible'];
		$this->map['is_required'] = $this->args['is_required'];
		$this->map['value']       = $this->args['value'];
		$this->map['state']       = $this->args['state'];
		$this->map['updated']     = $this->args['updated'];
		$this->map['created']	  = $this->args['created'];

	    // valueproperties
	    $this->prop['id']			= array('update' => false);
		$this->prop['group_id']		= array('update' => true);
		$this->prop['title']		= array('update' => true);
		$this->prop['text']	        = array('update' => true);
		$this->prop['description']  = array('update' => true);
		$this->prop['type']         = array('update' => true);
		$this->prop['is_visible']	= array('update' => true);
		$this->prop['is_required']	= array('update' => true);
		$this->prop['value']		= array('update' => true);
		$this->prop['state']	    = array('update' => true);
		$this->prop['updated']      = array('update' => false);
		$this->prop['created']		= array('update' => false);
	}

}