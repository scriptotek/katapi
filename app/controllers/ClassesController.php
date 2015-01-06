<?php

class ClassesController extends BaseController {

	public function getShow($system, $number)
	{
        list($number, $format) = $this->getFormat($number);
        if (is_null($format)) {
            return $this->negotiateContentType('ClassesController',
                array('system' => $system, 'number' => $number),
                'number'
            );
        }

        $instance = Classification::where('system', '=', $system)
			->where('number', '=', $number)
			->first();

		if (!$instance) {
			return Response::json(array(
				'error' => 'notFound',
			));
		}

        switch ($format) {

            case '.json':
                return Response::json($instance);

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
