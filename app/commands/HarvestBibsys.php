<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;
use Scriptotek\Oai\Client as OaiClient;

class HarvestBibsys extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'harvest:bibsys';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Harvest records from OAI service and update database.';

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

		$this->info('');
		$this->info('============================================================');
		$this->info(sprintf('%s: Starting OAI harvest',
			strftime('%Y-%m-%d %H:%M:%S')
		));
		foreach (array('url', 'set', 'from', 'until', 'resume') as $key) {
			if (!is_null($this->option($key))) {
				$this->info(sprintf('- %s: %s', $key, $this->option($key)));
			}
		}

		$this->info('------------------------------------------------------------');

		$this->harvest(
			$this->option('url'),
			$this->option('from'),
			$this->option('until'),
			$this->option('set'),
			$this->option('resume')
		);

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
			array('url', null, InputArgument::OPTIONAL, 'Repository URL', 'http://utvikle-a.bibsys.no/oai/repository'),
			array('set', null, InputOption::VALUE_REQUIRED, 'OAI set name', 'ubo_komplett'),
			array('from', null, InputOption::VALUE_REQUIRED, 'From date (YYYY-MM-DD)'),
			array('until', null, InputOption::VALUE_REQUIRED, 'Until date (YYYY-MM-DD)'),
			array('resume', null, InputOption::VALUE_REQUIRED, 'Resumption token'),
		);
		// $url = 'http://oai.bibsys.no/repository';
		// $oaiSet = 'urealSamling42';
	}

	/**
	 * Harvest records using the OaiClient
	 */
	public function harvest($url, $startDate, $untilDate, $oaiSet, $resumptionToken = null)
	{

		$counts = array(
			'added' => 0,
			'changed' => 0,
			'removed' => 0,
			'unchanged' => 0,
			'errored' => 0
		);

		$client = new OaiClient($url, array(
			'schema' => 'marcxchange',
			'user-agent' => 'KatApi/0.1',
			'maxRetries' => 100,
		));

		if (!file_exists(storage_path("harvest"))) {
			mkdir(storage_path("harvest"));
		}

		$requestNo = 0;
		$client->on('request.error', function($err) {
			if (isset($this->progress)) $this->progress->clear();
			$this->output->writeln("\n<error>" . $err . '</error>');
			if (isset($this->progress)) $this->progress->display();
		});
		$client->on('request.complete', function($verb, $args, $body) use ($requestNo) {
			$requestNo++;
			$fname = storage_path("harvest/harvest$requestNo.xml");
			file_put_contents($fname, $body);
		});

		$records = $client->records(
			$startDate,
			$untilDate,
			$oaiSet,
			$resumptionToken
		);

		if ($records->error) {
			$this->progress->clear();
			$this->output->writeln("\n<error>" . $records->errorCode . ' : ' . $records->error . '</error>');
			die;
		}

		$this->progress = $this->getHelperSet()->get('progress');
		$this->progress->start($this->output, $records->numberOfRecords);

		$resumptionToken = '';
		foreach ($records as $record) {
			$this->progress->advance();
			$status = $this->store($record, $oaiSet);
			$counts[$status]++;
			if ($resumptionToken != $records->getResumptionToken()) {
				$resumptionToken = $records->getResumptionToken();
				Log::info('Got resumption token: ' . $resumptionToken);
				file_put_contents(storage_path('resumption_token'), $resumptionToken); 
			}
		}
		$this->progress->finish();

		// TODO: Purge any subjects in the database that are not in the RDF...

		$this->output->writeln(sprintf('%d records added, %d records changed, %d records removed, %d errored, %d records unchanged', $counts['added'], $counts['changed'], $counts['removed'], $counts['errored'], $counts['unchanged']));

		return true;
	}

	/**
	 * Store a single record
	 *
	 * @return 'added', 'changed', 'unchanged' or 'removed'
	 */
	public function store($record, $oaiSet)
	{
		$status = 'unchanged';

		// ex.: oai:bibsys.no:collection:901028711
		$bibsys_id = $record->data->text('.//marc:record[@type="Bibliographic"]/marc:controlfield[@tag="001"]');
		if (strlen($bibsys_id) != 9) {
			Log::error("[$record->identifier] Invalid record id: $bibsys_id");
			$this->progress->clear();
			$this->output->writeln("\n<error>[$record->identifier] Invalid record id: $bibsys_id</error>");
			$this->progress->display();
			return 'errored';
		}

		$doc = Document::where('bibsys_id', '=', $bibsys_id)->first();
		if (is_null($doc)) {
			Log::info(sprintf('[%s] CREATE document', $bibsys_id));
			$status = 'added';
			$doc = new Document;
			$doc->bibsys_id = $bibsys_id;
			$doc->save();
		} else {
			// Log::info(sprintf('[%s] UPDATE document', $bibsys_id));
		}
		if (!$doc->import($record->data, $this->output)) {
			Log::error("[$record->identifier] Import failed: Invalid record");
			$this->progress->clear();
			$this->output->writeln("\n<error>[$record->identifier] Import failed: Invalid record, see log for details.</error>");
			$this->progress->display();
			return 'errored';
		}
		if (!isset($doc->sets)) {
			$doc->sets = array();
		}
		if (!in_array($oaiSet, $doc->sets)) {
			$sets = $doc->sets;
			$sets[] = $oaiSet;
			$doc->sets = $sets;
		}

		if ($status == 'unchanged' && $doc->isDirty()) {
			$status = 'changed';

			// foreach ($doc->getAttributes() as $key => $val) {
			// 	if ($doc->isDirty($key)) {
			// 		$original = $doc->getOriginal($key);

			// 		if ($original) {
			// 			$current = $val;
			// 			print "-------------------------------------------\n";
			// 			print "Key: $key\n";
			// 			// print get_class($original) . "\n";
			// 			// print get_class($current) . "\n";
			// 			// print 'MongoDates equal: ' . (($original == $current) ? 'true' : 'false') . "\n";
			// 			// print 'Seconds equal: ' . (($original->sec == $current->sec) ? 'true' : 'false') . "\n";
			// 			// print 'MSecs equal: ' . (($original->usec == $current->usec) ? 'true' : 'false') . "\n";
			// 			var_dump($original);
			// 			var_dump($current);
			// 			print "-------------------------------------------\n";
			// 		}
			// 	}
			// }

		}
		if (!$doc->save()) {  // No action done if record not dirty
			$err = "[$record->identifier] Document $id could not be saved!";
			Log::error($err);
			$this->output->writeln("<error>$err</error>");
			return 'errored';
		}
		return $status;
	}

}
