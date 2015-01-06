<?php

/**
 * A single class in a classification scheme edition.
 *
 * @property string id  Internal MongoId
 * @property string system  Classification scheme code
 *     (compatible with http://www.loc.gov/standards/sourcelist/classification.html)
 * @property string edition  Edition of the classification scheme. Can be null
 * @property string number  Notation used to represent the class
 */
class Classification extends BaseModel {

    /**
     * The MongoDB collection associated with the model.
     *
     * @var string
     */
	protected $collection = 'classifications';

    /**
     * Authoritative list of vocabulary names
     * Ref: http://www.loc.gov/standards/sourcelist/classification.html
     *
     * @var array
     */
    public static $systems = array(
        'acmccs' => 'CCS',
        'ddc' => 'DDC',
        'no-ureal-ca' => 'Astrofysisk hylleoppstilling',
        'no-ureal-cb' => 'Biologisk hylleoppstilling',
        'no-ureal-cg' => 'Geofysisk hylleoppstilling',
        'inspec' => 'INSPEC',
        'loovs' => 'Løøvs klassifikationssystem',
        'msc' => 'MSC',
        'nlm' => 'NLM-klassifikasjon',
        'oosk' => 'UBB-klassifikasjon',
        'udc' => 'UDC',
        'utk' => 'UBO-klassifikasjon',
    );

    /**
     * Appended, calculated attributes to this model that are not really in the
     * attributes array, but are run when we need to array or JSON the model.
     *
     * @var array
     */
    protected $appends = array('documents', 'link');

    protected $fillable = array(
        'system',
        'edition',
        'number',
    );

    /**
     * Accessor for the virtual 'documents' attribute
     *
     * @return array
     */
    function getDocumentsAttribute()
    {
        return $this->getRelatedDocuments('classifications');
    }

    /**
     * Accessor for the virtual 'link' attribute
     *
     * @return array
     */
    public function getLinkAttribute() {
        return URL::action('ClassesController@getShow', array($this->id));
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        // Convert DateTime objects to strings
        // Eloquent can handle objects in the document root, but not in subdocuments
        // (such as dates in the 'holdings' subdocuments)
        $attributes = $this->flattenDates($attributes);

        return $attributes;
    }

}
