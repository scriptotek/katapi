<?php

use Jenssegers\Mongodb\Model as Eloquent;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;
use Scriptotek\SimpleMarcParser\Parser;
use Scriptotek\SimpleMarcParser\ParserException;

class Document extends Eloquent {

	protected $collection = 'documents';

	protected $appends = array('subjects');
	protected $hidden = array('subject_ids', 'created_at', 'modified_at');
	protected $dates = array('record_created', 'record_modified');

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
		return $subjects;
	}

	/**
	 * Import a single record
	 *
	 * @param QuiteSimpleXmlElement $data
	 * @param Symfony\Component\Console\Output\Output $output
	 * @return boolean
	 */
	public function import(QuiteSimpleXmlElement $data, Symfony\Component\Console\Output\Output $output)
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

				if ($this->bibsys_id != $parsed->id) {
					$err = sprintf('Record import failed: ID %s does not match %s', $parsed->id, $this->bibsys_id);
					Log::error($err);
					$output->writeln("<error>$err</error>");
					return false;
				}

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
				foreach ($this->subjects()->get() as $localSubject) {
					$localSubjects[$localSubject->id] = array($localSubject->vocabulary, $localSubject->indexTerm, $localSubject);
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
