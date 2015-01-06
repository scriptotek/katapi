<?php

/**
 * A single subject heading in a controlled subject heading vocabulary.
 *
 * @property string id
 * @property string vocabulary
 * @property string indexTerm
 * @property string|null identifier
 * @property string|null type
 */
class Subject extends BaseModel {

    /**
     * The MongoDB collection associated with the model.
     *
     * @var string
     */
	protected $collection = 'subjects';

	protected $fillable = array(
        'vocabulary',
        'indexTerm',
        'identifier',
        'type',
    );

//	protected $visible = array('_id', 'identifier', 'created_at', 'updated_at',
//		'indexTerm', 'vocabulary', 'prefLabels', 'altLabels');

	/**
	 * Authoritative list of vocabulary names
	 * Ref: http://www.loc.gov/standards/sourcelist/subject.html
	 */
	public static $vocabularies = array(
		'noubomn' => 'Realfagstermer',
		'humord' => 'Humord',
		'tekord' => 'Tekord',
		'lcsh' => 'Library of Congress Subject Headings',
		'mesh' => 'MeSH',
		'psychit' => 'APA Thesaurus of psychological index terms',
	);

    /**
     * Appended, calculated attributes to this model that are not really in the
     * attributes array, but are run when we need to array or JSON the model.
     *
     * @var array
     */
    protected $appends = array('documents', 'link');

    /**
     * Accessor for the virtual 'documents' attribute
     *
     * @return array
     */
    function getDocumentsAttribute()
    {
        return $this->getRelatedDocuments('subjects');
    }

    /**
     * Accessor for the virtual 'link' attribute
     *
     * @return array
     */
    public function getLinkAttribute() {
        return URL::action('SubjectsController@getShow', array($this->id));
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
        $attributes = parent::attributesToArray();

        // Convert DateTime objects to strings
        // Eloquent can handle objects in the document root, but not in subdocuments
        // (such as dates in the 'holdings' subdocuments)
        $attributes = $this->flattenDates($attributes);

        return $attributes;
	}
   
}
