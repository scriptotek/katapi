<?php

use Scriptotek\Sru\Client as SruClient;
use Danmichaelo\SimpleMarcParser\Parser;
use Guzzle\Http\Client as HttpClient;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;

class BibsysService {

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
	 * Lookup a document by id (objektid, dokid, knyttid or isbn)
	 *
	 * @param  string  $id
	 * @return QuiteSimpleXmlElement
	 */
	public function lookupId($id)
	{
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

		if (isset($res['id'])) {
			Clockwork::info('Querying for id: ' . $res['id']);
			$query = str_replace('{{id}}', $res['id'], $this->query);
		}
		if (isset($res['isbn']) && count($res['isbn']) > 0) {
			Clockwork::info('Querying for isbn: ' . $res['isbn'][0]);
			$query = str_replace('{{isbn}}', $res['isbn'][0], $this->query);
		}

		return $this->lookupQuery($query);
	}

	protected function lookupQuery($query) {

		$sru = new SruClient($this->baseUrl, $this->sruOptions);
		
		$response = $sru->search($query, 1, 1);

		if (count($response->records) == 0) {
			App::abort(404, 'Record not found');
		}

		$data = $response->records[0]->data;

		return $data; // TO BE IMPORTED BY Document::import


		//foreach ($res as $key => $value) {
		//	$rec->{$key} = $value;
		//}

		// BEGIN: RESPONSIBILITY OF Document::import, NO?

			$parser = new Parser;
			$r = $data->first('metadata/marc:collection/marc:record[@type="Bibliographic"]');

			$rec = $parser->parse($r);

			// From Carbon dates to datetime strings
			$rec->created = $rec->created->toDateTimeString();
			$rec->modified = $rec->modified->toDateTimeString();

			// To avoid conflict with the MongoDB ID
			$rec->bibsys_id = $rec->id;
			unset($rec->id);

			$rec->source = $sru->urlTo($query, 1, 1);

			$holdings = array();
			foreach ($data->xpath('metadata/marc:collection/marc:record[@type="Holdings"]') as $holding) {
				$h = $parser->parse($holding)->toArray();

				// From Carbon dates to datetime strings
				if (isset($h['created'])) {
					$h['created'] = $h['created']->toDateTimeString();
				}
				if (isset($h['acquired'])) {
					$h['acquired'] = $h['acquired']->toDateTimeString();
				}

				$holdings[] = $h;
			}
			$rec->holdings = $holdings;
		// END: RESPONSIBILITY OF Document::import

		return $this->showDocument($rec);
	}

	/**
	 * Lookup a document list by CQL query
	 *
	 * @param  string  $cql
	 *  
	 */
	public function search($cql)
	{
		return 'Hello ' . $cql;
	}


}
