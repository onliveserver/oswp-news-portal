<?php
/**
 * Email service implementation.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Emails;

use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Settings\Settings_Repository;
use OSWP\Posts\Emails\Email_Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends transactional emails using configured templates.
 */
class Email_Service {
	/**
	 * Container.
	 *
	 * @var Service_Container
	 */
	protected $container;

	/**
	 * Settings.
	 *
	 * @var Settings_Repository
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param Service_Container $container Container.
	 */
	public function __construct( Service_Container $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
	}

	/**
	 * No hooks required currently.
	 */
	public function register_hooks() {}

	/**
	 * Send an email template to a user ID, WP_User, or raw email.
	 *
	 * @param string              $template_key Template identifier.
	 * @param int|\WP_User|string $recipient    Recipient.
	 * @param array               $context      Placeholder data.
	 *
	 * @return bool
	 */
	public function send( $template_key, $recipient, array $context = [] ) {
		$template = $this->get_template( $template_key );
		if ( ! $template ) {
			return false;
		}

		$user  = null;
		$email = '';

		if ( is_numeric( $recipient ) ) {
			$user  = get_userdata( absint( $recipient ) );
			$email = $user ? $user->user_email : '';
		} elseif ( $recipient instanceof \WP_User ) {
			$user  = $recipient;
			$email = $recipient->user_email;
		} else {
			$email = sanitize_email( $recipient );
		}

		if ( empty( $email ) ) {
			return false;
		}

		$placeholders = $this->prepare_placeholders( $user, $context );
		$subject      = $this->replace_placeholders( $template['subject'], $placeholders );
		$body         = $this->replace_placeholders( $template['body'], $placeholders );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Send the email
		$result = wp_mail( $email, $subject, wpautop( $body ), $headers );

		// Log the email to database (temporary testing system)
		$user_id = $user ? $user->ID : null;
		Email_Log::log_email(
			$email,
			$user_id,
			$template_key,
			$subject,
			$body,
			$result,
			$result ? null : 'Email failed to send'
		);

		return $result;
	}

	/**
	 * Retrieve template from settings.
	 *
	 * @param string $key Template key.
	 *
	 * @return array|null
	 */
	protected function get_template( $key ) {
		$templates = (array) $this->settings->get( 'email_templates', [] );

		if ( ! isset( $templates[ $key ] ) ) {
			return null;
		}

		$template = $templates[ $key ];

		if ( isset( $template['enabled'] ) && ! $template['enabled'] ) {
			return null;
		}

		return $template;
	}

	/**
	 * Prepare placeholder data.
	 *
	 * @param \WP_User|null $user    User object.
	 * @param array          $context Extra context.
	 *
	 * @return array
	 */
	protected function prepare_placeholders( $user, array $context ) {
		$urls = $this->container->get( 'urls' );

		$defaults = [
			'site_name'        => get_bloginfo( 'name' ),
			'site_url'         => home_url( '/' ),
			'first_name'       => $user ? $user->first_name : '',
			'last_name'        => $user ? $user->last_name : '',
			'display_name'     => $user ? $user->display_name : '',
			'user_email'       => $user ? $user->user_email : '',
			'email'            => $user ? $user->user_email : '',
			'date'             => current_time( 'mysql' ),
			'login_url'        => $urls->get_login_url(),
			'dashboard_url'    => $urls->get_dashboard_url(),
			'verification_link'=> '',
			'verification_code'=> '',
			'reset_link'       => '',
			'reset_code'       => '',
			'remaining_posts'  => isset( $context['remaining_posts'] ) ? $context['remaining_posts'] : '',
			'admin_email'      => sanitize_email( $this->settings->get( 'notify_admin_email' ) ),
		];

		return wp_parse_args( $context, $defaults );
	}

	/**
	 * Replace placeholders in text.
	 *
	 * @param string $text         Text.
	 * @param array  $placeholders Placeholder map.
	 *
	 * @return string
	 */
	protected function replace_placeholders( $text, array $placeholders ) {
		foreach ( $placeholders as $key => $value ) {
			$text = str_replace( '{' . $key . '}', $value, $text );
		}

		return $text;
	}
}
