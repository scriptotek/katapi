<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Scriptotek\Oai\ListRecordsResponse;
use Scriptotek\Oai\Record as OaiRecord;
use Scriptotek\SimpleMarcParser\ParserException;

class ImportHarvest extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'harvest:import';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Import records from harvest into MongoDB.';

	/**
	 * Create a new command instance.
	 *
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('folder', null, InputArgument::OPTIONAL, 'Harvest folder', 'harvest'),
			array('set', null, InputOption::VALUE_REQUIRED, 'OAI set name', 'ubo_komplett'),
		);
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
		$this->info(sprintf('%s: Starting import',
			strftime('%Y-%m-%d %H:%M:%S')
		));

		$folder = $this->option('folder');
		$folder = rtrim($folder, '/') . '/';
		$oaiSet = $this->option('set');

		$this->info(sprintf('- Folder: %s', $folder));
		$this->info(sprintf('- OAI set: %s', $oaiSet));

		$this->info('------------------------------------------------------------');

		$files = glob(storage_path($folder . '*.xml'));

		$this->importFiles($files, $oaiSet);

		$this->info(sprintf('@ %s: Completing OAI harvest',
			strftime('%Y-%m-%d %H:%M:%S')
		));
		$this->info('------------------------------------------------------------');
	}

	/**
	 * Write a string as error output.
	 *
	 * @param  string  $msg
	 * @return void
	 */
	public function error($msg)
	{
		Log::error($msg);
		$progressBarActive = (isset($this->progress) && (null !== $this->progress->getStartTime()));
		if ($progressBarActive) $this->progress->clear();
		$this->output->writeln("<error>$msg</error>");
		if ($progressBarActive) $this->progress->display();
	}

	/**
	 * Import records from a list of files
	 *
	 * @param $files string[]
	 * @param $oaiSet string
	 */
	function importFiles($files, $oaiSet) {

		// $this->progress = new ProgressBar($this->output, $records->numberOfRecords);
		// $this->progress->setFormat('Harvesting: %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
		// $this->progress->start();

		// $resumptionToken = '';
		// foreach ($records as $record) {
		// 	$status = $this->store($record, $oaiSet);
		// 	$counts[$status]++;
		// 	if ($resumptionToken != $records->getResumptionToken()) {
		// 		$resumptionToken = $records->getResumptionToken();
		// 		Log::info('Got resumption token: ' . $resumptionToken);
		// 		file_put_contents(storage_path('resumption_token'), $resumptionToken);
		// 	}
		// 	$this->progress->setCurrent($records->key());
		// }
		// $this->progress->finish();

		$t0 = $t1 = microtime(true);
		$recordNo = 0; $recordNoBatch = 0;
		$nFiles = count($files);

		foreach ($files as $fileNo => $filename) {

			if (($fileNo+2) % 10 == 0) {
				$dt = round((microtime(true) - $t1)*100)/100;
				$dt2 = round((microtime(true) - $t0)*100)/100;
				$mem = round(memory_get_usage()/1024/102.4)/10;
				$t1 = microtime(true);
				$percentage = ($fileNo + 1) / $nFiles;
				$et = $dt2 / $percentage - $dt2;
				$h = floor($et / 3600);
				$m = floor(($et - ($h * 3600)) / 60);
				$s = round($et - $h * 3600 - $m * 60);
				$nrecs = $recordNo - $recordNoBatch;
				$recordNoBatch = $recordNo;
				$recsPerSecCur = $nrecs/$dt;
				$recsPerSec = $recordNo / $dt2;

				$this->info(sprintf(
					'[%5.2f %%] ETA: %s, current speed: %d recs/s, avg speed: %d recs/s, mem: %.1f MB. Parsed %d records.',
					$percentage * 100,
					sprintf("%02d:%02d:%02d", $h, $m, $s),
					$recsPerSecCur,
					$recsPerSec,
					$mem,
					$recordNo
				));
			}

			try {
				$response = new ListRecordsResponse(file_get_contents($filename));
				foreach ($response->records as $record) {
					if ($this->importRecord($record, $oaiSet)) {
						$recordNo += 1;
					}
				}
			} catch (Danmichaelo\QuiteSimpleXMLElement\InvalidXMLException $e) {
				$this->error('Invalid XML found! Skipping file: ' . $filename);
			}

		}
	}

	/**
	 * Import a single record.
	 *
	 * @param OaiRecord $record
	 * @param $oaiSet
	 * @return boolean
	 */
	public function importRecord(OaiRecord $record, $oaiSet)
	{
		try {
			$doc = Document::fromRecord($record->data);
		} catch (ParserException $e) {
            $this->error('Failed to parse OAI record "' . $record->identifier . '". Error "' . $e->getMessage() . '" in: ' . $e->getFile() . ':' . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString());
            return false;
        }

		$id = $doc->bibliographic['id'];

		if (!isset($doc->oai_sets)) {
			$doc->oai_sets = array();
		}

		if (!in_array($oaiSet, $doc->oai_sets)) {
			$sets = $doc->oai_sets;
			$sets[] = $oaiSet;
			$doc->oai_sets = $sets;
		}

		if (!$doc->save()) {  // No action done if record not dirty
			$err = "[$record->identifier] Document $id could not be saved!";
			Log::error($err);
			$this->output->writeln("<error>$err</error>");
			return false;
		}

		return true;
	}

}
