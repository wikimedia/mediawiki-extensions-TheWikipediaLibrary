<?php

class TwlEligiblePresentationModel extends EchoEventPresentationModel {

	/**
	 * @inheritDoc
	 */
	public function getIconType() {
		return 'twl-eligible';
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaderMessageKey() {
		return 'notification-header-twl-eligiblity';
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyMessage() {
		return $this->msg( 'notification-body-twl-eligiblity' );
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryLink() {
		global $wgTwlUserPrimaryUrl;
		return [
			'url' => $wgTwlUserPrimaryUrl,
			'label' => $this->msg( 'notification-twl-eligiblity-primarylink-text' )->text(),
		];
	}
}
