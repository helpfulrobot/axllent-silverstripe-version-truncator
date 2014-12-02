<?php

class VersionTruncatorExt extends SiteTreeExtension {

	public function onAfterWrite() {
		parent::onAfterWrite();
		VersionTruncator::TruncateVersions($this->owner->ID);
	}

	// /* on deletion & / or unpublish */
	// public function onBeforeDelete() {
	// 	parent::onBeforeDelete();
	// 	VersionTruncator::TruncateVersions($this->owner->ID);
	// 	// Config::inst()->update('VersionTruncator', 'version_limit', 1);
	// 	// Config::inst()->update('VersionTruncator', 'draft_limit', 1);
	// 	// Config::inst()->update('VersionTruncator', 'preserve_redirects', false);
	// 	// VersionTruncator::TruncateVersions($this->owner->ID);
	// }

}