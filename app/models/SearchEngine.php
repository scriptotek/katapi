<?php

use Isbn\Isbn;

class SearchEngine {

    protected function prepare($docs) {

        $docs = new Documents($docs);
        $docs = $docs->toArray();

        return array(
            'numberOfRecords' => count($docs),
            'nextRecordPosition' => null,
            'documents' => $docs,
            'query_engine' => 'local',
        );
    }

    public function ask(Query $query) {

        $map = array(
            'series' => array(
                'fields' => array('bibliographic.part_of.id', 'bibliographic.series.id'),
                'type' => 'id',
            ),
            'creator' => array(
                'fields' => 'bibliographic.creators.id',
                'type' => 'id'
            ),
            'title' => array(
                'fields' => 'bibliographic.title',
                'type' => 'text',
            ),
        );


        // Very temporary solution:
        if (preg_match('/^id:([0-9a-z-, ]+)$/i', $query->getQueryString(), $matches)) {
            $id = str_replace('-', '', strtolower($matches[1]));
            $ids = explode(',', $id);
            $isbn = new Isbn;
            $q = [];
            foreach ($ids as $id)
            {
                $id = trim($id);
                    if (strlen($id) == 9) {
                    $q[] = ['holdings.id' => $id];
                    $q[] = ['holdings.barcode' => $id];
                    $q[] = ['bibliographic.id' => $id];
                } elseif (strlen($id) == 10) {
                    $q[] = ['bibliographic.isbns' => $id];
                    $q[] = ['bibliographic.isbns' => $isbn->translate->to13($id)];
                } elseif (strlen($id) == 13) {
                    $q[] = ['bibliographic.isbns' => $id];
                    $q[] = ['bibliographic.isbns' => $isbn->translate->to10($id)];
                }
            }
            $q = ['$or' => $q];

            $res = Document::whereRaw($q)->get();

            return $this->prepare($res);
        } else if (preg_match('/^(' . implode('|', array_keys($map)) . '):(.+)$/i', $query->getQueryString(), $matches)) {

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

            return $this->prepare(Document::whereRaw($q)->get());

        } else {
            return null;
        }

        // In the future:
        $parser = new Parser('vocabulary:noubå "en \"setning" term:"Flere ord"');
        $ast = $parser->getAST(); // returns \Doctrine\ORM\Query\AST\SelectStatement

        var_dump($ast);
        die;
        return array();
    }

}