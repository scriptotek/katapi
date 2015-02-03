<?php

/**
* Read-only class for efficient retrieval of multiple Documents
*/
class Documents
{

    protected $docs = [];

    /**
     * @param Document[] $docs
     */
    function __construct($docs)
	{
        $this->docs = $docs;
	}

    /**
     * Helper method used by Document::relationsToArray() and Documents::relationsToArray() 
     * to fetch info for the related
     * subjects/classifications using just one query.
     *
     * @return array
     */
    static public function fetchRelatedItems($model, $ids)
    {
        if (!in_array($model, array('Subject', 'Classification')))
        {
            throw new Exception('model must be Subject or Classification.');
        }
        $ids = array_unique($ids);
        return $model::whereIn('_id', array_keys($ids))->get();
    }

    public function toArray()
    {
        $relations = $this->relationsToArray();

        $out = [];
        foreach ($this->docs as $doc)
        {
            $o = $doc->attributesToArray();
            $o['subjects'] = $relations[$doc->_id]['subjects'];
            $o['classifications'] = $relations[$doc->_id]['classifications'];
            $out[] = $o;
        }

        return $out;
    }

    /**
     * Fetches related subjects and classifications for all documents
     * using only two queries.
     */
    protected function relationsToArray()
    {
        $s_ids = array();
        $c_ids = array();
        foreach ($this->docs as $doc) {
            foreach ($doc->subjects as $x) {
                $s_ids[] = strval($x['internal_id']);
            }
            foreach ($doc->classifications as $x) {
                $c_ids[] = strval($x['internal_id']);
            }
        }

        $s_ids = array_values(array_unique($s_ids));
        $c_ids = array_values(array_unique($c_ids));

        $expansionData = array(
            'subjects' => Subject::whereIn('_id', $s_ids)->get(),
            'classifications' => Classification::whereIn('_id', $c_ids)->get(),
        );
        $relations = array();
        foreach ($this->docs as $doc) {
            $t = $doc->getExpandedRelations($expansionData);
            $relations[$doc->_id] = array(
                'classifications' => $t['classifications'],
                'subjects' => $t['subjects'],
            );
        }
        return $relations;
    }

}