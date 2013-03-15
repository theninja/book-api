<?php
/**
 * Basic bootstrap file:
 * Autoloader, registry, config, auth and request initialised.
 */
namespace BookApi;

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

spl_autoload_register(function($class) {
	include(
		__DIR__.DIRECTORY_SEPARATOR.'lib'.
		str_replace('\\', DIRECTORY_SEPARATOR,
			substr($class, strlen(__NAMESPACE__))
		).'.php'
	);
});

Registry::init(new Config(require_once 'config.php'));
Auth::init();
Request::init();
?>