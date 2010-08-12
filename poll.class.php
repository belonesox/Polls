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

class WikiPoll
{
    static $parserOptions;

    // DB schema updates
    static function LoadExtensionSchemaUpdates()
    {
        global $wgExtNewTables;
        $wgExtNewTables[] = array("poll_votes", dirname(__FILE__) . "/poll-tables.sql");
        return true;
    }

    // Local parsing and HTML rendering for individual lines of wiki markup
    static function parse($parser, $line)
    {
        global $wgTitle;
        /* Если использовать для разбора каких-либо кусков текста глобальный парсер,
           нужно передавать $clearState = false! Иначе функция parse дёргает
           Parser::clearState() и все сохранённые подстановки типа
           UNIQ35b039f153ed3bf9-h-1--QINU забываются в тексте статьи.
           Этого, между прочим, не делает даже OutputPage::parse() - а должна бы :-( */
        if (!self::$parserOptions)
        {
            self::$parserOptions = new ParserOptions();
            self::$parserOptions->setEditSection(false);
            self::$parserOptions->setTidy(false);
        }
        $old = $parser->mOptions;
        $parserOutput = $parser->parse(trim($line), $parser->mTitle ? $parser->mTitle : $wgTitle, self::$parserOptions, false, false);
        $parser->mOptions = $old;
        return str_replace(array("<p>","</p>","\r","\n"), "", $parserOutput->mText);
    }

    static function get_user_votes_count($ID, $user, $IP = false)
    {
        $dbw = wfGetDB(DB_MASTER);
        // Select count of used votes
        $where = 'poll_id='.$dbw->addQuotes($ID) . ' AND (poll_user='.$dbw->addQuotes($user);
        if ($IP)
            $where .= ' OR poll_ip='.$dbw->addQuotes($IP);
        $where .= ')';
        $user_votes_count = $dbw->selectField('poll_vote', 'count(1)', $where, __METHOD__);
        return $user_votes_count;
    }

    // A parser hook for <poll> tag
    static function renderPoll($input, $attr, $parser)
    {
        global $wgUser, $wgTitle, $wgOut;

        wfLoadExtensionMessages('WikiPoll');

        $IP = wfGetIP();                    // IP-address or poll reader or voter.
        $ID = strtoupper(md5($input));      // MD5-hash or poll text
        $timestamp = date("Y-m-d H:i:s");   // current date

        if ($wgUser->mName == "")
            $user = $IP;
        else
            $user = $wgUser->mName;

        $parser->disableCache();

        $lines = split("\n", trim($input));
        $labels = array();
        $values = array();

        $authorized   = 0;
        $poll_points  = 0;
        $poll_end     = false;
        $hide_results = false;
        $restrict_ip  = false;
        while (true)
        {
            $line = trim(array_shift($lines));
            if ($line == 'AUTHORIZED')
            {
                /* Display results and allow voting only for authorized users */
                $authorized = 1;
                continue;
            }
            elseif ($line == 'AUTHORIZED_DISPLAY')
            {
                /* Display poll options, results, and allow voting only for authorized users */
                $authorized = 2;
                continue;
            }
            elseif ($line == 'ALTERNATIVE')
            {
                /* Alternative selection (default) */
                $poll_points = 1;
                continue;
            }
            elseif (preg_match('/^POINTS\s*(\d+)$/s', $line, $m))
            {
                /* Selection of N=$m[1] options */
                $poll_points = intval($m[1]);
                continue;
            }
            elseif (preg_match('/^(END[-_]?POLL|POLL[-_]?END)\s*(\d{4}-\d{2}-\d{2})$/s', $line, $m))
            {
                /* Disable voting after $m[2] */
                $poll_end = $m[2];
                continue;
            }
            elseif ($line == 'HIDE_RESULTS')
            {
                /* Hide poll results until POLL_END */
                $hide_results = true;
                continue;
            }
            elseif ($line == 'RESTRICT_IP')
            {
                /* Restrict voting by IP, not only by username */
                $restrict_ip = true;
                continue;
            }
            array_unshift($lines, $line);
            break;
        }

        /* Default is alternative selection */
        if ($poll_points < 1)
            $poll_points = 1;

        /* Restrict poll display to authorized users when AUTHORIZED_DISPLAY is specified */
        if ($authorized > 1 && !$wgUser->getID())
            return wfMsg('wikipoll-must-login');

        /* We must have at least two lines: question and at least one variant of the answer */
        if (sizeof($lines) < 2)
            return wfMsg('wikipoll-empty');

        /* HIDE_RESULTS requires END_POLL */
        if ($hide_results && !$poll_end)
            return wfMsg('wikipoll-results-hidden-but-no-end');

        $question = self::parse($parser, array_shift($lines));

        $labels = array();
        $values = array();
        foreach ($lines as $line)
        {
            if (trim($line) != "")
            {
                $label = self::parse($parser, $line);
                $labels[] = $label;
                $values[] = 0;
            }
        }

        // Select count of used votes
        $user_votes_count = self::get_user_votes_count($ID, $user, $restrict_ip ? $IP : false);

        $dbw =& wfGetDB(DB_MASTER);

        $str = "<a name='poll-$ID'><p><b>$question</b></p></a>";

        // Action treatment
        if ($_POST['poll-ID'] == $ID && $_POST['vote'] &&
            !($user_votes_count >= $poll_points ||
              $poll_end && $poll_end <= date('Y-m-d') ||
              $authorized > 0 && !$wgUser->getID()))
        {
            $votes = $_POST['answers'];
            // Just one answer
            if ($votes && !is_array($votes))
                $votes = array($votes);
            if ($votes && count($votes)+$user_votes_count <= $poll_points)
            {
                // Register all user votes
                foreach ($votes as $vote)
                {
                    $dbw->insert('poll_vote', array(
                        'poll_id'     => $ID,
                        'poll_user'   => $user,
                        'poll_ip'     => $IP,
                        'poll_answer' => $vote,
                        'poll_date'   => $timestamp,
                    ), __METHOD__);
                }
                // Update count of used votes
                $user_votes_count = self::get_user_votes_count($ID, $user, $restrict_ip ? $IP : false);
            }
            elseif ($user_answers)
                $str .= wfMsg('wikipoll-too-many-votes');
        }

        // User passed authorization && Poll did not end && Votes available
        if ($user_votes_count < $poll_points &&
            (!$poll_end || $poll_end > date('Y-m-d')) &&
            (!$authorized || $wgUser->getID()))
        {
            // Show form
            $action = $wgTitle->escapeLocalUrl("action=purge");
            if ($poll_points == 1)
            {
                $block = '';
                foreach ($labels as $i => $label)
                {
                    $form = Xml::hidden('poll-ID', $ID);
                    $form .= Xml::hidden('answers', $i+1);
                    $form .= Xml::submitButton('+', array('name' => 'vote', 'style' => 'color: blue; background-color: yellow'));
                    $form .= '&nbsp;';
                    $form .= $label;
                    $form = Xml::element('form', array('action' => '#poll-'.$ID, 'method' => 'POST'), $form);
                    $block .= Xml::element('li', NULL, $form);
                }
                $str .= Xml::wrapClass($block, 'wikipoll-alt', 'ul');
            }
            else
            {
                $votes_rest = $poll_points-$user_votes_count;
                $str .= self::parse($parser, wfMsg('wikipoll-remaining', $votes_rest));
                $block = '';
                foreach ($labels as $i => $label)
                    $block .= Xml::element('li', NULL, Xml::checkLabel($label, "answers[]", "answers-$i", array('value' => $i+1)));
                $block = Xml::element('ul', NULL, $block);
                $block .= Xml::hidden('poll-ID', $ID);
                $block .= Xml::submitButton('Ok', array('name' => 'vote'));
                $str .= Xml::element('form', array('action' => '#poll-'.$ID, 'method' => 'POST'), $block);
            }
            return $str;
        }
        // User passed authorization && Votes unavailable && Results not hidden, or poll ended
        elseif (($user_votes_count >= $poll_points && !$hide_results || $poll_end <= date('Y-m-d')) &&
            (!$authorized || $wgUser->getID()))
        {
            // Show results.
            // Get votes distribution
            $res = $dbw->select('poll_vote',
                'poll_answer, count(1) votes',
                array('poll_id' => $ID),
                __METHOD__,
                array('GROUP BY' => '1')
            );
            while ($row = $dbw->fetchObject($res))
                $values[$row->poll_answer-1] += $row->votes;
            $dbw->freeResult($res);

            require_once("graphs.inc.php");
            $graph = new BAR_GRAPH('hBar');
            $graph->showValues = 1;
            $graph->barWidth = 20;
            $graph->labelSize = 12;
            $graph->absValuesSize = 12;
            $graph->percValuesSize = 12;
            $graph->graphBGColor = 'Aquamarine';
            $graph->barColors = 'Gold';
            $graph->barBGColor = 'Azure';
            $graph->labelColor = 'black';
            $graph->labelBGColor = 'LemonChiffon';
            $graph->absValuesColor = '#000000';
            $graph->absValuesBGColor = 'Cornsilk';
            $graph->graphPadding = 15;
            $graph->graphBorder = '1px solid blue';

            $graph->values = $values;
            $graph->labels = $labels;
            $s = $graph->create();
            $s = str_replace("<table","\n<table",$s);
            $s = str_replace("<td","\n<td",$s);
            $s = str_replace("<tr","\n<tr",$s);
            $str .= $s;
        }
        // All other cases
        else
        {
            // Show poll options
            $str .= "<ul>";
            foreach ($labels as $label)
                $str .= "<li>$label</li>";
            $str .= "</ul>";
            if (!$authorized || $wgUser->getID())
            {
                // "Total users voted" for authorized users
                $n = $dbw->selectField('poll_vote',
                    'COUNT(DISTINCT poll_user)',
                    array('poll_id' => $ID),
                    __METHOD__);
                $str .= self::parse($parser, wfMsg('wikipoll-voted-count', $n, $poll_end));
            }
            else
            {
                // "You must login to vote" for unathorized users
                $str .= wfMsg('wikipoll-must-login-to-vote');
            }
        }

        return $str;
    }
}
