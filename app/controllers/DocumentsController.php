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

	public function lookup($res) {

		$sru = new SruClient($this->baseUrl, $this->sruOptions);
		if (isset($res['id'])) {
			$query = str_replace('{{id}}', $res['id'], $this->query);
		}
		if (isset($res['isbn']) && count($res['isbn']) > 0) {
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
		$rec->source = $sru->urlTo($query, 1, 1);
		foreach ($res as $key => $value) {
			$rec->{$key} = $value;
		}

		$holdings = array();

		foreach ($data->xpath('metadata/marc:collection/marc:record[@type="Holdings"]') as $holding) {
			$h = $parser->parse($holding);
			$holdings[] = $h;
		}
		$rec->holdings = $holdings;

		$rec->subjects = array_map(function($subj) {
			$term = $subj['term'];
			if (isset($subj['subdivisions']['topical'])) $term .= ' : ' . $subj['subdivisions']['topical'];
			if (isset($subj['subdivisions']['form'])) $term .= ' : ' . $subj['subdivisions']['form'];
			if (isset($subj['subdivisions']['chronological'])) $term .= ' : ' . $subj['subdivisions']['chronological'];
			if (isset($subj['subdivisions']['geographic'])) $term .= ' : ' . $subj['subdivisions']['geographic'];
			$o = array(
				'term' => $term
			);
			if (isset($subj['vocabulary'])) $o['vocabulary'] = $subj['vocabulary'];
			return $o;
		}, $rec->subjects);

		return Response::json($rec);
	}

}
