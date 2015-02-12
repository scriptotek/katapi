<?php

class SearchEngine {

    protected function success($docs, $count = 0) {

        $docs = new Documents($docs);
        $docs = $docs->toArray();

        return array(
            'numberOfRecords' => $count ?: count($docs),
            'offset' => 0,
            'nextRecordPosition' => null,
            'documents' => $docs,
            'query_engine' => 'local',
        );
    }

    protected function error($msg) {

        return array(
            'error' => $msg,
            'numberOfRecords' => 0,
            'documents' => [],
            'query_engine' => 'local',
        );
    }

    public function ask(Query $query) {

        try {
            $mq = $query->getMongoQuery();
        } catch (InvalidQueryException $e) {
            Log::error('Invalid query: ' . $e->getMessage());
            return $this->error('Invalid query: ' . $e->getMessage());
        }
        if (is_null($mq)) return null;

        try {
            $cursor = Document::whereRaw($mq);
            $count = $cursor->count();
            $docs = $cursor->limit(10)->get();
        } catch (\MongoCursorTimeoutException $e) {
            Log::error('MongoDB query timeout. Query was: ' . json_encode($mq, true));
            return $this->error('Uh oh, the query timed out.');
        }
        return $this->success($docs, $count);

        // In the future:
        $parser = new Parser('vocabulary:noubå "en \"setning" term:"Flere ord"');
        $ast = $parser->getAST(); // returns \Doctrine\ORM\Query\AST\SelectStatement

        var_dump($ast);
        die;
        return array();
    }

}
