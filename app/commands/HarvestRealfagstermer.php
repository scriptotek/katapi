<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Carbon\Carbon;

class HarvestRealfagstermer extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'harvest:realfagstermer';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Harvest SKOS concepts from RDF file.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{

		// The query log is kept in memory, so we should disable it for long-running
		// tasks to prevent memory usage from increasing linearly over time
		DB::connection()->disableQueryLog();

		$filename = $this->argument('filename');

		$this->info('');
		$this->info('============================================================');
		$this->info(sprintf('@ %s: Starting RDF harvest',
			strftime('%Y-%m-%d %H:%M:%S')
		));

		$this->info(sprintf('@ Filename: %s',
			$filename
		));
		$this->info('------------------------------------------------------------');

		$this->harvest($filename);

		// $this->info(sprintf('@ Processed %d records', $recordsProcessed));
		// $this->info(sprintf('@ %s: Completing OAI harvest',
		// 	strftime('%Y-%m-%d %H:%M:%S')
		// ));
		// $this->info('------------------------------------------------------------');
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('filename', InputArgument::REQUIRED, 'The RDF/SKOS filename'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(

		);
	}

	public function getLangValue(EasyRdf_Literal $label)
	{
		$defaultLang = 'nb';

		$lang = $label->getLang();
		if (!isset($lang)) {
			if ($this->output->isVerbose()) {
				$this->output->writeln('WARN: Label without lang: ' . $label->getValue());
			}
			$lang = $defaultLang;
		}
		$value = $label->getValue();

		return array($lang, $value);
	}

	/**
	 * Harvest records using the OaiClient
	 */
	public function harvest($filename)
	{

		// Load everything into memory.
		// Shouldn't take more than approx 30 seconds

		$this->output->writeln('Parsing ' . $filename . ' (might take 30-60 seconds)');
		$graph = new EasyRdf_Graph;
		$graph->parseFile($filename, 'rdfxml');

		$this->output->writeln('Traversing concepts');

		// Alternativ 2:

		// redstore -p 8080 -b localhost -n -s memory
		// curl -T ~/data/realfagstermer/realfagstermer20140408.rdf http://localhost:8080/data/realfagstermer.rdf
		
		//$gs = new EasyRdf_GraphStore('http://localhost:8080/');
		//$graph = $gs->get('http://localhost:8080/data/realfagstermer.rdf');

		EasyRdf_Namespace::set('skos', 'http://www.w3.org/2004/02/skos/core#');

		// $sparql = new EasyRdf_Sparql_Client('http://localhost:8080/query');

		// $result = $sparql->query("
		 //        SELECT ?concept WHERE {
		 //          ?concept rdf:type skos:Concept .
		 //        }"
		 //    );
		 //    foreach ($result as $row) {
		 //        echo "<li>".link_to($row->label, $row->country)."</li>\n";
		 //    }

		$counts = array(
			'added' => 0,
			'changed' => 0,
			'removed' => 0,
			'unchanged' => 0,
		);

		foreach ($graph->allOfType('skos:Concept') as $concept) {  // as EasyRdf_Resource

			# identifier to separate from the mongodb id
			$identifier = explode('#', $concept->getUri())[1];

			$out = array(
				'identifier' => $identifier,
				'prefLabels' => array(),
				'altLabels' => array(),
			);
			foreach ($concept->allLiterals('skos:prefLabel') as $label) {
				list($lang, $value) = $this->getLangValue($label);
				$out['prefLabels'][$lang] = $value;
			}

			foreach ($concept->allLiterals('skos:altLabel') as $label) {
				list($lang, $value) = $this->getLangValue($label);
				if (!isset($out['altLabels'][$lang])) {
					$out['altLabels'][$lang] = array(); 
				}
				$out['altLabels'][$lang][] = $value;
			}

			if (!isset($out['prefLabels']['nb'])) {
				$this->output->writeln('<error>ERROR: ' . $out['identifier'] . ' does not have a skos:prefLabel@nb</error>');
				die;
			}

			if ($this->output->isVeryVerbose()) {
				$this->output->writeln($out['identifier'] . ' : ' . $out['prefLabels']['nb']);
			}

			$status = $this->store($out);
			$counts[$status]++;
		}

		// TODO: Purge any subjects in the database that are not in the RDF...

		$this->output->writeln(sprintf('%d concepts added, %d concepts changed, %d concepts removed, %d concepts unchanged', $counts['added'], $counts['changed'], $counts['removed'], $counts['unchanged']));
	}

	/**
	 * Store a single concept
	 * {
	 *   identifier: "REAL000001",
	 *   prefLabels: {
	 *     nb: "Test",
	 *     en: "Test"
	 *   },
	 *   altLabels: {
	 *     nb: ["Testing"]
	 *   }
	 * }
	 * @return 'added', 'changed', 'unchanged' or 'removed'
	 */
	public function store($concept)
	{
		// // ex.: oai:bibsys.no:collection:901028711
		// $id = preg_replace('/.*:/', '', $record->identifier);
		$status = 'unchanged';

		$identifier = $concept['identifier'];
		$doc = Realfagsterm::where('identifier', '=', $identifier)->first();
		if (is_null($doc)) {
			Log::info("CREATE subject $identifier during RDF harvest of Realfagstermer");
			$status = 'added';
			//$this->output->write("CREATE concept: $identifier", true);
			$doc = new Realfagsterm;
			$doc->identifier = $identifier;
			$doc->save();
		}
		$doc->import($concept);
		if ($status == 'unchanged' && $doc->isDirty()) {
			$status = 'changed';
		}
		if (!$doc->save()) {  // No action done if record not dirty
			$this->output->writeln("<error>Subject $identifier could not be saved!</error>");
			die;
		}
		return $status;
	}

}
