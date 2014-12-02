<?php


class VersionTruncatorTask extends BuildTask {

	protected $title = 'Purge old SiteTree versions';

	protected $description = 'Purge SiteTree versions from the database';

	protected $enabled = true;

	public function run($request) {

		$version_limit = Config::inst()->get('VersionTruncator', 'version_limit');

		$draft_limit = Config::inst()->get('VersionTruncator', 'draft_limit');

		echo '<h3>Select a task:</h3>
			<ul>
				<li>
					<p>
						<a href="?cleanup=1" onclick="return Confirm(\'Cleanup\')">Cleanup</a>
						- Keep only the last <strong>' . $version_limit . '</strong> SiteTree versions
						and <strong>' . $draft_limit . '</strong> drafts.
					</p>
				</li>
				<li>
					<p>
						<a href="?reset=1" onclick="return Confirm()">Reset</a>
						- Delete ALL old SiteTree versions, keeping only the latest <strong>published</strong> version.<br />
						This deletes all references to old pages, including previous pages with different URLSegments (redirects).
					</p>
				</li>
			</ul>

			<script type="text/javascript">
				function Confirm(q) {
					if (q == "Cleanup") {
						var question = "Please confirm you wish to clean the database?";
					} else {
						var question = "Please confirm you wish to delete ALL SiteTree versions except for the most recent PUBLISHED versions?";
					}
					if (confirm(question)) {
						return true;
					}
					return false;
				}
			</script>
		';

		$reset = $request->getVar('reset');
		$cleanup = $request->getVar('cleanup');

		if ($reset) {
			$this->purgeAllButCurrent();
		}
		else if ($cleanup) {
			$this->cleanupVersions();
		}

	}

	public function purgeAllButCurrent() {

		$totalPurged = 0;

		$currentPages = SiteTree::get();

		$validRecordIDs = array();

		Config::inst()->update('VersionTruncator', 'preserve_redirects', false);

		/* Purge all but live version */
		foreach ($currentPages as $currentPage) {
			$totalPurged = $totalPurged + VersionTruncator::TruncateVersions($currentPage->ID, 1, 0);
			array_push($validRecordIDs, $currentPage->ID);
		}

		/* Purge all deleted pages */
		$sqlQuery = new SQLQuery();
		$sqlQuery->setFrom('SiteTree_versions');
		$sqlQuery->addWhere('RecordID NOT IN (' . implode($validRecordIDs, ',') . ')');
		$sqlQuery->setGroupBy('RecordID');

		$result = $sqlQuery->execute();

		foreach ($result as $row) {
			$totalPurged = $totalPurged + VersionTruncator::TruncateVersions($row['RecordID'], 0, 0);
		}

		echo '<h2>' . $totalPurged .' records purged from the database</h2>';

	}

	public function cleanupVersions() {

		$totalPurged = 0;

		$currentPages = SiteTree::get();

		foreach ($currentPages as $currentPage) {
			$totalPurged = $totalPurged + VersionTruncator::TruncateVersions($currentPage->ID);
		}

		echo '<h2>' . $totalPurged .' records purged from the database</h2>';

	}


}