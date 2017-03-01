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

	/**
	 * Array of primary link details, with possibly-relative URL & label.
	 *
	 * @return array|bool Array of link data, or false for no link:
	 *                    ['url' => (string) url, 'label' => (string) link text (non-escaped)]
	 */
	public function getPrimaryLink() {
		global $wgTwlUserPrimaryUrl;
		return array(
			'url' => $wgTwlUserPrimaryUrl,
			'label' => $this->msg( 'notification-twl-eligiblity-primarylink-text' )->text(),
		);
	}

	public function getSecondaryLinks() {
		return array( $this->getTwlSecondaryLink() );
	}

	private function getTwlSecondaryLink() {
		global $wgTwlUserSecondaryUrl;
		return array(
			'url' => $wgTwlUserSecondaryUrl,
			'label' => $this->msg( 'notification-twl-eligiblity-secondarylink-text' )->text(),
			'description' => '',
			'icon' => 'article',
			'prioritized' => true,
		);
	}
}
