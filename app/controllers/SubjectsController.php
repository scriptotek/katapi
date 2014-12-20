<?php

use Scriptotek\Sru\Client as SruClient;
use Scriptotek\SimpleMarcParser\Parser;
use \Guzzle\Http\Client as HttpClient;

class SubjectsController extends BaseController {

	/**
	 *  Does content negotiation and 303 redirect
	 */
	public function getId($vocabulary, $term)
	{
		return $this->negotiateContentType('SubjectsController', array(
			'vocabulary' => $vocabulary, 'term' => $term)
		);
	}

	public function getShow($vocabulary, $term, $format)
	{
		$subject = Subject::with('documents')
			->where('vocabulary', '=', $vocabulary)
			->where('indexTerm', '=', $term)->first();
		if (!$subject) {
			return Response::json(array(
				'error' => 'notFound',
			));
		}

		switch ($format) {

			case 'rdf.xml':
			case 'rdf.nt':
			case 'rdf.n3':
			case 'rdf.jsonld':
				App::abort(400, 'Format not supported yet');

			case 'json':
				return Response::json($subject);

			case 'html':
				# Not supported yet
				return Redirect::action('SubjectsController@getShow', 
					array('vocabulary' => $vocabulary, 'term' => $term, 'format' => 'json')
				);

			default:
				App::abort(400, 'Unknown format requested');
		}

	}

}
