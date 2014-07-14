<?php

use Jenssegers\Mongodb\Model as Eloquent;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;
use Scriptotek\SimpleMarcParser\Parser;
use Scriptotek\SimpleMarcParser\ParserException;

class Document extends Eloquent {

	protected $collection = 'documents';

	protected $appends = array('subjects');
	protected $hidden = array('_id', 'subject_ids', 'created_at', 'modified_at');
	protected $dates = array('record_created', 'record_modified');

	public function getHoldingsAttribute($value)
	{
		foreach ($value as $key => $val) {
			if (isset($val['created'])) {
				$value[$key]['created'] = $this->asDateTime($val['created']);
			}
			if (isset($val['acquired'])) {
				$value[$key]['acquired'] = $this->asDateTime($val['acquired']);
			}
		}
		return $value;
	}

	public function setHoldingsAttribute($value)
	{
		foreach ($value as $key => $val) {
			if (isset($val['created'])) {
				$value[$key]['created'] = $this->fromDateTime($val['created']);
			}

			if (isset($val['acquired'])) {
				$value[$key]['acquired'] = $this->fromDateTime($val['acquired']);
			}
		}
		$this->attributes['holdings'] = $value;
	}

	/**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        // DateTime objects need to be converted to strings. Eloquent handles objects in the
        // root of the document, but not in subdocuments (such as dates in the 'holdings' subdocuments)
        // To make sure we have a consistent handling of dates, we convert all dates here.
        //
        // TODO: Automatically locate all DateTime fields instead of specifying them manually
        //
		if (isset($this->record_created)) $attributes['record_created'] = $this->record_created->toDateTimeString();
		if (isset($this->record_modified)) $attributes['record_modified'] = $this->record_modified->toDateTimeString();
		$holdings = array();
		foreach ($this->holdings as $key => $holding) {
			if (isset($holding['created'])) {
				$holding['created'] = $holding['created']->toDateTimeString();
			}
			if (isset($holding['acquired'])) {
				$holding['acquired'] = $holding['acquired']->toDateTimeString();
			}
			$holdings[] = $holding;
		}
		$attributes['holdings'] = $holdings;

        return $attributes;
    }

    /* Accessor for the subjects attribute */
	public function getSubjectsAttribute()
	{
		$subjects = array();
		foreach ($this->subjects()->get() as $i) {
			$s = array(
				'vocabulary' => $i->vocabulary,
				'indexTerm' => $i->indexTerm,
				'uri' => URL::action('SubjectsController@getShow', array('vocabulary' => $i->vocabulary, 'term' => $i->indexTerm)),
			);
			if (isset($i->id)) $s['id'] = $i->id;
			$subjects[] = $s;
		}

		// Sort subject headings by vocabulary
		usort($subjects, function($a, $b) {			
		    return strcmp(array_get($a, 'vocabulary', ''), array_get($b, 'vocabulary', ''));
		});

		return $subjects;
	}

	/**
	 * Import a single record
	 *
	 * @param QuiteSimpleXmlElement $data
	 * @param Symfony\Component\Console\Output\Output $output
	 * @return boolean
	 */
	public function import(QuiteSimpleXmlElement $data, Symfony\Component\Console\Output\Output $output = null)
	{
		$parser = new Parser;

		$holdings = array();

		foreach ($data->xpath('.//marc:record') as $rec) {

			try {
				$parsed = $parser->parse($rec);
			} catch (ParserException $e) {
				$err = sprintf('Record import failed: %s', $e->getMessage());
				Log::error($err);
				return false;
			}

			if ($parsed instanceof Scriptotek\SimpleMarcParser\BibliographicRecord) {

				if (isset($this->bibsys_id) && $this->bibsys_id != $parsed->id) {
					$err = sprintf('Record import failed: ID %s does not match %s', $parsed->id, $this->bibsys_id);
					Log::error($err);
					if (!is_null($output)) {
						$output->writeln("<error>$err</error>");
					}
					return false;
				}
				$this->bibsys_id = $parsed->id;

				foreach ($parsed->toArray() as $key => $val) {
					switch ($key) {
						case 'id':
						//case 'isbns':
						case 'subjects':
							// ignore
							break;
						case 'created':
						case 'modified':
							// To avoid unecessary updates, it's important to only updates dates if they *actually*
							// changed. In Eloquent, attributes are marked as dirty if not *identical*, but
							// dates are objects, and two objects are identical if and only if they refer to the 
							// *same instance* of the same class. So two date instances with the same numerical content 
							// will not be identical.
							$modKey = "record_" . $key;
							if (!isset($this->{$modKey}) || ($this->{$modKey} != $val)) {
								$this->{$modKey} = $val;
							}
							break;
						default:
							$this->{$key} = $val;
					}
				}

				$localSubjects = array();
				if (!is_null($this->id)) {  // Model has been stored to DB			
					foreach ($this->subjects()->get() as $localSubject) {
						$localSubjects[$localSubject->id] = array($localSubject->vocabulary, $localSubject->indexTerm, $localSubject);
					}
				}

				if (isset($parsed->subjects)) {
					foreach ($parsed->subjects as $subject) {

						if (isset($subject['term']) && isset($subject['vocabulary'])) {

							$subj = Subject::where('vocabulary', '=', $subject['vocabulary'])
								              ->where('indexTerm', '=', $subject['term'])
								              ->first();


							// CREATE subject if it doesn't exist
							if (!$subj) {
								Log::info(sprintf('CREATE subject {vocabulary: "%s", indexTerm: "%s"} during import of document %s', $subject['vocabulary'], $subject['term'], $parsed->id));

								// print(sprintf('CREATE subject {vocabulary: "%s", indexTerm: "%s"} during import of document %s', $subject['vocabulary'], $subject['term'], $parsed->id));
								$subj = new Subject(array(
									'vocabulary' => $subject['vocabulary'],
									'indexTerm' => $subject['term'],
								));
								$subj->save();
							}

							// ADD subject to document
							$fnd = false;
							foreach ($localSubjects as $sid => $localSubject) {
								// print " - " . $subject2['indexTerm'] . "\n";
								if ($subject['term'] == $localSubject[1] && $subject['vocabulary'] == $localSubject[0]) {
									$fnd = true;
								}
							}
							if (!$fnd) {
								Log::info(sprintf('[%s] ADD subject %s:%s', $parsed->id, $subject['vocabulary'], $subject['term']));
								$localSubjects[$subj->id] = array($subject['vocabulary'], $subject['term']);
								$this->subjects()->attach($subj);
							}

						}
					}

					$ids = array();
					$masterSubjects = $parsed->subjects;
					foreach ($localSubjects as $sid => $localSubject) {

						$fnd = false;
						foreach ($masterSubjects as $k => $masterSubject) {

							if ($masterSubject['term'] == $localSubject[1] && array_get($masterSubject, 'vocabulary', '') == $localSubject[0]) {
								$fnd = true;
								// print $masterSubject['term'] . "\n";
								unset($masterSubjects[$k]);
								break;
							}
						}
						if (!$fnd) {
							Log::info(sprintf('[%s] REMOVE subject %s:%s', $parsed->id, $localSubject[0] , $localSubject[1]));
							$this->subjects()->detach($localSubject[2]);
							unset($localSubjects[$sid]);
						}
					}
					//Log::info(sprintf("[%s] UPDATE set of subjects: (%s)", $parsed->id, implode(', ', array_keys($localSubjects))));
					//$this->subjects()->sync(array_keys($localSubjects));
					//Log::info('>OK');
				}
			}

			if ($parsed instanceof Scriptotek\SimpleMarcParser\HoldingsRecord) {

				$holdings[] = $parsed->toArray();
			}
		}

		$this->holdings = $holdings;
		return true;
	}

	public function subjects()
	{
		return $this->belongsToMany('Subject');
	}

}
