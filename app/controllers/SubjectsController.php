<?php

use Scriptotek\Sru\Client as SruClient;
use Scriptotek\SimpleMarcParser\Parser;
use \Guzzle\Http\Client as HttpClient;

class SubjectsController extends BaseController {

	public function getShow($vocabulary, $term)
	{
		$subject = Subject::with('documents')
			->where('vocabulary', '=', $vocabulary)
			->where('indexTerm', '=', $term)->first();
		if (!$subject) {
			return Response::json(array(
				'error' => 'notFound',
			));
		}

		return Response::json($subject);
	}

}
