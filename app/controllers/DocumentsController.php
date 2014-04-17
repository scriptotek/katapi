<?php

use Scriptotek\Sru\Client as SruClient;
use Danmichaelo\SimpleMarcParser\BibliographicParser;
use Danmichaelo\SimpleMarcParser\HoldingsParser;
use \Guzzle\Http\Client as HttpClient;

class DocumentsController extends BaseController {

	protected $vendors = array(
		'bibsys' => array(
			'url' => 'http://sru.bibsys.no/search/biblioholdings', 
			'options' => array(
				'schema' => 'marcxchange',
				'version' => '1.1',
				'user-agent' => 'KatApi/0.1',
			),
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
		$res = array('id' => $id);

		$sru = new SruClient($vendor['url'], $vendor['options']);
		$query = 'rec.identifier="' . $res['id'] . '"';

		//die($sru->urlTo($query));
		$response = $sru->search($query);

		if (count($response->records) == 0) {
			App::abort(404, 'Record not found');
		}

		$data = $response->records[0]->data;

		$parser = new BibliographicParser;
		$holdingsParser = new HoldingsParser;
		$r = $data->first('metadata/marc:collection/marc:record[@type="Bibliographic"]');

		$rec = $parser->parse($r);
		$rec['holdings'] = array();

		foreach ($data->xpath('metadata/marc:collection/marc:record[@type="Holdings"]') as $holding) {
			$h = $holdingsParser->parse($holding);
			$rec['holdings'][] = $h;
		}

		return Response::json($rec);
	}

	public function getSearch()
	{
		if (!isset($_GET['cql'])) {
			App::abort(404, 'No query given.');
		}
		$cql = $_GET['cql'];
		$cql = filter_var($cql, FILTER_SANITIZE_URL);
		return 'Hello ' . $cql;
	}


}