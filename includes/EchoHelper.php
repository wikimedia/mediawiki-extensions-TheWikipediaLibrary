<?php
/**
 * TheWikipediaLibrary Echo notification helper
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\TheWikipediaLibrary;

use EchoEvent;
use EchoNotification;
use EchoNotificationMapper;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserIdentity;
use Title;

class EchoHelper {

	/**
	 * Notify the user if they haven't already been notified on this wiki
	 *
	 * @param UserIdentity $user
	 * @param Title $title
	 * @return bool
	 */
	public static function send( UserIdentity $user, Title $title ) {
		$notificationMapper = new EchoNotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $user, 1, null, [ 'twl-eligible' ] );
		$type = 'twl-eligible';
		/** @var EchoNotification $notification */
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
		if ( EchoEvent::create( [
			'type' => $type,
			'title' => $title,
			'agent' => $user,
		] )
		) {
			return true;
		}

		return false;
	}
}
