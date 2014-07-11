<?php

use Jenssegers\Mongodb\Model as Eloquent;

class Realfagsterm extends Subject {

	public function import($concept)
	{
		$this->indexTerm = $concept['prefLabels']['nb'];
		$this->prefLabels = $concept['prefLabels'];
		$this->altLabels = $concept['altLabels'];
		$this->vocabulary = 'noubomn';
	}

}