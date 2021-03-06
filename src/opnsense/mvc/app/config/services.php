<?php

use Phalcon\DI\FactoryDefault;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Url as UrlResolver;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter;
use Phalcon\Session\Adapter\Files as SessionAdapter;
use OPNsense\Core\Config;
use OPNsense\Core\Routing;

/**
 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
 */
$di = new FactoryDefault();
$di->set('config', $config);

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->set('url', function () use ($config) {
    $url = new UrlResolver();
    $url->setBaseUri($config->application->baseUri);

    return $url;
}, true);

/**
 * Setting up the view component
 */
$di->set('view', function () use ($config) {

    $view = new View();
    // if configuration defines more view locations, convert phalcon config items to array
    if (is_string($config->application->viewsDir)) {
        $view->setViewsDir($config->application->viewsDir);
    } else {
        $viewDirs = array();
        foreach ($config->application->viewsDir as $viewDir) {
            $viewDirs[] = $viewDir;
        }
        $view->setViewsDir($viewDirs);
    }
    $view->registerEngines(array(
        '.volt' => function ($view, $di) use ($config) {

            $volt = new VoltEngine($view, $di);

            $volt->setOptions(array(
                'compiledPath' => $config->application->cacheDir,
                'compiledSeparator' => '_'
            ));
            // register additional volt template functions
            $volt->getCompiler()->addFunction('theme_file_or_default', 'view_fetch_themed_filename');
            $volt->getCompiler()->addFunction('file_exists', 'file_exists');

            return $volt;
        },
        '.phtml' => 'Phalcon\Mvc\View\Engine\Php'
    ));

    return $view;
}, true);

/**
 * If the configuration specify the use of metadata adapter use it or use memory otherwise
 */
$di->set('modelsMetadata', function () {
    return new MetaDataAdapter();
});

/**
 * Start the session the first time some component request the session service
 */
$di->setShared('session', function () {
    $session = new SessionAdapter();
    $session->start();
    // Set session response cookie, unfortunalty we need to read the config here to determine if secure option is
    // a valid choice.
    $cnf = Config::getInstance();
    if ((string)$cnf->object()->system->webgui->protocol == 'https') {
        $secure = true;
    } else {
        $secure = false;
    }
    setcookie(session_name(), session_id(), null, '/', null, $secure, true);

    return $session;
});



/**
 * Setup router
 */
$di->set('router', function () use ($config) {
    $routing = new Routing($config->application->controllersDir, "ui");
    $routing->getRouter()->handle();
    return $routing->getRouter();
});
