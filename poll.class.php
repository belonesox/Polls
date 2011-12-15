<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * @author Stas Fomin <stas-fomin@yandex.ru>
 * @author Vitaliy Filippov <vitalif@mail.ru>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

class SpecialPolls extends SpecialPage
{
    static $curId;
    static $curPoll;
    function __construct()
    {
        SpecialPage::SpecialPage('Polls', 'viewpolls');
    }
    function execute($par)
    {
        global $wgRequest, $wgOut, $wgUser, $wgParser, $wgTitle;
        if (!$this->userCanExecute($wgUser))
        {
            $this->displayRestrictionError();
            return;
        }
        if (($p = strrpos($par, '/')) !== false)
        {
            $page = Title::newFromText(substr($par, 0, $p));
            $id = substr($par, $p+1);
        }
        else
        {
            $page = Title::newFromText($wgRequest->getVal('pollpage'));
            $id = $wgRequest->getVal('id');
        }
        if (preg_match('/\W/', $id) || !$page || !$page->exists())
        {
            $wgOut->setPageTitle(wfMsg('wikipoll-no-id-title'));
            $wgOut->addHTML(wfMsgNoTrans(
                'wikipoll-no-id-text',
                htmlspecialchars($wgTitle->getLocalUrl()),
                htmlspecialchars($page ? $page->getPrefixedText() : ''),
                htmlspecialchars($id)
            ));
            return;
        }
        if (!$par)
        {
            $wgOut->redirect(Title::newFromText($wgTitle->getPrefixedText().'/'.$page.'/'.$id)->getFullUrl());
            return;
        }
        wfLoadExtensionMessages('WikiPoll');
        $page = new Article($page);
        self::$curId = $id;
        $wgParser->disableCache();
        $wgParser->setHook('poll', 'SpecialPolls::adminPoll');
        $options = ParserOptions::newFromUser($wgUser);
        $wgParser->parse($page->getContent(), $page->getTitle(), $options);
        if (!self::$curPoll)
        {
            $wgOut->setPageTitle(wfMsg('wikipoll-not-found-title'));
            $wgOut->addHTML(wfMsgNoTrans(
                'wikipoll-not-found-text',
                htmlspecialchars($wgTitle->getLocalUrl()),
                htmlspecialchars($page->getTitle()->getPrefixedText()),
                htmlspecialchars($id)
            ));
            return;
        }
        $id = self::$curPoll->ID;
        $wgOut->setPageTitle(wfMsg('wikipoll-admin-title', $page->getTitle()->getPrefixedText(), $id));
        $wgOut->addHTML(self::$curPoll->html_admin());
    }
    static function adminPoll($input, $attr, $parser)
    {
        $poll = WikiPoll::newFromText($parser, $input);
        if (is_object($poll) && (!self::$curPoll || $poll->ID == self::$curId))
            self::$curPoll = $poll;
        return '';
    }
}

class WikiPoll
{
    const POLL_UNAUTH = 0;
    const POLL_AUTH_VOTE = 1;
    const POLL_AUTH_DISPLAY = 2;

    var $revote = false, $open = false;
    var $ID = NULL, $uniqueID = false, $unsafe = false;
    var $is_checks = true, $points = 0, $end = NULL;
    var $authorized = 0, $hide_results = false, $restrict_ip = false;
    var $emailvotes = false;

    var $username, $userip;
    var $user_votes, $too_many_votes;
    var $result;

    var $question, $answers;

    var $parser, $parserOptions;

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
        $poll->handle_postdata($parser->getTitle());
        return $poll->render();
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
            elseif (preg_match('/^VOTES(?:_TO_|2)EMAIL\D+(\d+)/', $line, $m))
            {
                /* Send email to page watchers when there are at least N votes */
                $self->emailvotes = intval($m[1]);
            }
            elseif (preg_match('/^UNSAFE[\s_]*ID=(\S+)$/s', $line, $m))
            {
                /* Unsafe poll, allows to overwrite answers later */
                $unsafe_name = preg_match('/\W/', $m[1]) || strlen($m[1]) > 31;
                $self->unsafe = true;
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
            elseif ($line == 'OPEN')
            {
                /* Display all voters */
                $self->open = true;
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
        if ($self->emailvotes < 0 || $self->hide_results && date('Y-m-d') < $self->end)
            $self->emailvotes = false;
        return $self;
    }

    // return HTML for question and anchor
    function html_question()
    {
        $html = '<p><a name="poll-'.$this->ID.'" title="ID: '.$this->ID.'"><b>'.$this->question.'</b></a></p>';
        return $html;
    }

    // return HTML code of rendered poll / form
    function render()
    {
        global $wgUser;
        $html = '';
        if ($this->too_many_votes)
            $html .= wfMsg('wikipoll-too-many-votes');
        $html .= $this->html_question();
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
        /* Show revote link after results */
        if ($this->revote)
            $html .= '<p><a href="?poll-ID='.$this->ID.'&recall=1#poll-'.$this->ID.'">'.wfMsg('wikipoll-recall').'</a></p>';
        return $html;
    }

    // SQL condition to select user votes
    function user_where($db)
    {
        return 'poll_user='.$db->addQuotes($this->username).($this->restrict_ip ? ' OR poll_ip='.$db->addQuotes($this->userip) : '');
    }

    // Handle POST data, for poll from page $title
    function handle_postdata($title)
    {
        if (empty($_REQUEST['poll-ID']) || $_REQUEST['poll-ID'] != $this->ID)
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
            header("Location: ".$title->getFullUrl()."#poll-".$this->ID);
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
            // If there is more than VOTES_TO_EMAIL, notify watchers
            if ($this->emailvotes !== false && count($votes)+count($uv) >= $this->emailvotes)
                $this->notify(count($votes)+count($uv), $title);
            header("Location: ".$title->getFullUrl()."#poll-".$this->ID);
            exit;
        }
        elseif ($uv)
            $this->too_many_votes = true;
    }

    // Notify $title's watchers about this poll by email
    function notify($nvotes, $title)
    {
        $from = new MailAddress($wgPasswordSender, 'WikiPolls');
        $ntext = $this->parse(wfMsgNoTrans('wikipoll-email-nvotes', $nvotes));
        $subject = wfMsg('wikipoll-email-subject', $this->question, mb_strtolower($ntext));
        $body = wfMsgNoTrans(
            defined('MEDIAWIKI_HAVE_HTML_EMAIL') ? 'wikipoll-email-html' : 'wikipoll-email-text',
            $ntext,
            $this->question,
            $title->getPrefixedText(),
            $title->getFullUrl(),
            $this->emailvotes,
            $this->html_results()
        );
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select(array('watchlist', 'user'), 'user.*', array(
            'wl_namespace' => $title->getNamespace(),
            'wl_title' => $title->getDBkey(),
            'user_id=wl_user',
            'user_email!=\'\'',
            'user_email_authenticated IS NOT NULL',
        ), __METHOD__);
        foreach ($res as $row)
            UserMailer::send(new MailAddress($row->user_email), $from, $subject, $body);
    }

    // Get votes distribution and voters
    function get_results()
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('poll_vote',
            'poll_answer, poll_user, count(1) votes',
            array('poll_id' => $this->ID),
            __METHOD__,
            array('GROUP BY' => 'poll_answer, poll_user')
        );
        $this->result = array_pad(array(), count($this->answers), 0);
        $this->voters = array();
        $this->total = 0;
        foreach ($res as $row)
        {
            $a = $row->poll_answer ? $row->poll_answer-1 : 'NONE';
            if (!isset($this->result[$a]))
                $this->result[$a] = 0;
            $this->result[$a] += $row->votes;
            $this->total += $row->votes;
            $this->voters[$a][$row->poll_user] = $row->votes;
        }
        $dbr->freeResult($res);
        if (!empty($this->result['NONE']))
        {
            $this->voters[count($this->result)] = $this->voters['NONE'];
            $this->result[] = $this->result['NONE'];
            $this->answers[] = wfMsg('wikipoll-none-of-above');
            unset($this->result['NONE']);
            unset($this->voters['NONE']);
        }
    }

    // Show results
    function html_results()
    {
        if (!$this->result)
            $this->get_results();
        $s = '';
        $max = max($this->result);
        foreach ($this->answers as $i => $a)
        {
            $perc = round($this->result[$i]*100/$this->total);
            $width = round($this->result[$i]*100/$max);
            $tr = '<td style="background-color: #fffacd; border: 1px outset #ffea95; padding: 0 2px">'.$a.'</td>';
            $tr .= '<td style="background-color: #fff8dc; border: 1px outset #ffea95; padding: 0 2px">'.$this->result[$i].'</td>';
            $tr .= '<td style="padding-right: 1em"><table style="height: 100%"><tr>' .
                '<td style="width: '.$width.'px; border: 1px outset #ffea95; background: #ffcb00">' .
                '</td><td>'.$perc.'%</td></tr></table></td>';
            if ($this->open && !empty($this->voters[$i]))
            {
                foreach ($this->voters[$i] as $v => &$n)
                    $n = htmlspecialchars($v) . ($n > 1 ? ' ('.$n.')' : '');
                $tr .= '<td style="color: #666; padding-right: 0.3em">'.implode(', ', $this->voters[$i]).'</td>';
            }
            $s .= '<tr>'.$tr.'</tr>';
        }
        $s = '<table style="background-color: white; border: 1px solid #a0c0ff" cellspacing="2" cellpadding="0">'.$s.'</table>';
        $s = '<table style="background-color: #c0f0ff; border: 1px solid #0000ff"><tr><td style="padding: 15px">'.$s.'</td></tr></table>'; // was 7fffd4
        return $s;
    }

    // Options
    function html_options()
    {
        global $wgUser;
        $str = '';
        foreach ($this->answers as $a)
            $str .= self::xelement('li', NULL, $a);
        $str = self::xelement('ol', NULL, $str);
        return $str;
    }

    // Total users voted + poll end date
    function html_total()
    {
        // "Total users voted" for authorized users
        $dbr = wfGetDB(DB_SLAVE);
        $n = $dbr->selectField('poll_vote', 'COUNT(DISTINCT poll_user)', array('poll_id' => $this->ID), __METHOD__);
        return $this->parse(wfMsgNoTrans('wikipoll-voted-count', $n, $this->end));
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
            if ($this->open)
                $str .= wfMsgNoTrans('wikipoll-warning-open').' ';
            $str .= $this->parse(wfMsgNoTrans('wikipoll-remaining', $votes_rest));
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
            if (!empty($i_voted[$i+1]))
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

    // Poll text in admin mode (with voters table)
    function html_admin()
    {
        $this->get_results();
        $html = '';
        $html .= $this->html_flags();
        $html .= $this->html_question();
        $open = $this->open;
        $this->open = true;
        $html .= $this->html_results();
        $this->open = $open;
        $html .= $this->html_votes_table();
        return $html;
    }

    // Poll options (metadata)
    function html_flags()
    {
        global $wgUser;
        $flags = array();
        if ($this->parser && ($t = $this->parser->mTitle))
            $flags[] = array('title', $wgUser->getSkin()->link($t));
        $flags[] = array('id', $this->ID);
        $flags[] = $this->unsafe ? 'unsafe' : 'safe';
        $flags[] = $this->uniqueID ? 'unique' : 'global';
        $flags[] = $this->is_checks ? 'checks' : ($this->points == 1 ? 'alternative' : array('points', $this->points));
        $flags[] = !$this->authorized ? 'unauth' : ($this->authorized == 1 ? 'auth-vote' : 'auth-display');
        $flags[] = $this->restrict_ip ? 'ip-enabled' : 'ip-disabled';
        $flags[] = $this->open ? 'open' : 'closed';
        $flags[] = $this->revote ? 'revote' : 'no-revote';
        if ($this->end)
            $flags[] = array($this->hide_results ? 'end-hidden' : 'end-shown', $this->end);
        $html = '';
        foreach ($flags as $f)
        {
            if (!is_array($f))
                $f = array($f);
            $html .= '<li>'.wfMsgNoTrans('wikipoll-flags-'.$f[0], $f[1]).'</li>';
        }
        $html = '<ul>'.$html.'</ul>';
        return $html;
    }

    // Poll result table with all user names, IPs, vote dates...
    function html_votes_table()
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select(
            'poll_vote', '*', array('poll_id' => $this->ID), __METHOD__,
            array('ORDER BY' => 'poll_date')
        );
        $cols = array('date', 'user', 'ip', 'answer');
        $html = '<tr>';
        foreach ($cols as $col)
            $html .= '<th>'.wfMsg("wikipoll-col-$col").'</th>';
        $html .= '</tr>';
        $anon = wfMsg('wikipoll-anonymous');
        foreach ($res as $row)
        {
            if ($row->ip == $row->user)
                $row->user = $anon;
            $tr = '';
            foreach ($cols as $col)
            {
                $col = "poll_$col";
                $tr .= '<td>'.htmlspecialchars($row->$col).'</td>';
            }
            $html .= '<tr>'.$tr.'</tr>';
        }
        $html = '<table class="wikitable">'.$html.'</table>';
        return $html;
    }
}
