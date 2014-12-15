<?php

use Jenssegers\Mongodb\Model as Eloquent;

class Classification extends Eloquent {

	protected $collection = 'classifications';

	/**
	 * Authoritative list of vocabulary names
	 * Ref: http://www.loc.gov/standards/sourcelist/classification.html
	 */
	public static $systems = array(
		'acmccs' => 'CCS',
		'ddc' => 'DDC',
		'no-ureal-ca' => 'Astrofysisk hylleoppstilling',
		'no-ureal-cb' => 'Biologisk hylleoppstilling',
		'no-ureal-cg' => 'Geofysisk hylleoppstilling',
		'inspec' => 'INSPEC',
		'msc' => 'MSC',
		'nlm' => 'NLM-klassifikasjon',
		'oosk' => 'UBB-klassifikasjon',
		'udc' => 'UDC',
		'utk' => 'UBO-klassifikasjon',
	);

}
