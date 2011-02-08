<?php
namespace Controllers;

class main {
	
	protected $fw;
	
	public function __construct($fw) {
		$this->fw = $fw;
	}
	
	public function __call($method, $args) {
		// this is like ColdFusion's onMissingMethod
		$rc = $args[0];
		$rc->{$method} = 'missing';
	}
	
	public function startReverse(&$rc) {
		$rc->param('name', 'no name given');
		$this->fw->service('reverse', 'reverse');
	}
	
	public function endTime(&$rc) {
		$rc->param('time', 'Something went wrong');
	}
	
}