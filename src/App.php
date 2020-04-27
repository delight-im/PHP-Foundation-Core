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
use Delight\FileUpload\FileUpload;
use Delight\Foundation\Throwable\NoSupportedLocaleError;
use Delight\I18n\I18n;
use Delight\Ids\Id;
use Delight\Router\Router;

/** Main application class in the framework */
class App {

	const TEMPLATES_CACHE_SUBFOLDER = '/views/cache';

	/** @var string the root URL as whitelisted in the configuration */
	private $canonicalRootUrl;
	/** @var string|null the host as whitelisted in the configuration */
	private $canonicalHost;
	/** @var Router */
	private $router;
	/** @var string the path to the directory for private storage in this application */
	private $appStoragePath;
	/** @var string the path to the directory containing the templates */
	private $templatesPath;
	/** @var string the path to the directory for this framework's internal storage */
	private $frameworkStoragePath;
	/** @var PdoDatabase the database instance */
	private $db;
	/** @var Input|null the input helper for validation and filtering */
	private $inputHelper;
	/** @var TemplateManager|null the template manager */
	private $templateManager;
	/** @var \Swift_Mailer|null */
	private $mail;
	/** @var Auth the authentication component */
	private $auth;
	/** @var I18n|null the internationalization component */
	private $i18n;
	/** @var Id|null the ID encoder and decoder */
	private $ids;
	/** @var Flash|null the flash message handler */
	private $flash;

	/**
	 * @param string $appStoragePath the path to the directory for private storage in this application
	 * @param string $templatesPath the path to the directory containing the templates
	 * @param string $frameworkStoragePath the path to the directory for this framework's internal storage
	 */
	public function __construct($appStoragePath, $templatesPath, $frameworkStoragePath) {
		// get the root URL as whitelisted in the configuration
		$this->canonicalRootUrl = self::determineCanonicalRootUrl();

		if (!empty($this->canonicalRootUrl)) {
			$this->canonicalHost = \parse_url($this->canonicalRootUrl, \PHP_URL_HOST);
		}

		// detect the root path for the router from the root URL
		$canonicalRootPath = urldecode(parse_url($this->canonicalRootUrl, PHP_URL_PATH));

		// get a router instance for the detected root path
		$this->router = new Router($canonicalRootPath);

		// remember some relevant paths on the file system
		$this->appStoragePath = $appStoragePath;
		$this->templatesPath = $templatesPath;
		$this->frameworkStoragePath = $frameworkStoragePath;

		// configure the data source
		$dataSource = new PdoDataSource(!empty($_ENV['DB_DRIVER']) ? $_ENV['DB_DRIVER'] : '');
		$dataSource->setHostname(!empty($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : null);
		$dataSource->setPort(!empty($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : null);
		$dataSource->setFilePath(!empty($_ENV['DB_FILE_PATH']) ? $_ENV['DB_FILE_PATH'] : null);
		$dataSource->setMemory(isset($_ENV['DB_IN_MEMORY']) && \is_numeric($_ENV['DB_IN_MEMORY']) ? $_ENV['DB_IN_MEMORY'] : null);
		$dataSource->setDatabaseName(!empty($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : null);
		$dataSource->setCharset(!empty($_ENV['DB_CHARSET']) ? $_ENV['DB_CHARSET'] : null);
		$dataSource->setUsername(!empty($_ENV['DB_USERNAME']) ? $_ENV['DB_USERNAME'] : null);
		$dataSource->setPassword(!empty($_ENV['DB_PASSWORD']) ? $_ENV['DB_PASSWORD'] : null);

		// set up the database instance
		$this->db = PdoDatabase::fromDataSource($dataSource);

		// create the input helper lazily
		$this->inputHelper = null;

		// create the template manager lazily
		$this->templateManager = null;

		// create the mailing component lazily
		$this->mail = null;

		// initialize the authentication component
		$this->auth = new Auth(
			$this->db(),
			$this->getClientIp(),
			!empty($_ENV['DB_PREFIX']) ? $_ENV['DB_PREFIX'] : null
		);

		// initialize the internationalization component
		if (!empty($_ENV['I18N_SUPPORTED_LOCALES'])) {
			$this->i18n = new I18n(
				\preg_split('/\s*,\s*/', $_ENV['I18N_SUPPORTED_LOCALES'], -1, \PREG_SPLIT_NO_EMPTY)
			);
			$this->i18n->setSessionField(!empty($_ENV['I18N_SESSION_FIELD']) ? $_ENV['I18N_SESSION_FIELD'] : null);
			$this->i18n->setCookieName(!empty($_ENV['I18N_COOKIE_NAME']) ? $_ENV['I18N_COOKIE_NAME'] : null);
			$this->i18n->setCookieLifetime(!empty($_ENV['I18N_COOKIE_LIFETIME']) ? $_ENV['I18N_COOKIE_LIFETIME'] : null);
			$this->i18n->setLocaleAutomatically();
		}

		// create the ID encoder and decoder lazily
		$this->ids = null;

		// create the flash message handler lazily
		$this->flash = null;
	}

	/**
	 * Returns the path to the specified file or folder inside the private storage of this application
	 *
	 * @param string $requestedPath a file or folder inside the private storage of this application, e.g. `/secret/keys.txt` or `/tmp/42.png`
	 * @return string
	 */
	public function getStoragePath($requestedPath) {
		$requestedPath = trim($requestedPath);
		$requestedPath = '/' . ltrim($requestedPath, '/');

		return $this->appStoragePath . $requestedPath;
	}

	/**
	 * Returns the database instance
	 *
	 * @return PdoDatabase
	 */
	public function db() {
		return $this->db;
	}

	/**
	 * Returns the input helper for validation and filtering
	 *
	 * @return Input
	 */
	public function input() {
		// if the component has not been created yet
		if (!isset($this->inputHelper)) {
			// create the component
			$this->inputHelper = new Input();
		}

		// return the component
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
		return $this->getTemplateManager()->render($viewName, $data);
	}

	/**
	 * Returns the template manager that can be used to add globals or filters
	 *
	 * @return TemplateManager
	 */
	public function getTemplateManager() {
		// if the component has not been created yet
		if (!isset($this->templateManager)) {
			// create the component
			$this->templateManager = new TemplateManager($this->templatesPath, $this->frameworkStoragePath . self::TEMPLATES_CACHE_SUBFOLDER);

			// add the current instance as a global to the template manager
			$this->templateManager->addGlobal('app', $this);

			// add functions for internationalization as globals to the template manager
			if (isset($this->i18n)) {
				$i18nMarkupFunctions = [ '_', '_f', '_fe', '_p', '_pf', '_pfe', '_c', '_m' ];

				foreach ($i18nMarkupFunctions as $i18nMarkupFunction) {
					if (\function_exists($i18nMarkupFunction)) {
						$this->templateManager->addFunction($i18nMarkupFunction, $i18nMarkupFunction);
					}
				}
			}
		}

		// return the component
		return $this->templateManager;
	}

	/**
	 * Returns the mailing component
	 *
	 * @return \Swift_Mailer
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
	 * @return Auth
	 */
	public function auth() {
		return $this->auth;
	}

	/**
	 * Returns the internationalization component
	 *
	 * @return I18n
	 */
	public function i18n() {
		if (!isset($this->i18n)) {
			throw new NoSupportedLocaleError();
		}

		return $this->i18n;
	}

	/**
	 * Returns the component that can be used to encode and decode IDs conveniently for obfuscation
	 *
	 * @return Id
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
	 * @return Flash
	 */
	public function flash() {
		// if the component has not been created yet
		if (!isset($this->flash)) {
			// create the component
			$this->flash = new Flash();
		}

		// return the component
		return $this->flash;
	}

	/**
	 * Returns a new helper for convenient file uploads
	 *
	 * @param string $targetDirectory (optional) the target directory within the `storage` folder
	 * @return FileUpload
	 */
	public function upload($targetDirectory = null) {
		$helper = new FileUpload();

		if ($targetDirectory !== null) {
			$helper->withTargetDirectory($this->getStoragePath($targetDirectory));
		}

		return $helper;
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
		if (\file_exists($filePath) && \is_file($filePath)) {
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
	 * Sends the specified file to the client
	 *
	 * @param string $filePath the path to the file that should be served
	 * @param string $mimeType the MIME type for the output, e.g. `image/jpeg`
	 */
	public function serveFile($filePath, $mimeType) {
		// if the file exists at the specified path
		if (\file_exists($filePath) && \is_file($filePath)) {
			\header('Content-Type: ' . $mimeType, true);
			\header('Accept-Ranges: none', true);
			\header('Connection: close', true);

			// pipe the actual file contents through PHP
			\readfile($filePath);
		}
		// if the file could not be found
		else {
			throw new \RuntimeException('File `' . $filePath . '` not found');
		}
	}

	/**
	 * Detects whether the current request has been sent over plain HTTP (as opposed to secure HTTPS)
	 *
	 * @return bool
	 */
	public function isHttp() {
		return empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off';
	}

	/**
	 * Detects whether the current request has been sent over secure HTTPS (as opposed to plain HTTP)
	 *
	 * @return bool
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
	 * @return string
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
	 * @return string|null
	 */
	public function getHost() {
		return !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
	}

	/**
	 * Returns the host as whitelisted in the configuration
	 *
	 * @return string|null
	 */
	public function getCanonicalHost() {
		return $this->canonicalHost;
	}

	/**
	 * Returns the port of the server used for the current request
	 *
	 * @return int|null
	 */
	public function getPort() {
		return !empty($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : null;
	}

	/**
	 * Returns the public URL for the specified path below the root of this application
	 *
	 * @param string $requestedPath the path below the root of this application, e.g. `/users`
	 * @return string
	 */
	public function url($requestedPath) {
		$requestedPath = trim($requestedPath);
		$requestedPath = '/' . ltrim($requestedPath, '/');

		return $this->canonicalRootUrl . $requestedPath;
	}

	/**
	 * Returns the public URL for the specified path below the root of this application with a query parameter indicating the current locale
	 *
	 * @param string $requestedPath the path below the root of this application, e.g. `/users`
	 * @return string
	 */
	public function urlWithLang($requestedPath) {
		if (isset($this->i18n)) {
			$locale = $this->i18n->getLocale();

			if (!empty($locale)) {
				return $this->urlWithParams($requestedPath, [ 'lang' => $locale ]);
			}
		}

		return $this->url($requestedPath);
	}

	/**
	 * Returns the public URL for the specified path below the root of this application with the supplied parameters in the query
	 *
	 * @param string $requestedPath the path below the root of this application, e.g. `/users`
	 * @param array $params the parameters to append to the query
	 * @return string
	 */
	public function urlWithParams($requestedPath, array $params) {
		return self::appendParamsToUrl(
			$this->url($requestedPath),
			$params
		);
	}

	/**
	 * Returns the route of the current request
	 *
	 * @return string
	 */
	public function currentRoute() {
		return substr($this->router->getRoute(), strlen($this->router->getRootPath()));
	}

	/**
	 * Returns the URL of the current request
	 *
	 * @return string
	 */
	public function currentUrl() {
		return $this->canonicalRootUrl . $this->currentRoute();
	}

	/**
	 * Returns the URL of the current request with a query parameter indicating the current locale
	 *
	 * @return string
	 */
	public function currentUrlWithLang() {
		if (isset($this->i18n)) {
			$locale = $this->i18n->getLocale();

			if (!empty($locale)) {
				return $this->currentUrlWithParams([ 'lang' => $locale ]);
			}
		}

		return $this->currentUrl();
	}

	/**
	 * Returns the URL of the current request with the supplied parameters in the query
	 *
	 * @param array $params the parameters to append to the query
	 * @return string
	 */
	public function currentUrlWithParams(array $params) {
		return self::appendParamsToUrl(
			$this->currentUrl(),
			$params
		);
	}

	/**
	 * Returns the URL of the current request along with its query string
	 *
	 * @return string
	 */
	public function currentUrlWithQuery() {
		$url = $this->currentUrl();

		if (!empty($_SERVER['QUERY_STRING'])) {
			$url .= '?';
			$url .= (string) $_SERVER['QUERY_STRING'];
		}

		return $url;
	}

	/**
	 * Returns the URL of the current request along with its query string and an additional query parameter indicating the current locale
	 *
	 * @return string
	 */
	public function currentUrlWithQueryAndLang() {
		if (isset($this->i18n)) {
			$locale = $this->i18n->getLocale();

			if (!empty($locale)) {
				return $this->currentUrlWithQueryAndParams([ 'lang' => $locale ]);
			}
		}

		return $this->currentUrlWithQuery();
	}

	/**
	 * Returns the URL of the current request along with its query string and the supplied additional parameters in the query
	 *
	 * @param array $params the parameters to append to the query
	 * @return string
	 */
	public function currentUrlWithQueryAndParams(array $params) {
		if (!empty($_SERVER['QUERY_STRING'])) {
			\parse_str($_SERVER['QUERY_STRING'], $existingAndNewParams);
		}
		else {
			$existingAndNewParams = [];
		}

		foreach ($params as $key => $value) {
			$existingAndNewParams[$key] = $value;
		}

		return self::appendParamsToUrl(
			$this->currentUrl(),
			$existingAndNewParams
		);
	}

	/**
	 * Returns the query string from the current request
	 *
	 * @return string|null
	 */
	public function getQueryString() {
		return !empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null;
	}

	/**
	 * Returns the request method from the current request
	 *
	 * @return string
	 */
	public function getRequestMethod() {
		return $this->router->getRequestMethod();
	}

	/**
	 * Returns the URL of the referring page (or site) for the current request
	 *
	 * @return string
	 */
	public function getReferrer() {
		return !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
	}

	/**
	 * Returns the client's IP address
	 *
	 * @return string|null
	 */
	public function getClientIp() {
		return !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
	}

	/**
	 * Returns the user-agent string from the current request, which helps identify the client software (i.e. usually a web browser)
	 *
	 * @return string
	 */
	public function getUa() {
		return !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
	}

	/**
	 * Returns whether the client's IP address is the address of the loopback interface
	 *
	 * @return bool
	 */
	public function isClientLoopback() {
		$clientIp = $this->getClientIp();

		return $clientIp === '127.0.0.1' || $clientIp === '::1';
	}

	/**
	 * Returns whether the client is a command-line interface (CLI) as opposed to an HTTP client
	 *
	 * @return bool
	 */
	public function isClientCli() {
		return \PHP_SAPI === 'cli' && !isset($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_HOST']);
	}

	/**
	 * Returns whether an argument has been provided (at the specified position) via the command-line interface (CLI)
	 *
	 * @param int|null $position (optional) the position of the argument
	 * @return bool
	 */
	public function hasCliArgument($position = null) {
		$position = isset($position) && \is_numeric($position) ? (int) $position : 1;

		return isset($_SERVER['argc']) && $_SERVER['argc'] > $position && isset($_SERVER['argv']) && isset($_SERVER['argv'][$position]);
	}

	/**
	 * Returns the argument provided (at the specified position) via the command-line interface (CLI)
	 *
	 * @param int|null $position (optional) the position of the argument
	 * @return mixed|null
	 */
	public function getCliArgument($position = null) {
		$position = isset($position) && \is_numeric($position) ? (int) $position : 1;

		if (isset($_SERVER['argv']) && isset($_SERVER['argv'][$position])) {
			return $_SERVER['argv'][$position];
		}
		else {
			return null;
		}
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

		$headerLine = 'Content-Type: ' . $contentType;

		if ($charset !== null) {
			$headerLine .= '; charset=' . $charset;
		}

		\header($headerLine);
	}

	/**
	 * Returns whether any file uploads have been sent with the current request
	 *
	 * @return bool
	 */
	public function hasUploads() {
		return !empty($_FILES);
	}

	/**
	 * Returns whether the specified file upload has been sent with the current request
	 *
	 * @param string $name the name of the file upload, i.e. usually the name of the HTML input field
	 * @return bool
	 */
	public function hasUpload($name) {
		// if information about the specified file upload is available
		if (isset($_FILES[$name])) {
			// if the file input had an array-style name
			if (\is_array($_FILES[$name]['error'])) {
				// for any file selected from this input
				foreach ($_FILES[$name]['error'] as $errorCode) {
					if ($errorCode !== \UPLOAD_ERR_NO_FILE) {
						return true;
					}
				}
			}
			// if the file input had a scalar-style name
			else {
				return $_FILES[$name]['error'] !== \UPLOAD_ERR_NO_FILE;
			}
		}

		return false;
	}

	/** Loads (i.e. lexes, parses and compiles) and caches all templates without evaluating them */
	public function precompileTemplates() {
		$this->getTemplateManager()->precompile();
	}

	/** Deletes all compiled templates from the cache */
	public function clearTemplateCache() {
		$this->getTemplateManager()->clearCache();
	}

	private static function determineCanonicalRootUrl() {
		if (isset($_ENV['APP_PUBLIC_URL'])) {
			$candidates = \explode('|', $_ENV['APP_PUBLIC_URL']);
			$best = \array_shift($candidates);

			if (!empty($_SERVER['SERVER_NAME'])) {
				foreach ($candidates as $candidate) {
					$candidateHost = \parse_url($candidate, \PHP_URL_HOST);

					if (\strcasecmp($candidateHost, $_SERVER['SERVER_NAME']) === 0) {
						$best = $candidate;
						break;
					}
				}
			}

			return \rtrim($best, '/');
		}
		else {
			return '';
		}
	}

	/**
	 * Returns the given URL with the specified parameters appended to its query
	 *
	 * @param string $url
	 * @param array $params
	 * @return string
	 */
	private static function appendParamsToUrl($url, array $params) {
		$queryAppendix = \http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
		$pathAndQuery = \explode('?', $url, 2);

		if (isset($pathAndQuery[1])) {
			$queryAndFragment = \explode('#', $pathAndQuery[1], 2);

			if (isset($queryAndFragment[1])) {
				return $pathAndQuery[0] . '?' . $queryAndFragment[0] . '&' . $queryAppendix . '#' . $queryAndFragment[1];
			}
			else {
				return $pathAndQuery[0] . '?' . $queryAndFragment[0] . '&' . $queryAppendix;
			}
		}
		else {
			return $pathAndQuery[0] . '?' . $queryAppendix;
		}
	}

}
