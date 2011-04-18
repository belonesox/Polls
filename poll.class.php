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
    const POLL_UNAUTH = 0;
    const POLL_AUTH_VOTE = 1;
    const POLL_AUTH_DISPLAY = 2;

    var $revote = false;
    var $ID = NULL, $uniqueID = false;
    var $is_checks = true, $points = 0, $end = NULL;
    var $authorized = 0, $hide_results = false, $restrict_ip = false;

    var $username, $userip;
    var $user_votes_count, $too_many_votes;
    var $result;

    var $question, $answers;

    // Identical to Xml::element, but does no htmlspecialchars()
    static function xelement($element, $attribs = null, $contents = '', $allowShortTag = true)
    {
        if (is_null($contents))
            return Xml::openElement($element, $attribs);
        elseif ($contents == '')
            return Xml::element($element, $attribs, $contents, $allowShortTag);
        return Xml::openElement($element, $attribs) . $contents . Xml::closeElement($element);
    }

    // DB schema updates
    static function LoadExtensionSchemaUpdates()
    {
        global $wgExtNewTables;
        $wgExtNewTables[] = array("poll_votes", dirname(__FILE__) . "/poll-tables.sql");
        return true;
    }

    // A parser hook for <poll> tag
    static function renderPoll($input, $attr, $parser)
    {
        $parser->disableCache();
        wfLoadExtensionMessages('WikiPoll');
        $poll = WikiPoll::newFromText($parser, $input);
        if (!is_object($poll))
            return $poll;
        $poll->handle_postdata();
        return $poll->render();
    }

    // Local parsing and HTML rendering for individual lines of wiki markup
    var $parser, $parserOptions;
    function parse($line)
    {
        global $wgTitle;
        /* Если использовать для разбора каких-либо кусков текста глобальный парсер,
           нужно передавать $clearState = false! Иначе функция parse дёргает
           Parser::clearState() и все сохранённые подстановки типа
           UNIQ35b039f153ed3bf9-h-1--QINU забываются в тексте статьи.
           Этого, между прочим, не делает даже OutputPage::parse() - а должна бы :-( */
        if (!$this->parserOptions)
        {
            $this->parserOptions = new ParserOptions();
            $this->parserOptions->setEditSection(false);
            $this->parserOptions->setTidy(false);
            // The Cite extension requires <references /> if a <ref>
            // was anywhere inside previously parsed text,
            // only when isSectionPreview == false
            // This generates error messages if <ref> was used even outside
            // the poll itself.
            $this->parserOptions->setIsSectionPreview(true);
        }
        $old = $this->parser->mOptions;
        $parserOutput = $this->parser->parse($line, $this->parser->mTitle ? $this->parser->mTitle : $wgTitle, $this->parserOptions, false, false);
        $this->parser->mOptions = $old;
        return str_replace(array("<p>", "</p>", "\r", "\n"), "", $parserOutput->mText);
    }

    // get count of used votes
    function get_user_votes()
    {
        if ($this->user_votes === NULL)
        {
            $dbr = wfGetDB(DB_SLAVE);
            $where = array('poll_id' => $this->ID);
            $where[] = $this->user_where($dbr);
            $res = $dbr->select('poll_vote', 'poll_answer', $where, __METHOD__);
            $this->user_votes = array();
            foreach ($res as $row)
                $this->user_votes[] = $row->poll_answer;
        }
        return $this->user_votes;
    }

    // create new poll object from text
    // returns object => success
    // returns string => error
    static function newFromText($parser, $text)
    {
        global $wgUser;

        $self = new WikiPoll;
        $self->parser = $parser;
        $self->userip = wfGetIP();
        $self->username = $wgUser->getName();

        $self->ID = strtoupper(md5($text)); // MD5-hash or poll text
        $lines = explode("\n", trim($text));
        while ($lines)
        {
            $line = strtoupper(trim($lines[0]));
            if ($line == 'AUTHORIZED')
            {
                /* Display results and allow voting only for authorized users */
                $self->authorized = self::POLL_AUTH_VOTE;
            }
            elseif ($line == 'CHECKS')
            {
                /* Allow to select any number of different options (default) */
                $self->is_checks = true;
            }
            elseif ($line == 'AUTHORIZED_DISPLAY')
            {
                /* Display poll options, results, and allow voting only for authorized users */
                $self->authorized = self::POLL_AUTH_DISPLAY;
            }
            elseif ($line == 'ALTERNATIVE')
            {
                /* Alternative selection */
                $self->points = 1;
                $self->is_checks = false;
            }
            elseif (preg_match('/^POINTS\s*(\d+)$/s', $line, $m))
            {
                /* Selection of N=$m[1] options, allowing duplicates */
                $self->points = intval($m[1]);
                $self->is_checks = false;
            }
            elseif (preg_match('/^(END[-_ ]?POLL|POLL[-_ ]?END)\s*(\d{4}-\d{2}-\d{2})$/s', $line, $m))
            {
                /* Disable voting after $m[2] */
                $self->end = $m[2];
            }
            elseif ($line == 'HIDE_RESULTS')
            {
                /* Hide poll results until POLL_END */
                $self->hide_results = true;
            }
            elseif ($line == 'RESTRICT_IP')
            {
                /* Restrict voting by IP, not only by username */
                $self->restrict_ip = true;
            }
            elseif (preg_match('/^UNSAFE[\s_]*ID=(\S+)$/s', $line, $m))
            {
                /* Unsafe poll, allows to overwrite answers later */
                $unsafe_name = preg_match('/\W/', $m[1]) || strlen($unsafe_name) > 31;
                $self->ID = 'X'.($unsafe_name ? substr(md5($m[1]), 1) : $m[1]);
            }
            elseif ($line == 'PAGENAME_ID' || $line == 'UNIQUE')
            {
                /* Append page name hash to the ID to make polls with equal ID
                   differ for different wiki pages */
                $self->uniqueID = true;
            }
            elseif ($line == 'REVOTE' || $line == 'ALLOW_RECALL' || $line == 'ALLOW_REVOTE')
            {
                /* Allow to recall answers */
                $self->revote = true;
            }
            elseif ($line != "")
                break;
            array_shift($lines);
        }
        if ($self->uniqueID)
            $self->ID = 'Y'.substr(md5($self->ID . '-' . $parser->mTitle->getPrefixedText()), 1);
        if ($self->points < 1)
            $self->points = 1;
        $self->question = $self->parse(trim(array_shift($lines)));
        $self->answers = array();
        foreach ($lines as $line)
        {
            $line = trim($line);
            if ($line)
                $self->answers[] = $self->parse($line);
        }
        if ($self->question == "" || count($self->answers) < 2)
            return wfMsg('wikipoll-empty');
        if ($self->hide_results && !$self->end)
        {
            /* HIDE_RESULTS requires END_POLL */
            return wfMsg('wikipoll-results-hidden-but-no-end');
        }
        return $self;
    }

    // return HTML code of rendered poll / form
    function render()
    {
        global $wgUser;
        $html = '';
        if ($this->too_many_votes)
            $html .= wfMsg('wikipoll-too-many-votes');
        $html .= '<p><a name="poll-'.$this->ID.'"><b>'.$this->question.'</b></a></p>';
        $uv = $this->get_user_votes();
        $results = false;
        if ($this->authorized != self::POLL_UNAUTH && !$wgUser->getID())
        {
            if ($self->authorized == self::POLL_AUTH_DISPLAY)
                return wfMsg('wikipoll-must-login');
            else
            {
                // "You must login to vote" for unathorized users
                $html .= $this->html_options();
                $html .= wfMsg('wikipoll-must-login-to-vote');
            }
            return $html;
        }
        elseif ($this->end && date('Y-m-d') >= $this->end)
        {
            $html .= $this->html_results();
        }
        elseif ($this->is_checks && !$uv || count($uv) < $this->points)
        {
            if ($this->is_checks)
                $html .= $this->html_form_checks();
            else
                $html .= $this->html_form_points();
            return $html;
        }
        elseif (!$this->hide_results)
        {
            $html .= $this->html_results();
        }
        else
        {
            $html .= $this->html_options();
            $html .= $this->html_total();
            return $html;
        }
        /* Show revote link */
        if ($this->revote)
            $html .= '<p><a href="?poll-ID='.$this->ID.'&recall=1#poll-'.$this->ID.'">'.wfMsg('wikipoll-recall').'</a></p>';
        return $html;
    }

    // SQL condition to select user votes
    function user_where($db)
    {
        return 'poll_user='.$db->addQuotes($this->username).($this->restrict_ip ? ' OR poll_ip='.$db->addQuotes($this->userip) : '');
    }

    // Handle POST data
    function handle_postdata()
    {
        global $wgTitle;
        if (!$_REQUEST['poll-ID'] || $_REQUEST['poll-ID'] != $this->ID)
            return;
        $dbw = wfGetDB(DB_MASTER);
        if ($_REQUEST['recall'])
        {
            // Delete old votes
            $dbw->delete('poll_vote', array(
                'poll_id' => $this->ID,
                $this->user_where($dbw)
            ));
            $dbw->commit();
            header("Location: ".$wgTitle->getFullUrl()."#poll-".$this->ID);
            exit;
        }
        elseif (!$_REQUEST['vote'])
            return;
        $votes = $_REQUEST['answers'];
        if (!is_array($votes))
            $votes = array($votes); // Just one answer
        $uv = $this->get_user_votes();
        if ($votes && ($this->is_checks || count($votes)+count($uv) <= $this->points))
        {
            $timestamp = wfTimestamp(TS_DB);
            if ($this->is_checks)
            {
                // Delete old votes for CHECKS mode
                $dbw->delete('poll_vote', array(
                    'poll_id' => $this->ID,
                    $this->user_where($dbw)
                ));
            }
            // Register user votes
            $rows = array();
            foreach ($votes as $vote)
                $rows[] = array(
                    'poll_id'     => $this->ID,
                    'poll_user'   => $this->username,
                    'poll_ip'     => $this->userip,
                    'poll_answer' => $vote,
                    'poll_date'   => $timestamp,
                );
            $dbw->insert('poll_vote', $rows, __METHOD__);
            $dbw->commit();
            header("Location: ".$wgTitle->getFullUrl()."#poll-".$this->ID);
            exit;
        }
        elseif ($uv)
            $this->too_many_votes = true;
    }

    // Get votes distribution
    function get_results()
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('poll_vote',
            'poll_answer, count(1) votes',
            array('poll_id' => $this->ID),
            __METHOD__,
            array('GROUP BY' => '1')
        );
        $this->result = array_pad(array(), count($this->answers), 0);
        while ($row = $dbr->fetchRow($res))
            $this->result[$row['poll_answer'] ? $row['poll_answer']-1 : 'NONE'] += $row[1];
        $dbr->freeResult($res);
        $none = $this->result['NONE'];
        unset($this->result['NONE']);
        if ($none)
        {
            $this->result[] = $none;
            $this->answers[] = wfMsg('wikipoll-none-of-above');
        }
    }

    // Show results
    function html_results()
    {
        if (!$this->result)
            $this->get_results();
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
        $graph->values = $this->result;
        $graph->labels = $this->answers;
        $s = $graph->create();
        $s = str_replace("<table","\n<table",$s);
        $s = str_replace("<td","\n<td",$s);
        $s = str_replace("<tr","\n<tr",$s);
        return $s;
    }

    // Options
    function html_options()
    {
        global $wgUser;
        $str = '';
        foreach ($this->answers as $a)
            $str .= self::xelement('li', NULL, $a);
        $str = self::xelement('ul', NULL, $str);
        return $str;
    }

    // Total users voted + poll end date
    function html_total()
    {
        // "Total users voted" for authorized users
        $dbr = wfGetDB(DB_SLAVE);
        $n = $dbr->selectField('poll_vote', 'COUNT(DISTINCT poll_user)', array('poll_id' => $this->ID), __METHOD__);
        return $this->parse(wfMsg('wikipoll-voted-count', $n, $this->end));
    }

    // Poll form (POINTS mode)
    function html_form_points()
    {
        global $wgTitle;
        $action = $wgTitle->escapeLocalUrl("action=purge");
        $uv = $this->get_user_votes();
        $str = '';
        if ($this->points > 1)
        {
            $votes_rest = $this->points-count($uv);
            $str .= $this->parse(wfMsg('wikipoll-remaining', $votes_rest));
        }
        $i_voted = array();
        foreach ($uv as $n)
            $i_voted[$n]++;
        $block = '';
        foreach ($this->answers as $i => $label)
        {
            $form = Xml::hidden('poll-ID', $this->ID);
            $form .= Xml::hidden('answers', $i+1);
            $form .= Xml::submitButton('+', array('name' => 'vote', 'style' => 'color: blue; background-color: #e0e0e0; border: 1px outset gray'));
            $form .= '&nbsp;';
            $form .= $label;
            if ($i_voted[$i+1])
                $form .= $this->parse(wfMsgNoTrans('wikipoll-points', $i_voted[$i+1]));
            $form = self::xelement('form', array('action' => '#poll-'.$this->ID, 'method' => 'POST'), $form);
            $block .= self::xelement('li', NULL, $form);
        }
        $str .= self::xelement('ul', array('class' => 'wikipoll-alt'), $block);
        return $str;
    }

    // Poll form (CHECKS mode)
    function html_form_checks()
    {
        global $wgTitle;
        $action = $wgTitle->escapeLocalUrl("action=purge");
        $form = '';
        foreach ($this->answers as $i => $label)
        {
            $item = Xml::check('answers[]', false, array('value' => $i+1, 'id' => "c$this->ID-$i"));
            $item .= '&nbsp;' . self::xelement('label', array('for' => "c$this->ID-$i"), $label);
            $form .= self::xelement('li', NULL, $item);
        }
        $form = self::xelement('ul', array('class' => 'wikipoll-alt'), $form);
        $form = Xml::hidden('poll-ID', $this->ID) . $form;
        $form .= Xml::submitButton(wfMsg('wikipoll-submit'), array('name' => 'vote'));
        $form = self::xelement('form', array('action' => '#poll-'.$this->ID, 'method' => 'POST'), $form);
        return $form;
    }
}
