<?php

/*
 * PHP-Foundation-Core (https://github.com/delight-im/PHP-Foundation-Core)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Foundation;

/** Input validation and filtering */
final class Input {

	/** Validates a value as a single-line string by removing control characters and leading or trailing whitespace */
	const DATA_TYPE_STRING = 1;
	/** Validates a value as an integer number and returns `null` for all invalid values */
	const DATA_TYPE_INT = 2;
	/** Validates a value as a boolean and returns `null` for all invalid values */
	const DATA_TYPE_BOOL = 3;
	/** Validates a value as a floating-point number and returns `null` for all invalid values */
	const DATA_TYPE_FLOAT = 4;
	/** Validates a value as an email address and returns `null` for all invalid values */
	const DATA_TYPE_EMAIL = 5;
	/** Validates a value as a URL and returns `null` for all invalid values */
	const DATA_TYPE_URL = 6;
	/** Validates a value as an IP address (IPv4 or IPv6) and returns `null` for all invalid values */
	const DATA_TYPE_IP = 7;
	/** Validates a value as a multi-line string by removing control characters (except for tabs and newlines) and leading or trailing whitespace */
	const DATA_TYPE_TEXT = 8;

	/**
	 * Returns a validated, filtered and typecast value from the `GET` variables
	 *
	 * @param string $key the name of the element to return from the `GET` variables
	 * @param int|null $type one of the constants from this class starting with `DATA_TYPE_` or `null`
	 * @return mixed|null the validated, filtered and typecast value of the requested data type
	 */
	public function get($key, $type = null) {
		return self::fromSource(\INPUT_GET, $_GET, $key, $type);
	}

	/**
	 * Returns a validated, filtered and typecast value from the `POST` variables
	 *
	 * @param string $key the name of the element to return from the `POST` variables
	 * @param int|null $type one of the constants from this class starting with `DATA_TYPE_` or `null`
	 * @return mixed|null the validated, filtered and typecast value of the requested data type
	 */
	public function post($key, $type = null) {
		return self::fromSource(\INPUT_POST, $_POST, $key, $type);
	}

	/**
	 * Returns a validated, filtered and typecast value from the cookies
	 *
	 * @param string $key the name of the element to return from the cookies
	 * @param int|null $type one of the constants from this class starting with `DATA_TYPE_` or `null`
	 * @return mixed|null the validated, filtered and typecast value of the requested data type
	 */
	public function cookie($key, $type = null) {
		return self::fromSource(\INPUT_COOKIE, $_COOKIE, $key, $type);
	}

	/**
	 * Validates, filters and typecasts the given value
	 *
	 * @param mixed $value the value to process
	 * @param int|null $type one of the constants from this class starting with `DATA_TYPE_` or `null`
	 * @return mixed|null the validated, filtered and typecast value of the requested data type
	 */
	public function value($value, $type = null) {
		if (!isset($value)) {
			return null;
		}

		$type = self::dataTypeOrDefault($type);

		if ($type === self::DATA_TYPE_EMAIL || $type === self::DATA_TYPE_URL || $type === self::DATA_TYPE_IP) {
			$value = \trim($value);
		}

		$filtered = \filter_var($value, self::determineFilterMode($type), self::composeFilterFlags($type));

		return self::applyPostProcessing($filtered, $type);
	}

	/**
	 * Returns a validated, filtered and typecast value from the given source
	 *
	 * @param int $source one of PHP's global constants starting with `INPUT_`
	 * @param array|null $elements the array of input values that belongs to the specified source
	 * @param string $key the name of the element to return from the specified source
	 * @param int|null $type one of the constants from this class starting with `DATA_TYPE_` or `null`
	 * @return mixed|null the validated, filtered and typecast value of the requested data type
	 */
	private static function fromSource($source, $elements, $key, $type = null) {
		if (!isset($elements) || !isset($elements[$key])) {
			return null;
		}

		$type = self::dataTypeOrDefault($type);

		$filtered = \filter_input($source, $key, self::determineFilterMode($type), self::composeFilterFlags($type));

		return self::applyPostProcessing($filtered, $type);
	}

	/**
	 * Returns the supplied data type, if specified, or the default
	 *
	 * @param int|null $type one of the constants from this class starting with `DATA_TYPE_` or `null`
	 * @return int one of the constants from this class starting with `DATA_TYPE_`
	 */
	private static function dataTypeOrDefault($type = null) {
		return $type !== null ? $type : self::DATA_TYPE_STRING;
	}

	/**
	 * Determines the filter mode as understood by {@see \filter_input} and {@see \filter_var}
	 *
	 * @param int $type one of the constants from this class starting with `DATA_TYPE_`
	 * @return int the filter mode
	 */
	private static function determineFilterMode($type) {
		switch ($type) {
			case self::DATA_TYPE_INT:
				return FILTER_VALIDATE_INT;
			case self::DATA_TYPE_BOOL:
				return FILTER_VALIDATE_BOOLEAN;
			case self::DATA_TYPE_FLOAT:
				return FILTER_VALIDATE_FLOAT;
			case self::DATA_TYPE_EMAIL:
				return FILTER_VALIDATE_EMAIL;
			case self::DATA_TYPE_URL:
				return FILTER_VALIDATE_URL;
			case self::DATA_TYPE_IP:
				return FILTER_VALIDATE_IP;
			default:
				return FILTER_DEFAULT;
		}
	}

	/**
	 * Builds a bitmask representing filter options as understood by {@see \filter_input} and {@see \filter_var}
	 *
	 * @param int $type one of the constants from this class starting with `DATA_TYPE_`
	 * @return int the bitmask consisting of individual flags
	 */
	private static function composeFilterFlags($type) {
		$flags = 0;

		$flags |= \FILTER_NULL_ON_FAILURE;
		$flags |= \FILTER_FLAG_STRIP_BACKTICK;
		$flags |= \FILTER_FLAG_NO_ENCODE_QUOTES;

		if ($type !== self::DATA_TYPE_TEXT) {
			$flags |= \FILTER_FLAG_STRIP_LOW;
		}

		$flags |= \FILTER_FLAG_ALLOW_FRACTION;
		$flags |= \FILTER_FLAG_IPV4;
		$flags |= \FILTER_FLAG_IPV6;

		return $flags;
	}

	/**
	 * Applies post-processing to a (filtered) value
	 *
	 * @param mixed|null $value the value to process
	 * @param int $type one of the constants from this class starting with `DATA_TYPE_`
	 * @return mixed|null the processed value
	 */
	private static function applyPostProcessing($value, $type) {
		if ($type === self::DATA_TYPE_STRING || $type === self::DATA_TYPE_TEXT) {
			$value = \trim($value);
		}

		if ($type === self::DATA_TYPE_TEXT) {
			// remove control characters (ASCII characters 0 to 31) except for tabs and newlines
			$value = \preg_replace('/[\x00-\x08\x0b-\x0c\x0e-\x1f]/', '', $value);
		}

		return $value;
	}

}
