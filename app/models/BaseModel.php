<?php

use Jenssegers\Mongodb\Model as Eloquent;

class BaseModel extends Eloquent{

    /**
     * @param array $attributes
     * @return array
     */
    function flattenDates($attributes) {
        $dateFields = array('created', 'modified', 'assigned', 'acquired');
        foreach ($attributes as $key => $val) {
            if (is_array($val)) {
                $attributes[$key] = $this->flattenDates($val);
            } elseif (in_array($key, $dateFields, true)) {
                $attributes[$key] = is_null($attributes[$key]) ? null : (string) $this->asDateTime($attributes[$key]);
            }
        }
        return $attributes;
    }

    /**
     * Get an embedded subdocument by MongoId
     *
     * @param $group
     * @param $id
     * @return array|null
     */
    protected function getSubdocumentById($group, $id)
    {
        if (!isset($this->attributes[$group])) {
            return null;
        }
        foreach ($this->attributes[$group] as $subdoc) {
            if ($subdoc['internal_id'] == $id) {
                return $subdoc;
            }
        }
        return null;
    }

    /**
     * Get a subject heading instance by MongoID
     *
     * @param $value
     * @return array|null
     */
    protected function getSubjectById($value)
    {
        if (!isset($this->attributes['subjects'])) {
            return null;
        }
        foreach ($this->attributes['subjects'] as $c) {
            if ($c['internal_id'] == $value) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Get documents associated with a Subject or Classification instance.
     *
     * @param $key
     * @return array
     */
    function getRelatedDocuments($key)
    {
        // db.documents.find({ "classifications.internal_id": ObjectId("54a956d18b70d5b02a0041a7") } )
        $id = $this->id;
        $results = array();
        foreach (Document::where($key . '.internal_id', '=', new MongoId($id))->get() as $doc) {
            $item = array_first($doc->{$key}, function ($i, $val) use ($id) {
                return $val['internal_id'] == $id;
            });
            $biblio = $doc->bibliographic;
            $results[] = array(
                'id' => array_get($biblio, 'id'),
                'link' => $doc->link,
                'assigned' => $item['assigned'],
                'created' => array_get($biblio, 'created'),
                'title' => array_get($biblio, 'title'),
                'edition' => array_get($biblio, 'edition'),
                'year' => array_get($biblio, 'year'),
                'creators' => array_get($biblio, 'creators'),
            );
        }
        return $results;
    }

}
