<?php

class TwlEligiblePresentationModel extends EchoEventPresentationModel {

	public function getIconType() {
		return 'twl-eligible';
	}

	public function getHeaderMessageKey() {
		return 'notification-header-twl-eligiblity';
	}

	public function getBodyMessage() {
		return $this->msg( 'notification-body-twl-eligiblity' );
	}

	public function getPrimaryLink() {
		global $wgTwlUserPrimaryUrl;
		return [
			'url' => $wgTwlUserPrimaryUrl,
			'label' => $this->msg( 'notification-twl-eligiblity-primarylink-text' )->text(),
		];
	}

	public function getSecondaryLinks() {
		return [ $this->getTwlSecondaryLink() ];
	}

	private function getTwlSecondaryLink() {
		global $wgTwlUserSecondaryUrl;
		return [
			'url' => $wgTwlUserSecondaryUrl,
			'label' => $this->msg( 'notification-twl-eligiblity-secondarylink-text' )->text(),
			'description' => '',
			'icon' => 'article',
			'prioritized' => true,
		];
	}
}
