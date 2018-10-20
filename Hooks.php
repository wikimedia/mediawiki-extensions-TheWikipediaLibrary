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

		// if the feature flag is set to true, schedule a callable update.
		if( $wgTwlSendNotifications ) {
			// if the user has 500 edits and has been registered for 6 months give access
			// to the-wikipedia-library database
			DeferredUpdates::addCallableUpdate( function () use ( $user, $title ) {
				global $wgTwlEditCount, $wgTwlRegistrationDays, $wgTwlRegistrationHours, $wgTwlRegistrationSeconds;
				$twlRegistrationPeriod = $wgTwlRegistrationDays * $wgTwlRegistrationHours * $wgTwlRegistrationSeconds;

				// notify the user only once about the twl-eligibility
				$notificationMapper = new EchoNotificationMapper();
				$notifications = $notificationMapper->fetchByUser( $user, 1, null, [ 'twl-eligible' ] );
				if ( count( $notifications ) >= 1 ) {
					return;
				}
				$registration_timestamp = wfTimestamp( TS_UNIX, $user->getRegistration() );
				$lastedit_timestamp = wfTimestamp( TS_UNIX, wfTimestampNow( TS_UNIX ) );
				$eligibility_period_timestamp = $lastedit_timestamp - $registration_timestamp;
				if ( $user->getEditCount() >= $wgTwlEditCount && $eligibility_period_timestamp >= $twlRegistrationPeriod ) {
					EchoEvent::create( [
						'type' => 'twl-eligible',
						'agent' => $user,
						// Wikipedia library eligiblity notification is sent to the agent
						'extra' => [
							'notifyAgent' => true,
						]
					] );
				}
			} );
		}
	}
}
