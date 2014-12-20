<?php

use \Guzzle\Http\Client as HttpClient;

class LibrariesController extends BaseController {

	public function getShow($id)
	{
		$xml_string = file_get_contents('http://www.nb.no/BaseBibliotekSearch/rest/bibkode/' . $id);
		$xml = simplexml_load_string($xml_string);
		return Response::JSON($xml);
	}

}
