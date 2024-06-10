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
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\Notifications\Model\Notification;
use MediaWiki\Logger\LoggerFactory;
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
		$notificationMapper = new NotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $user, 1, null, [ 'twl-eligible' ] );
		$type = 'twl-eligible';
		/** @var Notification $notification */
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
		if ( Event::create( [
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
