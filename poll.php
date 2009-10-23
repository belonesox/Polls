<?php

/*
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * @author Stas Fomin <stas-fomin@yandex.ru>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
$wgExtensionFunctions[] = "wfPoll";

class WikiPoll
{
    function WikiPoll()
    {
        global $wgParser;
        $this->localParser = clone $wgParser;
        $this->parserOptions = new ParserOptions();
        $this->parserOptions->setEditSection(false);
        $this->parserOptions->setTidy(false);
        $this->dbw =& wfGetDB(DB_MASTER);    // Mediawiki writeable connector.
    }

    // Local parsing and HTML rendering for individual lines of wiki markup
    function parseLine($line)
    {
        global $wgTitle;
        // Note: do not use wgParser !
        $parserOutput = $this->localParser->parse(trim($line), $wgTitle, $this->parserOptions, false);
        $label = str_replace(array("<p>","</p>","\r","\n"), "", $parserOutput->mText);
        return $label;
    }

    function get_user_votes_count($ID, $user)
    {
        // Select count of used votes
        $user_votes_count = $this->dbw->selectField('`wikipolls`.`poll_vote`', 'count(1)', array('poll_id' => $ID, 'poll_user' => $user));
        return $user_votes_count;
    }

    function renderPoll($input)
    {
        global $wgParser,$wgUser,$wgTitle,$wgOut;

        $IP = wfGetIP();                    // IP-address or poll reader or voter.
        $ID = strtoupper(md5($input));      // MD5-hash or poll text
        $timestamp = date("Y-m-d H:i:s");   // current date

        if ($wgUser->mName == "")
                $user = $IP;
        else
                $user = $wgUser->mName;

        $wgParser->disableCache();

        $lines = split("\n",$input);
        $labels = array();
        $values = array();

        $authorized = 0;
        if (strpos($lines[1], "AUTHORIZED") !== false)      // Important: !==
        {
            $authorized = 1;
            unset($lines[1]);
            $lines = array_values($lines);    // strip first line
        }

        // TODO: I18n
        if ($authorized && ($wgUser->mPassword == ""))
        {
            return <<<EOT
            <p><b><small>Sorry, you must be logged to view this poll.</small></b>
            <p><b><small>Извините, вы должны войти в систему, чтобы участвовать в этом голосовании или видеть его результаты.</small></b>
EOT;
        }

        // alternative selection is equiivalent to vote with 1 point
        if (trim($lines[1]) == "ALTERNATIVE")
            $lines[1] = "POINTS 1";

        $poll_points = 0;
        // If line like POINTS
        if (strpos($lines[1],"POINTS") !== false)
        {
            $terms = explode(' ',trim($lines[1]));
            // Getting the number of points
            if (sizeof($terms) > 1 && is_numeric($terms[sizeof($terms)-1]))
                $poll_points = $terms[sizeof($terms)-1];
            // strip first line
            unset($lines[1]);
            $lines = array_values($lines);
        }

        // We must have at least two lines : question and at least one variant of the answer.
        if (sizeof($lines) < 2)
            return '';

        $question = $this->parseLine($lines[1]);

        // strip first line
        unset($lines[1]);
        $lines = array_values($lines);

        $labels = array();
        $values = array();
        foreach($lines as $line)
        {
            if (trim($line) != "")
            {
                $label = $this->parseLine($line);
                array_push($labels, $label);
                array_push($values, 0);
            }
        }

        // Select count of used votes
        $user_votes_count = $this->get_user_votes_count($ID, $user);
        // *******************************************************************************************
        // action treatment
        // *******************************************************************************************
        if (!empty($_POST['poll-ID']) &&
            $_POST['poll-ID'] == $ID &&
            (($user_votes_count < $poll_points && $poll_points > 0) ||
             ($user_votes_count == 0 && $poll_points <= 0)) &&
            !empty($_POST['answers']))
        {
            $user_answers = $_POST['answers'];
            // Just one answer
            if (!is_array($user_answers))
                $user_answers = array($user_answers => 1);
            // Register all user votes
            $votes = array_keys($user_answers);
            foreach($votes as $vote)
            {
                $this->dbw->insert('`wikipolls`.`poll_vote`', array(
                    'poll_id'     => $ID,
                    'poll_user'   => $user,
                    'poll_ip'     => $IP,
                    'poll_answer' => $vote,
                    'poll_date'   => $timestamp,
                ), __METHOD__);
            }
        }

        // Select count of used votes
        $user_votes_count = $this->get_user_votes_count($ID, $user);
        // If no more points to vote -> show results
        if (($user_votes_count >= $poll_points && $poll_points > 0) ||
                ($user_votes_count > 0 && $poll_points <= 0))
        {
            // Show results.
            // Get votes distribution
            $sql =
" SELECT  poll_answer, count(1) as votes
        FROM `wikipolls`.`poll_vote`
     WHERE  poll_id = '{$ID}'
GROUP BY  1
ORDER BY  1";
            $res = $this->dbw->query($sql, __METHOD__);
            while ($row = $this->dbw->fetchObject($res))
                $values[$row->poll_answer-1] += $row->votes;
            $this->dbw->freeResult($res);

            $str = "<a name='poll-$ID'><p><b>$question</b></p></a>";

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
            $str = $graph->create();
            $str = str_replace("<table","\n<table",$str);
            $str = str_replace("<td","\n<td",$str);
            $str = str_replace("<tr","\n<tr",$str);
            $result = "<a name='poll-$ID'><p><b>$question</b></p></a>$str";

            $wgParser->disableCache();
            return $result;
        }

        // *******************************************************************************************
        // show form
        // *******************************************************************************************
        {
            $action = $wgTitle->escapeLocalUrl("action=purge");
            $str = "<a name='poll-$ID'><p><b>$question</b></p></a>";
            if ($poll_points > 1)
            {
                $votes_rest = $poll_points-$user_votes_count;
                $str .= <<<EOT
<p><small>You have {$votes_rest} points to vote</small>/
     <small>Вы можете использовать {$votes_rest} голоса.</small></p>
EOT;
                $block=''; $i=0;
                foreach($labels as $label)
                {
                    $i++;
                    $block .= <<<EOT
                    <li>
                        <form action="#poll-{$ID}" method="POST">
                            <input type="hidden" name="poll-ID" value="{$ID}">
                            <input type="hidden" name="answers" value="{$i}">
                            <input style="color:blue;background-color:yellow" value='+' name="Ok-{$ID}-{$i}" type='submit'>&nbsp;
                            <label for="Ok-{$ID}-{$i}">{$label}</label>
                        </form>
                    </li>
EOT;
                }
            }
            else
            {
                $block = ''; $i = 0;
                foreach($labels as $label)
                {
                    $i++;
                    $block .= <<<EOT
<li>
    <input type="checkbox" name="answers[$i]" id="answers[$i]" value='1' />
    <label for="answers[$i]">$label</label>
</li>
EOT;
                }
                $str .= <<<EOT
<form action="#poll-$ID" method="POST"><input type="hidden" name="poll-ID" value="$ID">
    <ul>$block</ul>
    <input name='vote' value='Ok' type='submit'>
</form>
EOT;
            }
            $str .= "<ul>$block</ul>";
        }

        $str = preg_replace('/[\n\r]\s+/','',trim($str));
        return $str;
    }
}

function wf_render_poll($str)
{
    global $WikiPoll;
    return $WikiPoll->renderPoll($str);
}

function wfPoll()
{
    global $wgParser;
    global $WikiPoll;
    $WikiPoll = new WikiPoll();
    $wgParser->setHook("poll", "wf_render_poll");
}

?>
