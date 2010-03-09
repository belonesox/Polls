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

/*
 * Messages.
 */
$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['WikiPoll'] = $dir . 'poll.i18n.php';

class WikiPoll
{
    var $parser;

    function WikiPoll()
    {
        $this->parserOptions = new ParserOptions();
        $this->parserOptions->setEditSection(false);
        $this->parserOptions->setTidy(false);
        $this->dbw =& wfGetDB(DB_MASTER);    // Mediawiki writeable connector.
    }

    // Local parsing and HTML rendering for individual lines of wiki markup
    function parse($line)
    {
        global $wgTitle;
        /* Если использовать для разбора каких-либо кусков текста глобальный парсер,
           нужно передавать $clearState = false! Иначе функция parse дёргает
           Parser::clearState() и все сохранённые подстановки типа
           UNIQ35b039f153ed3bf9-h-1--QINU забываются в тексте статьи.
           Этого, между прочим, не делает даже OutputPage::parse() - а должна бы :-( */
        $parserOutput = $this->parser->parse(trim($line), $wgTitle, $this->parserOptions, false, false);
        return str_replace(array("<p>","</p>","\r","\n"), "", $parserOutput->mText);
    }

    function get_user_votes_count($ID, $user, $IP = false)
    {
        // Select count of used votes
        $where = 'poll_id='.$this->dbw->addQuotes($ID) . ' AND (poll_user='.$this->dbw->addQuotes($user);
        if ($IP)
            $where .= ' OR poll_ip='.$this->dbw->addQuotes($IP);
        $where .= ')';
        $user_votes_count = $this->dbw->selectField('`wikipolls`.`poll_vote`', 'count(1)', $where, __METHOD__);
        return $user_votes_count;
    }

    function renderPoll($input, $attr, $parser)
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

        $this->parser = $parser;
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
                $authorized = 1;
                continue;
            }
            elseif ($line == 'ALTERNATIVE')
            {
                $poll_points = 1;
                continue;
            }
            elseif (preg_match('/^POINTS\s*(\d+)$/s', $line, $m))
            {
                $poll_points = $m[1];
                continue;
            }
            elseif (preg_match('/^(END[-_]?POLL|POLL[-_]?END)\s*(\d{4}-\d{2}-\d{2})$/s', $line, $m))
            {
                $poll_end = $m[2];
                continue;
            }
            elseif ($line == 'HIDE_RESULTS')
            {
                $hide_results = true;
                continue;
            }
            elseif ($line == 'RESTRICT_IP')
            {
                $restrict_ip = true;
                continue;
            }
            array_unshift($lines, $line);
            break;
        }

        /* Restrict poll display and/or voting to authorized users */
        if ($authorized && !$wgUser->mPassword)
            return wfMsg('wikipoll-must-login');

        /* We must have at least two lines: question and at least one variant of the answer */
        if (sizeof($lines) < 2)
            return wfMsg('wikipoll-empty');

        /* HIDE_RESULTS requires END_POLL */
        if ($hide_results && !$poll_end)
            return wfMsg('wikipoll-results-hidden-but-no-end');

        $question = $this->parse(array_shift($lines));

        $labels = array();
        $values = array();
        foreach($lines as $line)
        {
            if (trim($line) != "")
            {
                $label = $this->parse($line);
                array_push($labels, $label);
                array_push($values, 0);
            }
        }

        // Select count of used votes
        $user_votes_count = $this->get_user_votes_count($ID, $user, $restrict_ip ? $IP : false);

        // *******************************************************************************************
        // action treatment
        // *******************************************************************************************
        if (!empty($_POST['poll-ID']) && $_POST['poll-ID'] == $ID && $_POST['vote'] &&
            (($user_votes_count < $poll_points && $poll_points > 0) ||
             ($user_votes_count == 0 && $poll_points <= 0)) &&
            !empty($_POST['answers']) &&
            (!$poll_end || $poll_end > date('Y-m-d')))
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
        $user_votes_count = $this->get_user_votes_count($ID, $user, $restrict_ip ? $IP : false);
        // If no more points to vote -> show results
        if (($user_votes_count >= $poll_points && $poll_points > 0) ||
            ($user_votes_count > 0 && $poll_points <= 0) ||
            $poll_end && $poll_end <= date('Y-m-d'))
        {
            if (!$hide_results || $poll_end && $poll_end <= date('Y-m-d'))
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
            }
            else
            {
                $n = $this->dbw->selectField(
                    '`wikipolls`.`poll_vote`',
                    'COUNT(DISTINCT poll_user)',
                    array('poll_id' => $ID),
                    __METHOD__);
                $str = $this->parse(wfMsg('wikipoll-voted-count', $n, $poll_end));
            }

            $result = "<a name='poll-$ID'></a><p><b>$question</b></p>$str";

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
                $str .= $this->parse(wfMsg('wikipoll-remaining', $votes_rest));
            }
            if ($poll_points > 0)
            {
                $block = ''; $i = 0;
                foreach($labels as $label)
                {
                    $i++;
                    $block .= <<<EOT
<li>
    <form action="#poll-{$ID}" method="POST">
        <input type="hidden" name="poll-ID" value="{$ID}">
        <input type="hidden" name="answers" value="{$i}">
        <input style="color:blue;background-color:yellow" value='+' name="vote" type='submit'>&nbsp;
        <label for="vote">{$label}</label>
    </form>
</li>
EOT;
                }
                $str .= "<ul>$block</ul>";
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
        }

        $str = preg_replace('/[\n\r]+\s+/','',trim($str));
        return $str;
    }
}

function wfPoll()
{
    global $wgParser;
    $WikiPoll = new WikiPoll();
    $wgParser->setHook("poll", array($WikiPoll, "renderPoll"));
}

?>
