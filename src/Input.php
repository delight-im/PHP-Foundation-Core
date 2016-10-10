<?php

/*
 * PHP-Foundation-Core (https://github.com/delight-im/PHP-Foundation-Core)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Foundation;

/** Input validation and filtering */
final class Input {

	/** Validates a value as a string by removing control characters and whitespace */
	const DATA_TYPE_STRING = FILTER_DEFAULT;
	/** Validates a value as an integer number and returns `null` for all invalid values */
	const DATA_TYPE_INT = FILTER_VALIDATE_INT;
	/** Validates a value as a boolean and returns `null` for all invalid values */
	const DATA_TYPE_BOOL = FILTER_VALIDATE_BOOLEAN;
	/** Validates a value as a floating-point number and returns `null` for all invalid values */
	const DATA_TYPE_FLOAT = FILTER_VALIDATE_FLOAT;
	/** Validates a value as an email address and returns `null` for all invalid values */
	const DATA_TYPE_EMAIL = FILTER_VALIDATE_EMAIL;
	/** Validates a value as a URL and returns `null` for all invalid values */
	const DATA_TYPE_URL = FILTER_VALIDATE_URL;
	/** Validates a value as an IP address (IPv4 or IPv6) and returns `null` for all invalid values */
	const DATA_TYPE_IP = FILTER_VALIDATE_IP;
	/** Additional flags that determine the behavior of the validation and filtering process */
	const FLAGS = FILTER_NULL_ON_FAILURE | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_BACKTICK | FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;

	/**
	 * Returns a validated, filtered and typecast value from the `GET` variables
	 *
	 * @param string $key the key or name of the variable to return
	 * @param int $type the data type using one of the `DATA_TYPE_*` constants
	 * @return mixed the validated, filtered and typecast value where the data type depends on the second parameter
	 */
	public function get($key, $type = self::DATA_TYPE_STRING) {
		if (!isset($_GET[$key])) {
			return null;
		}

		$filtered = filter_input(INPUT_GET, $key, $type, self::FLAGS);

		if ($type === self::DATA_TYPE_STRING) {
			$filtered = trim($filtered);
		}

		return $filtered;
	}

	/**
	 * Returns a validated, filtered and typecast value from the `POST` variables
	 *
	 * @param string $key the key or name of the variable to return
	 * @param int $type the data type using one of the `DATA_TYPE_*` constants
	 * @return mixed the validated, filtered and typecast value where the data type depends on the second parameter
	 */
	public function post($key, $type = self::DATA_TYPE_STRING) {
		if (!isset($_POST[$key])) {
			return null;
		}

		$filtered = filter_input(INPUT_POST, $key, $type, self::FLAGS);

		if ($type === self::DATA_TYPE_STRING) {
			$filtered = trim($filtered);
		}

		return $filtered;
	}

	/**
	 * Returns a validated, filtered and typecast value from the cookies
	 *
	 * @param string $key the key or name of the variable to return
	 * @param int $type the data type using one of the `DATA_TYPE_*` constants
	 * @return mixed the validated, filtered and typecast value where the data type depends on the second parameter
	 */
	public function cookie($key, $type = self::DATA_TYPE_STRING) {
		if (!isset($_COOKIE[$key])) {
			return null;
		}

		$filtered = filter_input(INPUT_COOKIE, $key, $type, self::FLAGS);

		if ($type === self::DATA_TYPE_STRING) {
			$filtered = trim($filtered);
		}

		return $filtered;
	}

	/**
	 * Returns a validated, filtered and typecast value from the given variable
	 *
	 * @param mixed $variable the variable to validate
	 * @param int $type the data type using one of the `DATA_TYPE_*` constants
	 * @return mixed the validated, filtered and typecast value where the data type depends on the second parameter
	 */
	public function value($variable, $type = self::DATA_TYPE_STRING) {
		if (!isset($variable)) {
			return null;
		}

		if ($type === self::DATA_TYPE_EMAIL || $type === self::DATA_TYPE_URL || $type === self::DATA_TYPE_IP) {
			$variable = trim($variable);
		}

		$filtered = filter_var($variable, $type, self::FLAGS);

		if ($type === self::DATA_TYPE_STRING) {
			$filtered = trim($filtered);
		}

		return $filtered;
	}

}
