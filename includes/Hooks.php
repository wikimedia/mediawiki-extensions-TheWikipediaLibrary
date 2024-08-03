<?php

namespace MediaWiki\Extension\TheWikipediaLibrary;

use ExtensionRegistry;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use WikiPage;

/**
 * TheWikipediaLibrary extension hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */
class Hooks implements
	PageSaveCompleteHook,
	GetPreferencesHook
{
	private Config $config;
	private PermissionManager $permissionManager;
	private UserFactory $userFactory;

	public function __construct(
		ConfigFactory $configFactory,
		PermissionManager $permissionManager,
		UserFactory $userFactory
	) {
		$this->config = $configFactory->makeConfig( 'TheWikipediaLibrary' );
		$this->permissionManager = $permissionManager;
		$this->userFactory = $userFactory;
	}

	/**
	 * Add API preference tracking whether the user has been notified already.
	 * @param User $user
	 * @param array &$preferences
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences['twl-notified'] = [
			'type' => 'api'
		];
	}

	/**
	 * Send a Wikipedia Library notification if the user has reached the required age and editcount.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 *
	 * @note parameters include classes not available before 1.35, so for those typehints
	 * are not used. The variable name reflects the class
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $userIdentity
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 */
	public function onPageSaveComplete(
		$wikiPage,
		$userIdentity,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	) {
		// Need CentralAuth extension.
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			return;
		}
		if ( $this->config->get( 'TwlSendNotifications' ) ) {
			$title = $wikiPage->getTitle();
			$this->maybeSendNotification( $userIdentity, $title );
		}
	}

	/**
	 * Decide whether to notify the user based on central auth eligibility and global preference state
	 *
	 * @param UserIdentity $userIdentity
	 * @param Title $title
	 */
	private function maybeSendNotification( UserIdentity $userIdentity, Title $title ) {
		// Wrap in a POSTSEND deferred update to avoid blocking the HTTP response
		DeferredUpdates::addCallableUpdate( function () use ( $userIdentity, $title ) {
			$user = $this->userFactory->newFromUserIdentity( $userIdentity );
			// Only proceed if we're dealing with an authenticated non-system account
			if ( $user->isAnon() || $user->isTemp() || $user->isSystemUser() ) {
				return;
			}
			// Only proceed if we're dealing with a non-bot account
			if ( $this->permissionManager->userHasRight( $user, 'bot' ) ) {
				return;
			}
			// Only proceed if we're dealing with an SUL account
			$centralAuthUser = CentralAuthUser::getInstance( $user );
			if ( !$centralAuthUser->isAttached() ) {
				return;
			}
			// Only proceed if we haven't already notified this user
			$twlNotifiedPref = PreferenceHelper::getGlobalPreference( $user, 'twl-notified' );
			if ( $twlNotifiedPref === 'yes' ) {
				return;
			}
			// Only proceed if we're dealing with an eligible account
			if ( !$this->isTwlEligible( $centralAuthUser ) ) {
				return;
			}
			// Set the twl-notified preference to 'no' if we haven't notified this user
			// We've added this extra step to ensure that global preferences may be modified
			// to avoid multiple notifications in case the preference isn't saved before the next edit
			if ( $twlNotifiedPref === null ) {
				PreferenceHelper::setGlobalPreference( $user, 'twl-notified', 'no' );
				return;
			}
			// Notify the user if:
			// - they haven't been notified yet
			// - we can successfully set the preference
			if (
				$twlNotifiedPref === 'no'
				&& PreferenceHelper::setGlobalPreference( $user, 'twl-notified', 'yes' )
			) {
				EchoHelper::send( $user, $title );
			}
		} );
	}

	/**
	 * Determine Twl Eligibility
	 *
	 * @param CentralAuthUser $centralAuthUser
	 * @return bool
	 *
	 */
	public function isTwlEligible( CentralAuthUser $centralAuthUser ) {
		$twlRegistrationDays = $this->config->get( 'TwlRegistrationDays' );
		$minimumAge = $twlRegistrationDays * 24 * 3600;
		$accountAge = (int)wfTimestamp( TS_UNIX ) -
			(int)wfTimestamp( TS_UNIX, $centralAuthUser->getRegistration() );
		if ( $accountAge < $minimumAge ) {
			return false;
		}

		$twlEditCount = $this->config->get( 'TwlEditCount' );
		$globalEditCount = $centralAuthUser->getGlobalEditCount();

		return $globalEditCount >= $twlEditCount;
	}
}
