<?php

use Scriptotek\Sru\Client as SruClient;
use Danmichaelo\SimpleMarcParser\BibliographicParser;

class DocumentsController extends BaseController {

	protected $vendors = array(
		'bibsys' => array(
			'url' => 'http://sru.bibsys.no/search/biblioholdings', 
			'options' => array(
			    'schema' => 'marcxchange',
			    'version' => '1.1',
			    'user-agent' => 'OpenKat/0.1'
			)
		)
	);

	public function getIndex()
	{
		// show search box?
		return View::make('hello');
	}

	public function getShow($vendor, $id)
	{
		$id = strtolower(trim($id));
		$id = filter_var($id, FILTER_VALIDATE_REGEXP, array('options' => array('regexp'=>'([a-z0-9]+)')));
		if (empty($id)) {
			App::abort(404, 'Empty or invalid id');			
		}
		if (!isset($this->vendors[$vendor])) {
			App::abort(404, 'Vendor not found');
		}

		$vendor = $this->vendors[$vendor];
		$sru = new SruClient($vendor['url'], $vendor['options']);
		$query = 'rec.identifier="' . $id . '"';

		//die($sru->urlTo($query));
		$response = $sru->search($query);

		if (count($response->records) == 0) {
			App::abort(404, 'Record not found');
		}

		$parser = new BibliographicParser;
		$r = $response->records[0]->data->first('metadata/marc:collection/marc:record');

		$rec = $parser->parse($r);

		return Response::json($rec);
	}

	public function getSearch($query)
	{
		return 'Hello ' . $query;
	}


}