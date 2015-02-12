<?php

use Isbn\Isbn;

class Query {

    protected $queryString;

    function __construct($queryString)
	{
        $this->queryString = $queryString;
    }

    function getQueryString() {
        return $this->queryString;
    }

    function getMongoQuery() {

        $map = array(
            'series' => array(
                'fields' => array('bibliographic.part_of.id', 'bibliographic.series.id'),
                'type' => 'id',
            ),
            'creator' => array(
                'fields' => 'bibliographic.creators.id',
                'type' => 'id'
            ),
            /*'title' => array(
                'fields' => 'bibliographic.title',
                'type' => 'text',
            ),*/
        );

        $vocabularies = [
            'real' => ['collection' => 'subjects', 'field' => 'noubomn'],
            'tek' => ['collection' => 'subjects', 'field' => 'tekord'],
            'ddc' => ['collection' => 'classifications', 'field' => 'ddc'],
        ];

        // Very temporary solution:
        if (preg_match('/^id:([0-9a-z-, ]+)$/i', $this->queryString, $matches)) {
            $id = str_replace('-', '', $matches[1]);
            $ids = explode(',', $id);
            $isbn = new Isbn;
            $q = [];
            foreach ($ids as $id)
            {
                $id = trim($id);
                    if (strlen($id) == 9) {
                    $q[] = ['holdings.id' => strtolower($id)];
                    $q[] = ['holdings.barcode' => strtolower($id)];
                    $q[] = ['bibliographic.id' => strtoupper($id)];
                } elseif (strlen($id) == 10) {
                    $id = strtoupper($id);
                    $q[] = ['bibliographic.isbns' => $id];
                    $q[] = ['bibliographic.isbns' => $isbn->translate->to13($id)];
                } elseif (strlen($id) == 13) {
                    $id = strtoupper($id);
                    $q[] = ['bibliographic.isbns' => $id];
                    $q[] = ['bibliographic.isbns' => $isbn->translate->to10($id)];
                }
            }
            if (!count($q)) {
                throw new InvalidQueryException('No valid ID given');
            }
            return ['$or' => $q];

        } else if (preg_match('/^(' . implode('|', array_keys($map)) . '):(.+)$/i', $this->queryString, $matches)) {

            $key = $matches[1];
            $val = $matches[2];
            if ($map[$key]['type'] == 'text')
            {
                $val = new MongoRegex('/' . $val . '/i');
            }

            if (is_array($map[$key]['fields'])) {
                $q = ['$or' => array_map(function($x) use ($val) {
                    return array($x => $val);
                }, $map[$key]['fields'])];
            } else {
                $q = array($map[$key]['fields'] => $val);
            }

            return $q;        

        } else if (preg_match('/^(' . implode('|', array_keys($vocabularies)) . '):(.+)$/i', $this->queryString, $matches)) {

            $key = $matches[1];
            $val = $matches[2];
            $col = $vocabularies[$key]['collection'];
            $fld = $vocabularies[$key]['field'];

            if ($col == 'subjects') {
                $res = Subject::where(['vocabulary' => $fld, 'indexTerm' => $val]);
            } else {
                $res = Classification::where(['system' => $fld, 'number' => $val]);
            }

            $ids = [];
            foreach ($res->get() as $o) {
                $ids[] = new MongoId($o->id);
            }
            if (!count($ids)) {
                throw new InvalidQueryException('Subject/class not found');
            }

            return array($col . '.internal_id' => ['$in' => $ids]);
        }
    }

}