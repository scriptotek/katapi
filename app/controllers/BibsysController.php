<?php

use Scriptotek\Sru\Client as SruClient;
use Danmichaelo\SimpleMarcParser\BibliographicParser;
use Danmichaelo\SimpleMarcParser\HoldingsParser;
use \Guzzle\Http\Client as HttpClient;

class BibsysController extends DocumentsController implements VendorInterface {

	/**
	 * SRU base url
	 */
	protected $baseUrl = 'http://sru.bibsys.no/search/biblioholdings';

	/**
	 * Options for the SruClient
	 */
	protected $sruOptions = array(
		'schema' => 'marcxchange',
		'version' => '1.1'
	);

	public function getIndex()
	{
		// TODO: show search form?
	}

	private function lookupIds($res) {

		//$url = 'http://adminwebservices.bibsys.no/objectIdService/getObjectId?id=' . $id;
		$url = 'http://adminwebservices.bibsys.no/objectIdService/getIds?id=' . $res['id'];

        $http = new HttpClient;
        $httpRes = $http->get($url)->send();
        $response = trim($httpRes->getBody(true));

		$ids = array();
		$keys = array(
			'objektId' => 'objektid',
			'dokumentId' => 'dokid',
			'hefteId' => 'heftid',
		);
		foreach (explode("\n", $response) as $line) {
			list($key, $val) = explode(':', $line);
			$ids[$keys[$key]] = strtolower(trim($val));
		}

		$ids['knyttid'] = '';
		if (!in_array($res['id'], array($ids['dokid'], $ids['objektid']))) {
			 $ids['knyttid'] = $res['id'];
		}

		$res['id'] = $ids['objektid'];
		$res['ids'] = $ids;
		return $res;
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  string  $id
	 * @return Response
	 */
	public function getShow($id)
	{
		$id = strtolower(trim($id));
		$id = str_replace('-', '', $id);
		$id = filter_var($id, FILTER_VALIDATE_REGEXP, array('options' => array('regexp'=>'([a-z0-9]+)')));
		if (empty($id)) {
			App::abort(404, 'Empty or invalid id');
		}
		if (strlen($id) == 9) {
			$res = array('id' => $id);
			$res = $this->lookupIds($res);
			$this->query = 'rec.identifier="{{id}}"';
		} else if (strlen($id) == 10 || strlen($id) == 13) {
			$res = array('isbn' => array($id));
			$this->query = 'bs.isbn="{{isbn}}"';
		} else {
			App::abort(404, 'Invalid id format');
		}

		return parent::lookup($res);

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
