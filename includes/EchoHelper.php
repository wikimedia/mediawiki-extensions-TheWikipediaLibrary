<?php
/**
 * TheWikipediaLibrary Echo notification helper
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\TheWikipediaLibrary;

use MediaWiki\Extension\Notifications\Mapper\NotificationMapper;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Notification\RecipientSet;
use MediaWiki\Notification\Types\WikiNotification;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;

class EchoHelper {

	/**
	 * Notify the user if they haven't already been notified on this wiki
	 *
	 * @param UserIdentity $user
	 * @param Title $title
	 * @return bool
	 */
	public static function send( UserIdentity $user, Title $title ) {
		$type = 'twl-eligible';
		$notificationMapper = new NotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $user, 1, null, [ $type ] );
		foreach ( $notifications as $notification ) {
			if ( $notification->getEvent()->getType() === $type ) {
				LoggerFactory::getInstance( 'TheWikipediaLibrary' )->debug(
					'{user} (id: {id}) has already been notified about The Wikipedia Library',
					[
						'user' => $user->getName(),
						'id' => $user->getId(),
					] );
				// Since we found a local notification return true
				return true;
			}
		}
		MediaWikiServices::getInstance()->getNotificationService()->notify(
			new WikiNotification( $type, $title, $user, [] ),
			new RecipientSet( $user )
		);
		return true;
	}
}
