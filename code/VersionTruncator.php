<?php
/**
* Version Truncator for SilverStripe
* ==================================
*
* A SilerStripe extension to automatically delete old published & draft
* page versions from all classes extending the SiteTree upon save.
*
* Please refer to the README.md for confirguration options.
*
* License: MIT-style license http://opensource.org/licenses/MIT
* Authors: Techno Joy development team (www.technojoy.co.nz)
*/

class VersionTruncator {

	private static $version_limit = 10;

	private static $draft_limit = 5;

	// Preserve old versions if they have an different URLSegment / ParentID
	private static $preserve_redirects = false;

	// VACUUM tables/database after deletions
	private static $vacuum_tables = false;

	/**
	* Truncate versions
	*/
	public static function TruncateVersions($RecordID, $version_limit = false, $draft_limit = false) {

		if ($version_limit === false) {
			$version_limit = Config::inst()->get('VersionTruncator', 'version_limit');
		}

		if ($draft_limit === false) {
			$draft_limit = Config::inst()->get('VersionTruncator', 'draft_limit');
		}

		$preserve_redirects = Config::inst()->get('VersionTruncator', 'preserve_redirects');

		$sqlQuery = new SQLQuery();
		$sqlQuery->setFrom('SiteTree_versions');
		$sqlQuery->addWhere('RecordID = ' . $RecordID);
		$sqlQuery->setOrderBy('LastEdited DESC');

		$result = $sqlQuery->execute();

		$publishedCount = 0;

		$draftCount = 0;

		$seen_url_segments = array();

		$versionsToDelete = array();

		foreach ($result as $row) {
			$ID = $row['ID'];
			$RecordID = $row['RecordID'];
			$ClassName = $row['ClassName'];
			$Version = $row['Version'];
			$WasPublished = $row['WasPublished'];
			$URLSegment = $row['ParentID'] . $row['URLSegment'];

			/* Drafts */
			if (!$WasPublished) {
				$draftCount++;
				if ($draftCount > $draft_limit) {
					array_push($versionsToDelete, array(
						'RecordID' => $RecordID,
						'Version' => $Version,
						'ClassName' => $ClassName
					));
				}
			}
			/* Published */
			else {
				$publishedCount++;
				if ($publishedCount > $version_limit ) {
					if (!$preserve_redirects || in_array($URLSegment, $seen_url_segments)) {
						array_push($versionsToDelete, array(
							'RecordID' => $RecordID,
							'Version' => $Version,
							'ClassName' => $ClassName
						));
					}
				}
				/* add page to "seen URLs" if $preserve_redirects */
				if($preserve_redirects && !in_array($URLSegment, $seen_url_segments)) {
					array_push($seen_url_segments, $URLSegment);
				}
			}

		}

		/* If versions to delete, start deleting */
		if (count($versionsToDelete) > 0) {

			/* get tablelist array to make sure $subClass_versions tables exist */
			$tableList = DB::tableList();

			$affected_tables = array();

			foreach ($versionsToDelete as $d) {
				$subClasses = ClassInfo::dataClassesFor($d['ClassName']);
				foreach ($subClasses as $subClass) {
					if (in_array($subClass . '_versions', $tableList)) {
						DB::query('DELETE FROM "' . $subClass . '_versions" WHERE
							"RecordID" = ' . $d['RecordID'] . ' AND "Version" = ' . $d['Version']);
						/* add table to list of accected_tables */
						if (!in_array($subClass . '_versions', $affected_tables)) {
							array_push($affected_tables, $subClass . '_versions');
						}
					}
				}

			}

			VersionTruncator::vacuumTables($affected_tables);
		}

		return count($versionsToDelete);
	}


	/**
	* Optimize the tables that are affected
	* @param Array
	* @return null
	*/
	public static function vacuumTables($raw_tables) {

		$vacuum_tables = Config::inst()->get('VersionTruncator', 'vacuum_tables');

		$tables = array_unique($raw_tables);

		if ($vacuum_tables && count($tables) > 0) {
			global $databaseConfig;

			foreach ($tables as $table) {
				if (preg_match('/mysql/i', $databaseConfig['type'])) {
					DB::query('OPTIMIZE table "' . $table . '"');
				}

				else if (preg_match('/postgres/i', $databaseConfig['type'])) {
					DB::query('VACUUM "' . $table . '"');
				}
			}
			/* Sqlite just optimizes the database, not each table */
			if (preg_match('/sqlite/i', $databaseConfig['type'])) {
				DB::query('VACUUM');
			}
		}

	}

}