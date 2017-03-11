<?php

/*
 * PHP-Foundation-Core (https://github.com/delight-im/PHP-Foundation-Core)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Foundation;

use Delight\Auth\Auth;
use Delight\Db\PdoDatabase;
use Delight\Db\PdoDataSource;
use Delight\Ids\Id;
use Delight\Router\Router;

/** Main application class in the framework */
final class App {

	const TEMPLATES_CACHE_SUBFOLDER = '/views/cache';

	/** @var string the root URL from the configuration */
	private $rootUrl;
	/** @var Router */
	private $router;
	/** @var string the path where private files may be stored by the application */
	private $appStoragePath;
	/** @var PdoDatabase the database instance */
	private $db;
	/** @var Input the input helper for validation and filtering */
	private $inputHelper;
	/** @var TemplateManager the template manager */
	private $templateManager;
	/** @var \Swift_Mailer */
	private $mail;
	/** @var Auth the authentication component */
	private $auth;
	/** @var Id the ID encoder and decoder */
	private $ids;
	/** @var Flash the flash message handler */
	private $flash;

	/**
	 * Constructor
	 *
	 * @param string $appStoragePath the path to the directory for private storage in this application
	 * @param string $templatesPath the path to the directory containing the templates
	 * @param string $frameworkStoragePath the path to the directory for this framework's internal storage
	 */
	public function __construct($appStoragePath, $templatesPath, $frameworkStoragePath) {
		// get the root URL from the configuration
		$this->rootUrl = isset($_ENV['APP_PUBLIC_URL']) ? rtrim($_ENV['APP_PUBLIC_URL'], '/') : '';

		// detect the root path for the router from the root URL
		$rootPath = urldecode(parse_url($this->rootUrl, PHP_URL_PATH));

		// get a router instance for the detected root path
		$this->router = new Router($rootPath);

		// remember the path to the storage private to this application
		$this->appStoragePath = $appStoragePath;

		// configure the data source
		$dataSource = new PdoDataSource($_ENV['DB_DRIVER']);
		$dataSource->setHostname($_ENV['DB_HOST']);
		$dataSource->setPort($_ENV['DB_PORT']);
		$dataSource->setDatabaseName($_ENV['DB_NAME']);
		$dataSource->setCharset($_ENV['DB_CHARSET']);
		$dataSource->setUsername($_ENV['DB_USERNAME']);
		$dataSource->setPassword($_ENV['DB_PASSWORD']);

		// set up the database instance
		$this->db = PdoDatabase::fromDataSource($dataSource);

		// create a new input helper
		$this->inputHelper = new Input();

		// initialize the template manager
		$this->templateManager = new TemplateManager($templatesPath, $frameworkStoragePath . self::TEMPLATES_CACHE_SUBFOLDER);

		// add the this object as a global to the template manager
		$this->templateManager->addGlobal('app', $this);

		// create the mailing component lazily
		$this->mail = null;

		// initialize the authentication component
		$this->auth = new Auth($this->db());

		// create the ID encoder and decoder lazily
		$this->ids = null;

		// create a new flash message handler
		$this->flash = new Flash();
	}

	/**
	 * Returns the path to the specified file or folder inside the private storage of this application
	 *
	 * @param string $requestedPath a file or folder inside the private storage of this application, e.g. `/secret/keys.txt` or `/tmp/42.png`
	 * @return string the path to the file or folder inside the private storage
	 */
	public function getStoragePath($requestedPath) {
		$requestedPath = trim($requestedPath);
		$requestedPath = '/' . ltrim($requestedPath, '/');

		return $this->appStoragePath . $requestedPath;
	}

	/**
	 * Returns the database instance
	 *
	 * @return PdoDatabase the database instance
	 */
	public function db() {
		return $this->db;
	}

	/**
	 * Returns the input helper for validation and filtering
	 *
	 * @return Input the input helper
	 */
	public function input() {
		return $this->inputHelper;
	}

	/**
	 * Renders the template with the specified name
	 *
	 * Optionally, you can provide an array of data that the template will receive
	 *
	 * @param string $viewName the name of the template to render
	 * @param array $data (optional) the data to send to the template
	 * @return string the rendered template (usually HTML)
	 */
	public function view($viewName, $data = array()) {
		// render the template and return the evaluated HTML
		return $this->templateManager->render($viewName, $data);
	}

	/**
	 * Returns the template manager that can be used to add globals or filters
	 *
	 * @return TemplateManager the template manager
	 */
	public function getTemplateManager() {
		return $this->templateManager;
	}

	/**
	 * Returns the mailing component
	 *
	 * @return \Swift_Mailer the mailing component
	 */
	public function mail() {
		// if the instance has not been created yet
		if (!isset($this->mail)) {
			// create the instance

			$transport = null;

			if (isset($_ENV['MAIL_TRANSPORT'])) {
				if ($_ENV['MAIL_TRANSPORT'] === 'smtp') {
					$transport = \Swift_SmtpTransport::newInstance();
					$transport->setHost($_ENV['MAIL_HOST']);
					$transport->setPort($_ENV['MAIL_PORT']);
					$transport->setUsername($_ENV['MAIL_USERNAME']);
					$transport->setPassword($_ENV['MAIL_PASSWORD']);

					if (!empty($_ENV['MAIL_TLS'])) {
						$transport->setEncryption('tls');
					}
				}
				elseif ($_ENV['MAIL_TRANSPORT'] === 'sendmail') {
					$sendmailPath = ini_get('sendmail_path');

					if (empty($sendmailPath)) {
						$sendmailPath = '/usr/sbin/sendmail';
					}

					$transport = \Swift_SendmailTransport::newInstance($sendmailPath);
				}
				elseif ($_ENV['MAIL_TRANSPORT'] === 'php') {
					$transport = \Swift_MailTransport::newInstance();
				}
			}

			if (isset($transport)) {
				$this->mail = \Swift_Mailer::newInstance($transport);
			}
		}

		// return the instance
		return $this->mail;
	}

	/**
	 * Returns the authentication component
	 *
	 * @return Auth the authentication component
	 */
	public function auth() {
		return $this->auth;
	}

	/**
	 * Returns the component that can be used to encode and decode IDs conveniently for obfuscation
	 *
	 * @return Id the ID encoder and decoder
	 */
	public function ids() {
		// if the component has not been created yet
		if (!isset($this->ids)) {
			// create the component
			$this->ids = new Id(
				$_ENV['SECURITY_IDS_ALPHABET'],
				$_ENV['SECURITY_IDS_PRIME'],
				$_ENV['SECURITY_IDS_INVERSE'],
				$_ENV['SECURITY_IDS_RANDOM']
			);
		}

		// return the component
		return $this->ids;
	}

	/**
	 * Returns the component that can be used to manage flash messages
	 *
	 * @return Flash the flash message handler
	 */
	public function flash() {
		return $this->flash;
	}

	/**
	 * Sets the HTTP status code for the current response
	 *
	 * @param int $code the HTTP status code, e.g. `200`, `404` or `401`
	 */
	public function setStatus($code) {
		http_response_code($code);
	}

	/**
	 * Sends the specified content to the client and prompts a file download
	 *
	 * @param string $content the content that should be downloaded
	 * @param string $suggestedFilename the suggested name to save the file with (including an extension), e.g. `my-document.txt`
	 * @param string|null $mimeType (optional) the MIME type for the download, e.g. `text/plain`
	 */
	public function downloadContent($content, $suggestedFilename, $mimeType = null) {
		// if no MIME type has been provided explicitly
		if ($mimeType === null) {
			// use a reasonable default value
			$mimeType = 'application/octet-stream';
		}

		// send the appropriate response headers for the download
		self::sendDownloadHeaders($mimeType, $suggestedFilename, strlen($content));

		// send the actual content
		echo $content;
	}

	/**
	 * Sends the specified file to the client and prompts a download
	 *
	 * @param string $filePath the path to the file that should be downloaded
	 * @param string $suggestedFilename the suggested name to save the file with (including an extension), e.g. `my-photo.jpg`
	 * @param string|null $mimeType (optional) the MIME type for the download, e.g. `image/jpeg`
	 */
	public function downloadFile($filePath, $suggestedFilename, $mimeType = null) {
		// if the file exists at the specified path
		if (file_exists($filePath)) {
			// if no MIME type has been provided explicitly
			if ($mimeType === null) {
				// use a reasonable default value
				$mimeType = 'application/octet-stream';
			}

			// send the appropriate response headers for the download
			self::sendDownloadHeaders($mimeType, $suggestedFilename, filesize($filePath));

			// pipe the actual file contents through PHP
			readfile($filePath);
		}
		// if the file could not be found
		else {
			throw new \RuntimeException('File `'.$filePath.'` not found');
		}
	}

	/**
	 * Sends the appropriate response headers for a file download
	 *
	 * @param string $mimeType the MIME type for the file
	 * @param string $suggestedFilename the suggested name to save the file with (including an extension)
	 * @param int $size the size of the download in bytes
	 */
	private static function sendDownloadHeaders($mimeType, $suggestedFilename, $size) {
		header('Content-Type: '.$mimeType, true);
		header('Content-Disposition: attachment; filename="'.$suggestedFilename.'"', true);
		header('Content-Length: '.$size, true);
		header('Accept-Ranges: none', true);
		header('Cache-Control: no-cache', true);
		header('Connection: close', true);
	}

	/**
	 * Detects whether the current request has been sent over plain HTTP (as opposed to secure HTTPS)
	 *
	 * @return bool whether the current request is a HTTP request
	 */
	public function isHttp() {
		return empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off';
	}

	/**
	 * Detects whether the current request has been sent over secure HTTPS (as opposed to plain HTTP)
	 *
	 * @return bool whether the current request is a HTTP request
	 */
	public function isHttps() {
		return !$this->isHttp();
	}

	/**
	 * Alias of `isHttps`
	 *
	 * @return bool
	 */
	public function isSecure() {
		return $this->isHttps();
	}

	/**
	 * Returns the protocol from the current request
	 *
	 * @param bool $lowerCase whether to return the protocol name in lower case or upper case
	 * @return string the protocol name
	 */
	public function getProtocol($lowerCase = false) {
		if ($this->isHttp()) {
			return $lowerCase ? 'http' : 'HTTP';
		}
		else {
			return $lowerCase ? 'https' : 'HTTPS';
		}
	}

	/**
	 * Alias of `getProtocol`
	 *
	 * @param bool $lowerCase
	 * @return string
	 */
	public function getSchema($lowerCase = false) {
		return $this->getProtocol($lowerCase);
	}

	/**
	 * Returns the hostname of the server
	 *
	 * @return string the hostname
	 */
	public function getHost() {
		return $_SERVER['SERVER_NAME'];
	}

	/**
	 * Returns the port of the server used for the current request
	 *
	 * @return int the port number
	 */
	public function getPort() {
		return (int) $_SERVER['SERVER_PORT'];
	}

	/**
	 * Returns the public URL for the specified path below the root of this application
	 *
	 * @param string $requestedPath the path below the root of this application, e.g. `/users`
	 * @return string the public URL for the requested path
	 */
	public function url($requestedPath) {
		$requestedPath = trim($requestedPath);
		$requestedPath = '/' . ltrim($requestedPath, '/');

		return $this->rootUrl . $requestedPath;
	}

	/**
	 * Returns the route of the current request
	 *
	 * @return string the route
	 */
	public function currentRoute() {
		return substr($this->router->getRoute(), strlen($this->router->getRootPath()));
	}

	/**
	 * Returns the URL of the current request
	 *
	 * @return string the URL
	 */
	public function currentUrl() {
		return $this->rootUrl . $this->currentRoute();
	}

	/**
	 * Returns the query string from the current request
	 *
	 * @return string the query string
	 */
	public function getQueryString() {
		return $_SERVER['QUERY_STRING'];
	}

	/**
	 * Returns the request method from the current request
	 *
	 * @return string the request method
	 */
	public function getRequestMethod() {
		return $this->router->getRequestMethod();
	}

	/**
	 * Returns the client's IP address
	 *
	 * @return string the IP address
	 */
	public function getClientIp() {
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * Redirects to the specified path below the root of this application
	 *
	 * @param string $targetPath the path below the root of this application, e.g. `/users`
	 */
	public function redirect($targetPath) {
		// send the appropriate HTTP header causing the redirect
		header('Location: '.$this->url($targetPath));
		// end execution because that HTTP header is all we need
		exit;
	}

	/**
	 * Adds a new route for the HTTP request method `GET` and executes the specified callback if the route matches
	 *
	 * @param string $route the route to match, e.g. `/users/jane`
	 * @param callable|null $callback (optional) the callback to execute, e.g. an anonymous function
	 */
	public function get($route, $callback = null) {
		$this->router->get($route, $callback, [ $this ]) && exit;
	}

	/**
	 * Adds a new route for the HTTP request method `POST` and executes the specified callback if the route matches
	 *
	 * @param string $route the route to match, e.g. `/users/jane`
	 * @param callable|null $callback (optional) the callback to execute, e.g. an anonymous function
	 */
	public function post($route, $callback = null) {
		$this->router->post($route, $callback, [ $this ]) && exit;
	}

	/**
	 * Adds a new route for the HTTP request method `PUT` and executes the specified callback if the route matches
	 *
	 * @param string $route the route to match, e.g. `/users/jane`
	 * @param callable|null $callback (optional) the callback to execute, e.g. an anonymous function
	 */
	public function put($route, $callback = null) {
		$this->router->put($route, $callback, [ $this ]) && exit;
	}

	/**
	 * Adds a new route for the HTTP request method `PATCH` and executes the specified callback if the route matches
	 *
	 * @param string $route the route to match, e.g. `/users/jane`
	 * @param callable|null $callback (optional) the callback to execute, e.g. an anonymous function
	 */
	public function patch($route, $callback = null) {
		$this->router->patch($route, $callback, [ $this ]) && exit;
	}

	/**
	 * Adds a new route for the HTTP request method `DELETE` and executes the specified callback if the route matches
	 *
	 * @param string $route the route to match, e.g. `/users/jane`
	 * @param callable|null $callback (optional) the callback to execute, e.g. an anonymous function
	 */
	public function delete($route, $callback = null) {
		$this->router->delete($route, $callback, [ $this ]) && exit;
	}

	/**
	 * Adds a new route for the HTTP request method `HEAD` and executes the specified callback if the route matches
	 *
	 * @param string $route the route to match, e.g. `/users/jane`
	 * @param callable|null $callback (optional) the callback to execute, e.g. an anonymous function
	 */
	public function head($route, $callback = null) {
		$this->router->head($route, $callback, [ $this ]) && exit;
	}

	/**
	 * Adds a new route for the HTTP request method `TRACE` and executes the specified callback if the route matches
	 *
	 * @param string $route the route to match, e.g. `/users/jane`
	 * @param callable|null $callback (optional) the callback to execute, e.g. an anonymous function
	 */
	public function trace($route, $callback = null) {
		$this->router->trace($route, $callback, [ $this ]) && exit;
	}

	/**
	 * Adds a new route for the HTTP request method `OPTIONS` and executes the specified callback if the route matches
	 *
	 * @param string $route the route to match, e.g. `/users/jane`
	 * @param callable|null $callback (optional) the callback to execute, e.g. an anonymous function
	 */
	public function options($route, $callback = null) {
		$this->router->options($route, $callback, [ $this ]) && exit;
	}

	/**
	 * Adds a new route for the HTTP request method `CONNECT` and executes the specified callback if the route matches
	 *
	 * @param string $route the route to match, e.g. `/users/jane`
	 * @param callable|null $callback (optional) the callback to execute, e.g. an anonymous function
	 */
	public function connect($route, $callback = null) {
		$this->router->connect($route, $callback, [ $this ]) && exit;
	}

	/**
	 * Adds a new route for all of the specified HTTP request methods and executes the specified callback if the route matches
	 *
	 * @param string[] $requestMethods the request methods, one of which to match
	 * @param string $route the route to match, e.g. `/users/jane`
	 * @param callable|null $callback (optional) the callback to execute, e.g. an anonymous function
	 */
	public function any(array $requestMethods, $route, $callback = null) {
		$this->router->any($requestMethods, $route, $callback, [ $this ]) && exit;
	}

	/**
	 * Sets the content type and character encoding for the HTTP response
	 *
	 * @param string $contentType the content type or MIME type (or `text`, `html`, `json` or `xml` as shorthands)
	 * @param string|null $charset (optional) the character encoding (or `auto` for the default)
	 */
	public function setContentType($contentType, $charset = null) {
		// process shorthands for the content type or MIME type information
		switch ($contentType) {
			case 'text':
				$contentType = 'text/plain';
				$charset = isset($charset) ? $charset : 'auto';
				break;
			case 'html':
				$contentType = 'text/html';
				$charset = isset($charset) ? $charset : 'auto';
				break;
			case 'json':
				$contentType = 'application/json';
				break;
			case 'xml':
				$contentType = 'text/xml';
				$charset = isset($charset) ? $charset : 'auto';
				break;
		}

		// if the character encoding is to be determined automatically
		if ($charset === 'auto') {
			// if the internal character encoding has been configured
			if (isset($_ENV['APP_CHARSET'])) {
				// use the encoding from the configuration
				$charset = strtolower($_ENV['APP_CHARSET']);
			}
			// if there is no encoding in the configuration
			else {
				// use UTF-8 as the default
				$charset = 'utf-8';
			}
		}

		$headerLine = 'Content-type: ' . $contentType;

		if ($charset !== null) {
			$headerLine .= '; charset=' . $charset;
		}

		\header($headerLine);
	}

}
