<?php

class ClassesController extends BaseController {

	public function getShow($id)
    {
        list($id, $format) = $this->getFormat($id);
        if (is_null($format)) {
            return $this->negotiateContentType('SubjectsController',
                array('id' => $id),
                'id'
            );
        }

        $instance = Classification::find($id);

        if (!$instance) {
            return Response::json(array(
                'error' => 'notFound',
            ));
        }

        $out = $instance->toArray();
        $out['documents'] = $instance->getDocuments();

        switch ($format) {

            case '.json':
                return Response::json($out);

            case '.rdf.xml':
            case '.rdf.nt':
            case '.rdf.n3':
            case '.rdf.jsonld':
            case '.html':
                App::abort(400, 'Format not supported yet');

            default:
                App::abort(400, 'Unknown format requested');
        }
    }

}
