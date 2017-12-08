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
	 * Use this hook to remove feed links from the head
	 * of the output.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/AfterBuildFeedLinks
	 *
	 * @param array &$feedLinks Added feed links to be
	 *  outputted. You can remove them using unset(), and
	 *  it's possible to add additional feed links. However,
	 *  you should use the OutputPage::addFeedLink() method,
	 *  instead.
	 */
	public static function onPageContentSaveComplete( WikiPage &$wikiPage, &$user, $content, $summary, $minoredit, $watchthis, $sectionanchor, &$flags, $revision, &$status ) {
		$title = $wikiPage->getTitle();
		global $wgTwlSendNotifications;

			// if the feature flag is set to true, schedule a callable update.
			if( $wgTwlSendNotifications ) {
				// if the user has 500 edits and has been registered for 6 months give access
				// to the-wikipedia-library database
				DeferredUpdates::addCallableUpdate( function () use ( $user, $title ) {
					global $wgTwlEditCount, $wgTwlRegistrationDays, $wgTwlRegistrationHours, $wgTwlRegistrationSeconds;
					$twlRegistrationPeriod = $wgTwlRegistrationDays * $wgTwlRegistrationHours * $wgTwlRegistrationSeconds;

					// notify the user only once about the twl-eligibility
					$notificationMapper = new EchoNotificationMapper();
					$notifications = $notificationMapper->fetchByUser( $user, 1, null, array( 'twl-eligible' ) );
					if ( count( $notifications ) >= 1 ) {
						return;
					}
					$registration_timestamp = wfTimestamp( TS_UNIX, $user->getRegistration() );
					$lastedit_timestamp = wfTimestamp( TS_UNIX, wfTimestampNow( TS_UNIX ) );
					$eligibility_period_timestamp = $lastedit_timestamp - $registration_timestamp;
					if ( $user->getEditCount() >= $wgTwlEditCount && $eligibility_period_timestamp >= $twlRegistrationPeriod ) {
						EchoEvent::create( array(
								'type' => 'twl-eligible',
								'agent' => $user,
								// Wikipedia library eligiblity notification is sent to the agent
								'extra' => array(
									'notifyAgent' => true,
							)
						)
					);
				}
			} );
		}
	}
}
