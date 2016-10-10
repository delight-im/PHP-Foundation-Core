<?php

/*
 * PHP-Foundation-Core (https://github.com/delight-im/PHP-Foundation-Core)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Foundation;

/** Writing and retrieving flash messages */
final class Flash {

	/** The name of the session property where all data for this instance is stored */
	const SESSION_KEY_FLASH = 'im.delight.php.foundation.flash';
	/** The key for messages indicating success as stored within the data of this instance */
	const FLASH_KEY_SUCCESS = 'success';
	/** The key for messages indicating neutral information as stored within the data of this instance */
	const FLASH_KEY_INFO = 'info';
	/** The key for messages indicating a warning as stored within the data of this instance */
	const FLASH_KEY_WARNING = 'warning';
	/** The key for messages indicating danger as stored within the data of this instance */
	const FLASH_KEY_DANGER = 'danger';

	/**
	 * Writes a flash message indicating success
	 *
	 * The message will live until retrieved again, which is usually done on the very next request
	 *
	 * @param string $message the message to store
	 */
	public function success($message) {
		$this->set(self::FLASH_KEY_SUCCESS, $message);
	}

	/**
	 * Writes a flash message indicating neutral information
	 *
	 * The message will live until retrieved again, which is usually done on the very next request
	 *
	 * @param string $message the message to store
	 */
	public function info($message) {
		$this->set(self::FLASH_KEY_INFO, $message);
	}

	/**
	 * Writes a flash message indicating a warning
	 *
	 * The message will live until retrieved again, which is usually done on the very next request
	 *
	 * @param string $message the message to store
	 */
	public function warning($message) {
		$this->set(self::FLASH_KEY_WARNING, $message);
	}

	/**
	 * Writes a flash message indicating danger
	 *
	 * The message will live until retrieved again, which is usually done on the very next request
	 *
	 * @param string $message the message to store
	 */
	public function danger($message) {
		$this->set(self::FLASH_KEY_DANGER, $message);
	}

	/**
	 * Returns whether any message is available that could be retrieved
	 *
	 * @return bool
	 */
	public function hasAny() {
		return isset($_SESSION[self::SESSION_KEY_FLASH]) && is_array($_SESSION[self::SESSION_KEY_FLASH]) && count($_SESSION[self::SESSION_KEY_FLASH]) > 0;
	}

	/**
	 * Returns whether a message indicating success is available
	 *
	 * @return bool
	 */
	public function hasSuccess() {
		return $this->has(self::FLASH_KEY_SUCCESS);
	}

	/**
	 * Returns whether a message indicating neutral information is available
	 *
	 * @return bool
	 */
	public function hasInfo() {
		return $this->has(self::FLASH_KEY_INFO);
	}

	/**
	 * Returns whether a message indicating a warning is available
	 *
	 * @return bool
	 */
	public function hasWarning() {
		return $this->has(self::FLASH_KEY_WARNING);
	}

	/**
	 * Returns whether a message indicating danger is available
	 *
	 * @return bool
	 */
	public function hasDanger() {
		return $this->has(self::FLASH_KEY_DANGER);
	}

	/**
	 * Retrieves and returns all messages available right now
	 *
	 * @return string[] an array containing the messages indexed by their type
	 */
	public function getAll() {
		if ($this->hasAny()) {
			$messages = $_SESSION[self::SESSION_KEY_FLASH];

			$_SESSION[self::SESSION_KEY_FLASH] = [];

			return $messages;
		}
		else {
			return [];
		}
	}

	/**
	 * Returns the message indicating success (if any)
	 *
	 * @return string|null the message, if available, or `null`
	 */
	public function getSuccess() {
		return $this->get(self::FLASH_KEY_SUCCESS);
	}

	/**
	 * Returns the message indicating neutral information (if any)
	 *
	 * @return string|null the message, if available, or `null`
	 */
	public function getInfo() {
		return $this->get(self::FLASH_KEY_INFO);
	}

	/**
	 * Returns the message indicating a warning (if any)
	 *
	 * @return string|null the message, if available, or `null`
	 */
	public function getWarning() {
		return $this->get(self::FLASH_KEY_WARNING);
	}

	/**
	 * Returns the message indicating danger (if any)
	 *
	 * @return string|null the message, if available, or `null`
	 */
	public function getDanger() {
		return $this->get(self::FLASH_KEY_DANGER);
	}

	/**
	 * Writes a flash message of the specified type
	 *
	 * The message will live until retrieved again, which is usually done on the very next request
	 *
	 * @param string $key the type of the message (usually one of the constants in this class starting with `FLASH_KEY_`)
	 * @param string $value the message to store
	 */
	private function set($key, $value) {
		if (!isset($_SESSION[self::SESSION_KEY_FLASH])) {
			$_SESSION[self::SESSION_KEY_FLASH] = [];
		}

		$_SESSION[self::SESSION_KEY_FLASH][$key] = (string) $value;
	}

	/**
	 * Returns whether a message of the specified type is available
	 *
	 * @param string $key the type of the message (usually one of the constants in this class starting with `FLASH_KEY_`)
	 * @return bool
	 */
	private function has($key) {
		return isset($_SESSION[self::SESSION_KEY_FLASH]) && isset($_SESSION[self::SESSION_KEY_FLASH][$key]);
	}

	/**
	 * Returns the message of the specified type (if any)
	 *
	 * @param string $key the type of the message (usually one of the constants in this class starting with `FLASH_KEY_`)
	 * @return string|null the message, if available, or `null`
	 */
	private function get($key) {
		if (isset($_SESSION[self::SESSION_KEY_FLASH]) && isset($_SESSION[self::SESSION_KEY_FLASH][$key])) {
			$message = $_SESSION[self::SESSION_KEY_FLASH][$key];

			unset($_SESSION[self::SESSION_KEY_FLASH][$key]);

			return $message;
		}
		else {
			return null;
		}
	}

}
