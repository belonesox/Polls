<?php

/*
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * @author Stas Fomin <stas-fomin@yandex.ru>
 * @author Vitaliy Filippov <vitalif@mail.ru>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionCredits['parserhook'][] = array(
    'path'           => __FILE__,
    'name'           => 'WikiPolls',
    'version'        => '0.9 (2010-04-22)',
    'author'         => 'Stas Fomin <stas-fomin@yandex.ru>',
    'descriptionmsg' => 'wikipolls-desc',
    'url'            => 'http://lib.custis.ru/Справка:Опросы_и_голосования',
);

$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['WikiPoll'] = $dir . 'poll.i18n.php';
$wgAutoloadClasses['WikiPoll'] = $dir . 'poll.class.php';
$wgAutoloadClasses['BAR_GRAPH'] = $dir . 'graphs.inc.php';
$wgExtensionFunctions[] = 'wfPoll';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'WikiPoll::LoadExtensionSchemaUpdates';

/* Hook is here, class is autoloaded lazily */
function wfPoll()
{
    global $wgParser;
    $wgParser->setHook('poll', 'WikiPoll::renderPoll');
}
