<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;
use Scriptotek\Oai\Client as OaiClient;
use Symfony\Component\Console\Helper\ProgressBar;

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
	protected $description = 'Harvest records from OAI-PMH service and update database.';

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

	protected $errorCount = 0;

	protected function errorMsg($value)
	{
		$this->errorCount++;
		$dt = strftime('%Y-%m-%d %H:%M:%S');
		Log::error('Harvest error ' . $this->errorCount . ': ' . $value);
		$this->output->writeln($dt . ' Error no. ' . $this->errorCount . ': <error>' . $value . '</error>');
	}

	/**
	 * Harvest records using the OaiClient
	 */
	public function harvest($url, $startDate, $untilDate, $oaiSet, $resumptionToken = null)
	{

		$recordsHarvested = 0;
		$t0 = $t1 = microtime(true) - 1;

		$client = new OaiClient($url, array(
			'schema' => 'marcxchange',
			'user-agent' => 'KatApi/0.1',
			'max-retries' => 1000,
            'sleep-time-on-error' => 60,
		));

		if (!file_exists(storage_path('harvest'))) {
			mkdir(storage_path('harvest'));
		}

		$client->on('request.error', function($err) {
			$this->errorMsg($err);
		});

		$client->on('request.complete', function($verb, $args, $body) {
			$sha = sha1(json_encode($args));
			$fname = storage_path("harvest/tmp.xml");
			file_put_contents($fname, $body);
		});

		$records = $client->records(
			$startDate,
			$untilDate,
			$oaiSet,
			$resumptionToken
		);

		// $this->progress = new ProgressBar($this->output, $records->numberOfRecords);
		// $this->progress->setFormat('Harvesting: %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
		// $this->progress->start();

		// $resumptionToken = '';
		while (true) {

			if (!$records->valid()) {
				break 1;
			}
			$record = $records->current();
			$recordsHarvested++;

			if ($resumptionToken != $records->getResumptionToken()) {
				$resumptionToken = $records->getResumptionToken();
				// Log::info('Got resumption token: ' . $resumptionToken);
				file_put_contents(storage_path('resumption_token'), $resumptionToken); 
			}

			$currentIndex = $records->key()
					- count($records->getLastResponse()->records); // since Bibsys can't count right :D

			if (is_file(storage_path("harvest/tmp.xml"))) {
				rename(storage_path("harvest/tmp.xml"), storage_path(sprintf("harvest/f_%08d.xml", $currentIndex)));
			}

			$batch = 1000;
			if ($recordsHarvested % $batch == 0) {
				$dt = microtime(true) - $t1;
				$dt2 = microtime(true) - $t0;
				$mem = round(memory_get_usage()/1024/102.4)/10;
				$t1 = microtime(true);
				$percentage = $recordsHarvested / $records->numberOfRecords;
				$eta = '';	
				if ($percentage < 1.0) {
					$et = $dt2 / $percentage - $dt2;
					$h = floor($et / 3600);
					$m = floor(($et - ($h * 3600)) / 60);
					$s = round($et - $h * 3600 - $m * 60);
					$eta = 'ETA: ' . sprintf("%02d:%02d:%02d", $h, $m, $s) . ', ';
				}
				$recsPerSecCur = $batch/$dt;
				$recsPerSec = $recordsHarvested / $dt2;

				$this->info(sprintf(
					'%s %d / %d records (%.2f %%), %sCurrent speed: %.1f recs/s, Avg speed: %.1f recs/s, Mem: %.1f MB.',
					strftime('%Y-%m-%d %H:%M:%S'),
					$currentIndex,
					$records->numberOfRecords,
					$percentage * 100,
					$eta,
					$recsPerSecCur,
					$recsPerSec,
					$mem,
					$recordsHarvested
				));
			}


			while (true) {
				$attempt = 1;
				try {
					$records->next();
					break 1;
				} catch (Scriptotek\Oai\BadRequestError $e) {
					// OAI-PMH servers really shouldn't throw
					// random errors now and then, but some do...
					$attempt++;
					$this->errorMsg('Bad request. Attempt ' . $attempt . ' of 500. Sleeping 60 secs.');
					if ($attempt > 500) {
						throw $e;
					}
					sleep(60);
				}
			}
		}
		// $this->progress->finish();

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
			// $this->progress->clear();
			$this->output->writeln("\n<error>[$record->identifier] Invalid record id: $bibsys_id</error>");
			// $this->progress->display();
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
		try {
			$doc->import($record->data, $this->output);
		} catch (Exception $e) {
			Log::error("[$record->identifier] Import failed: Invalid record. Exception '" . $e->getMessage() . "' in: " . $e->getFile() . ":" . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString());
			   //kk var_export($e->getTrace(), true) );
			// $this->progress->clear();
			$this->output->writeln("\n<error>[$record->identifier] Import failed: Invalid record, see log for details.</error>");
			// $this->progress->display();
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

			$msg = sprintf("[%s] UPDATE document\n", $bibsys_id);
			foreach ($doc->getAttributes() as $key => $val) {
				 if ($doc->isDirty($key)) {
					 $original = $doc->getOriginal($key);

					 if ($original) {
						 $current = $val;
						 $msg .= "Key: $key\n";
						 $msg .= "Old: " . json_encode($original) . "\n";
						 $msg .= "New: " . json_encode($current) . "\n";
						 $msg .= "-------------------------------------------\n";
					 }
				 }
			 }
			 Log::info($msg);

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
