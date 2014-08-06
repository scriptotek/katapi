<?php

use Jenssegers\Mongodb\Model as Eloquent;

class Subject extends Eloquent {

	protected $collection = 'subjects';

	protected $fillable = array('indexTerm', 'vocabulary');	
	protected $visible = array('_id', 'identifier', 'created_at', 'updated_at', 
		'indexTerm', 'vocabulary', 'prefLabels', 'altLabels');

	/**
	 * Authoritative list of vocabulary names
	 * Ref: http://www.loc.gov/standards/sourcelist/subject.html
	 */
	public static $vocabularies = array(
		'noubomn' => 'Realfagstermer',
		'humord' => 'Humord',
		'tekord' => 'Tekord',
		'lcsh' => 'Library of Congress Subject Headings',
		'psychit' => 'APA Thesaurus of psychological index terms',
	);

	public function documents()
    {
        return $this->belongsToMany('Document');
    }

	/**
	 * Convert the model instance to an array.
	 * Override in order to present a *simplified* representation of the documents array
	 * instead of embedding the complete documents
	 *
	 * @return array
	 */
	public function toArray()
	{
		$attributes = $this->attributesToArray();

		$docs = array();
		foreach ($this->documents()->get() as $doc) {
			$docs[] = array(
				'id' => $doc->bibsys_id,
				'uri' =>  URL::action('DocumentsController@getShow', array('id' => $doc->bibsys_id)),
			); 
		}

		// Appends docs
		$res = array_merge($attributes, $this->relationsToArray());
		$res['documents'] = $docs;

		return $res;
	}
   
}