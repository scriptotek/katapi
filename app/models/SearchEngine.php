<?php

use Isbn\Isbn;

class SearchEngine {

    protected function prepare($docs) {
        $docs = $docs->toArray();
        return array(
            'numberOfRecords' => count($docs),
            'nextRecordPosition' => null,
            'documents' => $docs,
        );
    }

    public function ask(Query $query) {

        // Very temporary solution:
        if (preg_match('/^id:([0-9a-z-]+)$/i', $query->getQueryString(), $matches)) {
            $id = str_replace('-', '', strtolower($matches[1]));

            $isbn = new Isbn();
            $q = array();
            if (strlen($id) == 9) {
                $q[] = array('holdings.id' => $id);
                $q[] = array('holdings.barcode' => $id);
            } elseif (strlen($id) == 10) {
                $q[] = array('bibliographic.id' => $id);
                $q[] = array('bibliographic.isbns' => $id);
                $q[] = array('bibliographic.isbns' => $isbn->translate->to13($id));
            } elseif (strlen($id) == 13) {
                $q[] = array('bibliographic.isbns' => $id);
                $q[] = array('bibliographic.isbns' => $isbn->translate->to10($id));
            }
            $q = array('$or' => $q);

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