<?php

use Scriptotek\Sru\Client as SruClient;
use Scriptotek\SimpleMarcParser\Parser;
use \Guzzle\Http\Client as HttpClient;

class ClassesController extends BaseController {

	/**
	 *  Does content negotiation and 303 redirect
	 */
	public function getId($vocabulary, $term)
	{
		return $this->negotiateContentType('ClassesController', array(
			'system' => $system, 'number' => $number)
		);
	}

	public function getShow($system, $number, $format)
	{
		$cl = Subject::with('documents')
			->where('system', '=', $system)
			->where('number', '=', $number)
			->first();
		if (!$cl) {
			return Response::json(array(
				'error' => 'notFound',
			));
		}

		return Response::json($cl);
	}

}
