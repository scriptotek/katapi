<?php

use Jenssegers\Mongodb\Model as Eloquent;

class Subject extends Eloquent {

	protected $collection = 'subjects';

    /* Attributes to be added to JSON serialization through accessors */
    protected $appends = array('documents');

	protected $fillable = array('indexTerm', 'vocabulary');	
	protected $visible = array('_id', 'identifier', 'created_at', 'updated_at', 'indexTerm', 'vocabulary', 'prefLabels', 'altLabels');

	public function documents()
    {
        return $this->belongsToMany('Document');
    }

    /* Accessor for the documents attribute */
	public function getDocumentsAttribute()
	{
		$docs = array();
		foreach ($this->documents()->get() as $doc) {
			$docs[] = array(
				'id' => $doc->bibsys_id,
				'uri' =>  URL::action('DocumentsController@getShow', array('id' => $doc->bibsys_id)),
			); 
		}
		return $docs;
	}

}