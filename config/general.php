<?php

/**
 * General Configuration
 *
 * All of your system's general configuration settings go in here. You can see a
 * list of the available settings in vendor/craftcms/cms/src/config/GeneralConfig.php.
 *
 * @see \craft\config\GeneralConfig
 */

use craft\config\GeneralConfig;
use craft\helpers\App;

$isDev = App::env('DEV_MODE');
$isProd = App::env('DEV_MODE') ? false : true;

return GeneralConfig::create()
    ->cacheDuration(0)
    ->defaultWeekStartDay(1)
    ->omitScriptNameInUrls()
    ->devMode(App::env('DEV_MODE') ?? false)
    ->preloadSingles(true)
    ->backupOnUpdate(!$isDev)
    ->sendPoweredByHeader(false)
    ->limitAutoSlugsToAscii(true)
    ->convertFilenamesToAscii(true)
    ->preventUserEnumeration(true)
    ->allowAdminChanges(App::env('ALLOW_ADMIN_CHANGES') ?? false)
    ->disallowRobots(true)
    ->preventUserEnumeration()
    ->maxRevisions(10)
    ->aliases([
        '@webroot' => dirname(__DIR__) . '/web',
    ])
    ->timezone('Europe/Rome')
    ->setGraphqlDatesToSystemTimeZone(true)
    ->upscaleImages(false)
    ->maxCachedCloudImageSize(3200)
    ->headlessMode(true)
    ->enableGraphqlCaching(true)
    ->transformGifs(false)
    ->maxUploadFileSize("100M");
