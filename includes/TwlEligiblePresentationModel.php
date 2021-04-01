<?php
/*
	When creating a new notification type for Echo, you need to
	create a PresentationModel.
	For more information, see: https://www.mediawiki.org/wiki/Extension:Echo/Creating_a_new_notification_type

 */
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

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		return [];
	}
}
