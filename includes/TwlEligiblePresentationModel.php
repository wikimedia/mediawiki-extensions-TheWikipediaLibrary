<?php
namespace MediaWiki\Extension\TheWikipediaLibrary;

use EchoEventPresentationModel;
use MediaWiki\MediaWikiServices;
use WikiMap;

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
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'TheWikipediaLibrary' );
		return [
			'url' => $config->get( 'TwlUserPrimaryUrl' ),
			'label' => null,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		return [ $this->getSecondaryLinkWithMarkAsRead() ];
	}

	/**
	 * Like getPrimaryLinkWithMarkAsRead(), but for a secondary link.
	 * It alters the URL to add ?markasread=XYZ. When this link is followed,
	 * the notification is marked as read.
	 *
	 * If the notification is a bundle, the notification IDs are added to the parameter value
	 * separated by a "|". If cross-wiki notifications are enabled, a markasreadwiki parameter is
	 * added.
	 *
	 * @return array
	 */
	final public function getSecondaryLinkWithMarkAsRead() {
		global $wgEchoCrossWikiNotifications;
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'TheWikipediaLibrary' );
		$secondaryLink = [
			'url' => $config->get( 'TwlUserSecondaryUrl' ),
			'label' => $this->msg( 'notification-twl-eligiblity-secondarylink-text' )->text(),
			'icon' => 'article',
		];
		'@phan-var array $secondaryLink';
		$eventIds = [ $this->event->getId() ];
		if ( $this->getBundledIds() ) {
			$eventIds = array_merge( $eventIds, $this->getBundledIds() );
		}

		$queryParams = [ 'markasread' => implode( '|', $eventIds ) ];
		if ( $wgEchoCrossWikiNotifications ) {
			$queryParams['markasreadwiki'] = WikiMap::getCurrentWikiId();
		}

		$secondaryLink['url'] = wfAppendQuery( $secondaryLink['url'], $queryParams );
		return $secondaryLink;
	}
}
