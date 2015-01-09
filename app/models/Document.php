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
            Log::info('CREATE document "' . $biblio->id . '"');
            $doc = new Document;
        } else {
            Log::info('UPDATE document "' . $biblio->id . '"');
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
            throw new Exception('Document.bibliographic was given an unknown datatype.');
        }

        // We maintain subjects and classifications in separate MongoDB collections
        if (isset($value['subjects'])) {
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
                throw new Exception('Document.holdings was given an unknown datatype.');
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
     * Accessor for the 'classifications' attribute
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
     * @param $value
     * @throws Exception
     */
    public function setClassificationsAttribute($value)
    {
        $result = array();
        foreach ($value as $key => $value) {

            if (!is_array($value)) {
                throw new Exception('Document.classifications was given an unknown datatype.');
            }

            if (isset($value['internal_id'])) {

                // Undo the effect of getClassificationsAttribute
                $value['assigned'] = $this->fromDateTime($value['assigned']);
                $value['internal_id'] = new MongoId($value['internal_id']);
                $result[] = $value;

            } else {

                $instance = Classification::where('system', '=', $value['system'])
                    ->where('number', '=', $value['number'])
                    ->where('edition', '=', $value['edition'])
                    ->first();

                if (!$instance) {
                    Log::info(sprintf('CREATE classification {system: "%s", number: "%s"}', $value['system'], $value['number']));
                    $instance = new Classification(array(
                        'system' => $value['system'],
                        'number' => $value['number'],
                        'edition' => $value['edition'],
                    ));
                    $instance->save();
                }

                $r = $this->getSubdocumentById('classifications', $instance->id);

                if (is_null($r)) {
                    $r = array(
                        'internal_id' => new MongoId($instance->id),
                        'assigner' => $value['assigner'],
                        'assigned' => new MongoDate(),
                    );
                }

                $result[] = $r;
            }
        }
        $this->attributes['classifications'] = $result;
    }

    /**
     * Accessor for the 'subjects' attribute
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
     * Mutator for the 'subjects' attribute
     *
     * @param $value
     * @throws Exception
     */
    public function setSubjectsAttribute($value)
    {
        $result = array();
        foreach ($value as $key => $value) {

            if (isset($value['internal_id'])) {

                // Undo the effect of getSubjectsAttribute
                $value['assigned'] = $this->fromDateTime($value['assigned']);
                $value['internal_id'] = new MongoId($value['internal_id']);
                $result[] = $value;

            } else {

                if (!is_array($value)) {
                    throw new Exception('Document.subjects was given an unknown datatype.');
                }

                if (!isset($value['vocabulary'])) {
                    Log::info('Ignore term without vocabulary: ' . $value['term']);
                    continue;
                }

                $instance = Subject::where('vocabulary', '=', $value['vocabulary'])
                    ->where('indexTerm', '=', $value['term'])
                    ->first();

                if (!$instance) {
                    Log::info(sprintf('CREATE subject heading {vocabulary: "%s", term: "%s"}', $value['vocabulary'], $value['term']));
                    $value['indexTerm'] = $value['term'];
                    $instance = new Subject($value);
                    $instance->save();
                } else {
                    // TODO: Update if changed
                }

                $r = $this->getSubdocumentById('subjects', $instance->id);

                if (is_null($r)) {
                    $r = array(
                        'internal_id' => new MongoId($instance->id),
                        'assigned' => new MongoDate(),
                    );
                }

                $result[] = $r;
            }
        }
        $this->attributes['subjects'] = $result;
    }

//    public function subjects()
//    {
//        // return $this->belongsToMany('Subject');
//        return $this->embedsMany('SubjectInstance');
//    }

    /**
     * Mutator for the 'subjects' attribute
     *
     * @param $value
     */
//    function setSubjectsAttribute($value)
//    {
//        print_r($value);
//    }

    /**
     * Accessor for the 'subjects' attribute
     *
     * @return array
     */
//	public function getSubjectsAttribute()
//	{
//		$subjects = array();
//		foreach ($this->subjects()->get() as $i) {
//			if (isset($i->identifier)) {
//				$uri = URL::action('SubjectsController@getId', array('vocabulary' => $i->vocabulary, 'term' => $i->identifier));
//			} else {
//				$uri = URL::action('SubjectsController@getId', array('vocabulary' => $i->vocabulary, 'term' => $i->indexTerm));
//			}
//			$s = array(
//				'vocabulary' => $i->vocabulary,
//				'indexTerm' => $i->indexTerm,
//				'type' => $i->type,
//				'uri' => $uri,
//			);
//			if (isset($i->id)) $s['local_id'] = $i->id;
//			if (isset($i->identifier)) $s['id'] = $i->identifier;
//			$subjects[] = $s;
//		}
//
//		// Sort subject headings by vocabulary
//		usort($subjects, function($a, $b) {
//		    return strcmp(array_get($a, 'vocabulary', ''), array_get($b, 'vocabulary', ''));
//		});
//
//		return $subjects;
//	}

	/* Accessor for the classifications attribute */
//	public function getClassesAttribute()
//	{
//		$classes = array();
//		foreach ($this->bibliographic['classifications'] as $i) {
//			$s = $i;
//			$s['uri'] = URL::action('ClassesController@getId', array('system' => $i['system'], 'number' => $i['number']));
//			$classes[] = $s;
//		}
//
//		// Sort classes by system
//		usort($classes, function($a, $b) {
//		    return strcmp(array_get($a, 'system', ''), array_get($b, 'system', ''));
//		});
//
//		return $classes;
//	}

    /**
     * Accessor for the virtual 'link' attribute
     *
     * @return array
     */
    public function getLinkAttribute() {
        return URL::action('DocumentsController@getShow', array($this->id));
    }

    /**
     * Helper method for attributesToArray()
     *
     * @param $refs
     * @param $model
     * @return mixed
     */
    protected function extendAttributes($refs, $model)
    {
        $toCopy = array('internal_id', 'assigned', 'assigner');
        $toRemove = array('documents', 'created_at', 'updated_at', '_id');

        $ids = array_map(function ($x) {
            return strval($x['internal_id']);
        }, $refs);
        $items = $model::whereIn('_id', $ids)->get();

        foreach ($refs as $i => $ref) {
            $out = array();
            foreach ($items as $item) {
                if ($item->id == $ref['internal_id']) {
                    $out = $item->toArray();
                    break;
                }
            }
            array_forget($out, $toRemove);
            foreach ($toCopy as $x) {
                if (isset($ref[$x])) $out[$x] = $ref[$x];
            }
            $refs[$i] = $out;
        }
        return $refs;
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

        // Extend classifications
        $attributes['classifications'] = $this->extendAttributes($attributes['classifications'], 'Classification');

        // Extend subjects
        $attributes['subjects'] = $this->extendAttributes($attributes['subjects'], 'Subject');

        return $attributes;
    }

}
