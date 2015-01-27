<?php

use Scriptotek\SimpleMarcParser\BibliographicRecord;
use Scriptotek\SimpleMarcParser\HoldingsRecord;
use Scriptotek\SimpleMarcParser\Parser;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;

/**
 * A single document
 *
 * @property mixed bibliographic  Basic bibliographic description
 * @property array holdings  Array of holdings
 * @property array subjects  Subject headings
 * @property array classifications  Classification numbers
 * @property array links
 */
class Document extends BaseModel {

    /**
     * The MongoDB collection associated with the model.
     *
     * @var string
     */
	protected $collection = 'documents';

    /**
     * Appended, calculated attributes to this model that are not really in the
     * attributes array, but are run when we need to array or JSON the model.
     *
     * @var array
     */
	protected $appends = array('link');

    /**
     * Validation rules.
     *
     * @var array
     */
    public static $rules = array(
        'bibliographic' => 'array',
        'classifications' => 'array',
        'subjects' => 'array',
    );

    /**
     * Validation errors.
     *
     * @var Illuminate\Support\MessageBag
     */
    public $errors;

    /**
     * Parse using SimpleMarcParser and separate bibliographic and holdings.
     *
     * @param QuiteSimpleXMLElement $data
     * @return array
     */
    protected static function parseRecord(QuiteSimpleXMLElement $data)
    {
        $parser = new Parser;
        $biblio = null;
        $holdings = array();
        foreach ($data->xpath('.//marc:record') as $rec) {
            $parsed = $parser->parse($rec);
            if ($parsed instanceof BibliographicRecord) {
                $biblio = $parsed;
            } elseif ($parsed instanceof HoldingsRecord) {
                $holdings[] = $parsed;
            }
        }
        return array($biblio, $holdings);
    }

    /**
     * Find an existing document (and update it) or create a new one
     * from a marc:collection dataset
     *
     * @param QuiteSimpleXMLElement $data
     * @return Document
     */
    public static function fromRecord(QuiteSimpleXMLElement $data)
    {
        // Get BibliographicRecord and array of HoldingsRecord
        list($biblio, $holdings) = self::parseRecord($data);

        // Find existing Document or create a new one
        $doc = Document::where('bibliographic.id', '=', $biblio->id)->first();
        if (is_null($doc)) {
            Log::info(sprintf('[%s] CREATE document', $biblio->id));
            $doc = new Document;
        } else {
            // Log::info('UPDATE document "' . $biblio->id . '"');
        }

        // Update document
        $doc->bibliographic = $biblio;
        $doc->holdings = $holdings;

        return $doc;
    }

    /**
     * Accessor for the 'bibliographic' attribute
     *
     * @param $value
     * @return array
     */
    public function getBibliographicAttribute($value)
    {
        if (isset($value['created'])) {
            $value['created'] = $this->asDateTime($value['created']);
        }
        if (isset($value['modified'])) {
            $value['modified'] = $this->asDateTime($value['modified']);
        }
        return $value;
    }

    /**
     * Mutator for the 'bibliographic' attribute
     *
     * @param $value
     * @throws Exception
     */
    public function setBibliographicAttribute($value)
    {
        if ($value instanceof BibliographicRecord) {
            $value = $value->toArray();
        } elseif (!is_array($value)) {
            throw new \Exception('Document.bibliographic was given an unknown datatype.');
        }

        // We maintain subjects and classifications in separate MongoDB collections
        if (isset($value['subjects'])) {
            $value['subjects'] = array_map(function($x) {
                $x['indexTerm'] = $x['term'];
                unset($x['term']);
                if (array_key_exists('id', $x)) unset($x['id']); // We could rename it if we need it in the future
                return $x;
            }, $value['subjects']);
            $this->subjects = $value['subjects'];
            unset($value['subjects']);
        }
        if (isset($value['classifications'])) {
            $this->classifications = $value['classifications'];
            unset($value['classifications']);
        }

        // Store native DateTime
        if (isset($value['created'])) {
            $value['created'] = $this->fromDateTime($value['created']);
        }
        if (isset($value['modified'])) {
            $value['modified'] = $this->fromDateTime($value['modified']);
        }

        $this->attributes['bibliographic'] = $value;
    }

    /**
     * Accessor for the 'holdings' attribute
     *
     * @param $value
     * @return array
     */
    public function getHoldingsAttribute($value)
	{
		if (is_null($value)) {
			return array();
		}
		foreach ($value as $key => $val) {
			if (isset($val['created'])) {
				$value[$key]['created'] = $this->asDateTime($val['created']);
			}
			if (isset($val['acquired'])) {
				$value[$key]['acquired'] = $this->asDateTime($val['acquired']);
			}
		}
		return $value;
	}

    /**
     * Mutator for the 'holdings' attribute
     *
     * @param $value
     * @throws Exception
     */
    public function setHoldingsAttribute($value)
	{
        $out = array();
        $ids = array();
		foreach ($value as $key => $holding) {

            if ($holding instanceof HoldingsRecord) {
                $holding = $holding->toArray();
            } elseif (!is_array($holding)) {
                throw new \Exception('Document.holdings was given an unknown datatype.');
            }

            // Ignore creation date of record, since Bibsys just set it to the current date
            if (isset($holding['created'])) {
                unset($holding['created']);
			}

            if ($holding['bibliographic_record'] != $this->bibliographic['id']) {
                // Ignore (holdings for i-analytter er duplikater av holdings for overordnet post)
                continue;
            }

            if (in_array($holding['id'], $ids)) {
                Log::warning('Found duplicate holdings.id=' . $holding['id'] . ' for bibliographic.id=' . $this->bibliographic['id']);
                // Filter out duplicates from Bibsys...
                continue;
            }

			if (isset($holding['acquired'])) {
				$holding['acquired'] = $this->fromDateTime($holding['acquired']);
			} else {
                // Get year from DOKID
                $yr = 1900 + intval(substr($holding['id'], 0, 2));
                if ($yr < 1920) {
                    $yr += 100;
                }
                $holding['acquired'] = $this->fromDateTime(strval($yr) . '-01-01 00:00:00');
            }

            $out[] = $holding;
            $ids[] = $holding['id'];
		}
		$this->attributes['holdings'] = $out;
	}

    /**
     * Accessor for the 'classifications' attribute. Note that this method does 
     * not fetch data from the 'classifications' collection. The reason is that
     * the method is called from toArray(), and when serializing many 
     * documents, the number of queries needed would slow things down. 
     *
     * @param $value
     * @return array
     */
    public function getClassificationsAttribute($value)
    {
        if (is_null($value)) return null;

        $result = array();
        foreach ($value as $reference) {
            if (isset($reference['assigned'])) {
                $reference['assigned'] = $this->asDateTime($reference['assigned']);
            }
            $reference['internal_id'] = (string) $reference['internal_id'];
            $result[] = $reference;
        }
        return $result;
    }

    /**
     * Mutator for the 'classifications' attribute
     *
     * @param $classifications
     * @throws Exception
     */
    public function setClassificationsAttribute($classifications)
    {
        $this->attributes['classifications'] = $this->updateSubdocuments('classifications', $classifications);
    }

    /**
     * Method to get subjects, including data from the 'subjects' collection. 
     *
     * @return array
     */
    public function getSubjects()
    {
        $ids = array_unique(array_map(function($x) {
            return strval($x['internal_id']);
        }, $this->subjects));

        $rel = $this->getExpandedRelations(array(
            'subjects' => Subject::whereIn('_id', $ids)->get(),
        ));

        return $rel['subjects'];
    }

    /**
     * Method to get classificatinos, including data from the 'classifications' collection. 
     *
     * @return array
     */
    public function getClassifications()
    {
        $ids = array_unique(array_map(function($x) {
            return strval($x['internal_id']);
        }, $this->classifications));

        $rel = $this->getExpandedRelations(array(
            'classifications' => Classification::whereIn('_id', $ids)->get(),
        ));

        return $rel['classifications'];
    }

    /**
     * Accessor for the 'subjects' attribute. Note that this method does 
     * not fetch data from the 'subjects' collection. The reason is that
     * the method is called from toArray(), and when serializing many 
     * documents, the number of queries needed would slow things down. 
     *
     * @param $value
     * @return array
     */
    public function getSubjectsAttribute($value)
    {
        if (is_null($value)) return null;

        $result = array();
        foreach ($value as $reference) {
            if (isset($reference['assigned'])) {
                $reference['assigned'] = $this->asDateTime($reference['assigned']);
            }
            $reference['internal_id'] = (string) $reference['internal_id'];
            $result[] = $reference;
        }
        return $result;
    }

    /**
     * Currently just a wrapper
     */
    public function setSubjects($subjects)
    {
        $this->subjects = $subjects;
    }

    /**
     * Mutator for the 'subjects' attribute
     *
     * @param $value
     * @throws Exception
     */
    public function setSubjectsAttribute($subjects)
    {
        $this->attributes['subjects'] = $this->updateSubdocuments('subjects', $subjects);
    }

    public function updateSubdocuments($attr, $items)
    {

        if (count($items) == 0) {
            return [];
        }

        // Fields we keep in the 'documents' collection
        $localFields = ['internal_id', 'assigned', 'assigner', 'link', 'id'];

        $identifiers = [
            'subjects' => ['vocabulary', 'indexTerm'],
            'classifications' => ['system', 'edition', 'number'],
        ];

        $query = array('$or' => array_map(function($x) use ($identifiers, $attr) {
            $y = array();
            foreach ($identifiers[$attr] as $id) {
                if (!array_key_exists($id, $x)) {
                    throw new \Exception($id . ' is a required field for items in the ' . $attr . ' collection');
                }
                $y[$id] = $x[$id];
            }
            return $y;
        }, $items));

        $toCreate = [];
        $toUpdate = [];

        $currentItems = DB::collection($attr)->whereRaw($query)->get();

        // Loop over the new items
        foreach ($items as $item) {

            // Loop over the current items
            $foundItem = false;
            foreach ($currentItems as $current) {
                if (array_only($item, $identifiers[$attr]) == array_only($current, $identifiers[$attr])) {
                    $foundItem = true;
                    $instance = $current;
                    break;
                }
            }

            if ($foundItem) {
                $toUpdate[] = array('current' => $instance, 'new' => $item);
            } else {
                $item['_id'] = new MongoId;   // Pre-allocate ID
                $toCreate[] = $item;
                $currentItems[] = $item;
                $instance = $item;
            }

            $r = $this->getSubdocumentById($attr, $instance['_id']);

            if (is_null($r)) {
                $r = array(
                    'internal_id' => new MongoId($instance['_id']),
                    'assigned' => new MongoDate(),
                );
                if (isset($item['assigner'])) $r['assigner'] = $item['assigner'];
            }

            $out[] = $r;
        }

        // Insert data into the collection
        if (count($toCreate) != 0) {
            $new = array();
            foreach ($toCreate as $item) {
                $loggable = array_map(function($x) {
                    if (is_string($x)) return $x;
                }, $item);
                Log::info(sprintf('INSERT into %s (%s)',
                    $attr,
                    implode(',', $loggable)
                ));
                $new[] = $item;
            }
            DB::collection($attr)->insert($new);
        }

        // Update data in the 'subjects' collection
        foreach ($toUpdate as $x) {
            $fields = array_except($x['new'], $localFields);
            $dirty = false;
            foreach ($fields as $key => $val)
            {
                if (array_get($x['current'], $key) != $val)
                {
                    // $x['current'][$key] = $val;
                    $dirty = true;
                    // TEST: fwrite(STDERR, ' ['.$key. ': ' . array_get($x['current'], $key) . ' != ' . $val . ' ] ');
                }
            }
            if ($dirty)
            {
                $loggable = array_map(function($x) {
                    if (is_string($x)) return $x;
                }, $fields);
                Log::info(sprintf('UPDATE %s (%s)',
                    $attr,
                    implode(',', array_values($loggable))
                ));
                $q = DB::collection($attr);
                foreach ($identifiers[$attr] as $id) {
                    $q->where($id, '=', $x['new'][$id]);
                }
                $q->update($fields);
            }
        }

        return $out;
    }

    /**
     * Accessor for the virtual 'link' attribute
     *
     * @return string
     */
    public function getLinkAttribute() {
        if (is_null($this->id))
        {
            throw new \Exception('Document does not have an id assigned yet');
        }
        return URL::action('DocumentsController@getShow', array($this->id));
    }

    /**
     * Return a read-only array representation of the model, extended with
     * data from connected entitites, such as classifications and subject headings.
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

        // Add links to guide the API user
        $of = array_get($attributes, 'bibliographic.other_form.id');
        if (!is_null($of)) {
            $attributes['bibliographic']['other_form']['link'] = URL::action('DocumentsController@getShow', array($of));
        }

        return $attributes;
    }

    /**
     * Helper method for relationsToArray() that merges in data
     * from the related items (subjects/classifications)
     *
     * @return array
     */
    public function getExpandedRelations($expansionData)
    {
        // Fields in the Document model to include:
        $pivotFields = array('internal_id', 'assigned', 'assigner');

        // Fields on the Subject/Classification model to remove:
        $hiddenFields = array('documents', 'created_at', 'updated_at', '_id');

        $relations = array();
        // ('subjects', 'classifications')
        foreach (array_keys($expansionData) as $property)
        {
            $relations[$property] = array();

            foreach ($this->$property as $key => $val) {
                $relation = array();
                foreach ($expansionData[$property] as $item) {
                    if ($item->id == $val['internal_id']) {
                        $relation = $item->toArray();
                        break;
                    }
                }
                if (empty($relation))
                {
                    Log::warning(sprintf('[%s] Could not find classification with id:%s', 
                        $this->bibliographic['id'], $val['internal_id'])
                    );
                }
                array_forget($relation, $hiddenFields);
                foreach ($pivotFields as $x) {
                    if (isset($val[$x])) {
                        $relation[$x] = $val[$x];
                    }
                }
                $relations[$property][] = $relation;
            }

        }

        $relations = $this->flattenDates($relations);

        return $relations;
    }

    public function relationsToArray()
    {
        $s_ids = array_values(array_unique(array_map(function($x) {
            return strval($x['internal_id']);
        }, $this->subjects)));

        $c_ids = array_values(array_unique(array_map(function($x) {
            return strval($x['internal_id']);
        }, $this->classifications)));

        $relations = array(
            'subjects' => count($s_ids) ? Subject::whereIn('_id', $s_ids)->get() : [],
            'classifications' => count($c_ids) ? Classification::whereIn('_id', $c_ids)->get() : [],
        );

        return $this->getExpandedRelations($relations);
    }

    /**
     * Validate the model's attributes.
     *
     * @param  array  $rules
     * @param  array  $messages
     * @return bool
     */
    public function validate(array $rules = array(), array $messages = array())
    {
        $v = Validator::make($this->attributes, Document::$rules);

        if ($v->fails()) {
            $this->errors = $v->messages();
            return false;
        }

        $this->errors = null;
        return true;
    }

    /**
     * Save the model to the database.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = array())
    {
        if (!$this->validate()) {
            return false;
        }

        parent::save($options);
        return true;
    }

}
