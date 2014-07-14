<?php

use Scriptotek\Sru\Client as SruClient;
use Scriptotek\SimpleMarcParser\Parser;
use \Guzzle\Http\Client as HttpClient;

class DocumentsController extends BaseController {

	protected $userAgent = 'KatApi/0.1';

	protected $query = 'rec.identifier="{{id}}"';

	public function getIndex()
	{
		// show search box?
		return View::make('hello');
	}

	public function getShow($id)
	{
		$rec = array('id' => $id);
		$doc = Document::where('bibsys_id', '=', $id)->first();
		if (!$doc) {
			$bs = new BibsysController;
			return $bs->getShow($id);
		}

		$doc->served_by = 'local_db';
		return $this->showDocument($doc);
	}

	public function lookup($res) {

		$sru = new SruClient($this->baseUrl, $this->sruOptions);
		if (isset($res['id'])) {
			Clockwork::info('Querying for id: ' . $res['id']);
			$query = str_replace('{{id}}', $res['id'], $this->query);
		}
		if (isset($res['isbn']) && count($res['isbn']) > 0) {
			Clockwork::info('Querying for isbn: ' . $res['isbn'][0]);
			$query = str_replace('{{isbn}}', $res['isbn'][0], $this->query);
		}
		$response = $sru->search($query, 1, 1);

		if (count($response->records) == 0) {
			App::abort(404, 'Record not found');
		}

		$data = $response->records[0]->data;

		$parser = new Parser;
		$r = $data->first('metadata/marc:collection/marc:record[@type="Bibliographic"]');

		$rec = $parser->parse($r);
		$rec->created = $rec->created->toDateTimeString();
		$rec->modified = $rec->modified->toDateTimeString();

		$rec->source = $sru->urlTo($query, 1, 1);
		foreach ($res as $key => $value) {
			$rec->{$key} = $value;
		}

		$holdings = array();
		foreach ($data->xpath('metadata/marc:collection/marc:record[@type="Holdings"]') as $holding) {
			$holdings[] = $parser->parse($holding)->toArray();
		}
		$rec->holdings = $holdings;
		$rec->served_by = 'bibsys_sru';

		$rec->bibsys_id = $rec->id; // to avoid confusion with MongoDB ID
		unset($rec->id);

		return $this->showDocument($rec);
	}

	public function showDocument($doc)
	{
		// Add links
		$links = array(
			array(
				'rel' => 'self',
				'uri' => URL::current()
			)
		);
		$doc->links = $links;

		if (isset($doc->other_form) && isset($doc->other_form['id'])) {
			$of = $doc->other_form;
			$of['uri']= URL::action('DocumentsController@getShow', array($doc->other_form['id']));
			$doc->other_form = $of;
		}


		switch ($this->getRequestFormat()) {
			case 'json':
				// JSON-LD...
				// $doc->{"@context"} = array(
				// 	"other_form" => array(
				// 		'uri' => array(
				// 		     "@id" => "http://katapi.biblionaut.net/docs#Document",
				// 		     "@type" => "@id",
				// 		)
				// 	)
				// );
				return Response::json($doc);
			case 'html':
				return View::make('documents.show', array(
					'doc' => $doc
				));
			default:
				App::abort(400, 'Unknown format requested');
		}

	}

}
