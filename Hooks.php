<?php
/**
 * TheWikipediaLibrary extension hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */
class TheWikipediaLibraryHooks {

	/**
	 * Add The Wikipedia Library - eligibility events to Echo
	 *
	 * @param $notifications array of Echo notifications
	 * @param $notificationCategories array of Echo notification categories
	 * @param $icons array of icon details
	 * @return bool
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		$notificationCategories['system'] = [
			'priority' => 9
		];

		$notifications['twl-eligible'] = [
			EchoAttributeManager::ATTR_LOCATORS => [
				'EchoUserLocator::locateEventAgent'
			],
			'category' => 'system',
			'group' => 'positive',
			'section' => 'message',
			'presentation-model' => 'TwlEligiblePresentationModel'
		];

		$icons['twl-eligible'] = [
			'path' => 'TheWikipediaLibrary/modules/icons/twl-eligible.svg'
		];

		return true;
	}

	/**
	 * Send a Wikipedia Library notification if the user has reached 6 months and 500 edits.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param Content $content
	 * @param string $summary
	 * @param bool $isMinor
	 * @param bool $watch
	 * @param string $section
	 * @param int $flags
	 * @param Revision $revision
	 * @param Status &$status
	 * @param int $baseRevId
	 * @param int $undidRevId
	 */
	public static function onPageContentSaveComplete(
			WikiPage &$wikiPage, User &$user, Content $content, $summary, $isMinor, $watch, $section,
			&$flags, Revision $revision, Status &$status, $baseRevId, $undidRevId
	) {
		global $wgTwlSendNotifications;
		$title = $wikiPage->getTitle();

		if( $wgTwlSendNotifications ) {
			// Send a notification if the user has at least $wgTwlEditCount edits and their account
			// is at least $wgTwlRegistrationDays days old
			DeferredUpdates::addCallableUpdate( function () use ( $user, $title ) {
				global $wgTwlEditCount, $wgTwlRegistrationDays;

				// If we've already notified this user, don't notify them again
				$notificationMapper = new EchoNotificationMapper();
				$notifications = $notificationMapper->fetchByUser( $user, 1, null, [ 'twl-eligible' ] );
				if ( count( $notifications ) >= 1 ) {
					return;
				}

				$accountAge = wfTimestampNow( TS_UNIX ) - wfTimestamp( TS_UNIX, $user->getRegistration() );
				$minimumAge = $wgTwlRegistrationDays * 24 * 3600;
				if ( $user->getEditCount() >= $wgTwlEditCount && $accountAge >= $minimumAge ) {
					EchoEvent::create( [
						'type' => 'twl-eligible',
						'agent' => $user,
						'extra' => [
							'notifyAgent' => true,
						]
					] );
				}
			} );
		}
	}
}
