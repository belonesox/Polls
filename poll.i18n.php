<?php

$messages = array();

$messages['en'] = array(
    'wikipoll-desc'                      => 'A simple extension for inserting polls into articles.',
    'wikipoll-must-login'                => '<b><font color="red">Sorry, you must be logged in to view this poll.</font></b>',
    'wikipoll-must-login-to-vote'        => '<p><b><font color="red">You must be logged in to vote.</font></b></p>',
    'wikipoll-empty'                     => '<b><font color="red">Error: poll is empty.</font></b>',
    'wikipoll-submit'                    => 'Vote',
    'wikipoll-results-hidden-but-no-end' => '<b><font color="red">Error: poll results would be hidden forever, since HIDE_RESULTS were specified without poll end marker END_POLL YYYY-MM-DD.</font></b>',
    'wikipoll-remaining'                 => 'You have $1 point{{PLURAL:$1||s}} to vote.',
    'wikipoll-too-many-votes'            => '<b>Too many votes selected.</b> ',
    'wikipoll-voted-count'               => '<div style="display: inline-block; background-color: #7fffd4; border: 1px solid #0000ff; padding: 15px">
<div style="background-color: white">
* You have already answered to this poll.{{#if:$2|&nbsp;Poll results will be opened $2.|}}&nbsp;
* Total $1 user{{PLURAL:$1||s}} voted.
</div>
</div>',
);

$messages['ru'] = array(
    'wikipoll-desc'                      => 'Простое расширение для организации опросов/голосований на основе MediaWiki.',
    'wikipoll-must-login'                => '<b><font color="red">Извините, вы должны войти в систему, чтобы участвовать в этом голосовании или видеть его результаты.</font></b>',
    'wikipoll-must-login-to-vote'        => '<p><b><font color="red">Вы должны войти в систему, чтобы участвовать в этом голосовании.</font></b></p>',
    'wikipoll-empty'                     => '<b><font color="red">Ошибка: пустой опрос.</font></b>',
    'wikipoll-submit'                    => 'Проголосовать',
    'wikipoll-results-hidden-but-no-end' => '<b><font color="red">Ошибка: результаты будут скрыты навеки, ибо указано HIDE_RESULTS без маркера конца голосования END_POLL YYYY-MM-DD.</font></b>',
    'wikipoll-remaining'                 => 'Вы можете использовать $1 голос{{PLURAL:$1||ов|а}}.',
    'wikipoll-too-many-votes'            => '<b>Выбрано слишком много голосов.</b> ',
    'wikipoll-voted-count'               => '<div style="display: inline-block; background-color: #7fffd4; border: 1px solid #0000ff; padding: 15px; margin: 8px 0">
<div style="background-color: white">
* Вы уже ответили на этот опрос.{{#if:$2|&nbsp;Результаты будут доступны $2.|}}&nbsp;
* Всего проголосовал{{PLURAL:$1||о}} $1 человек.
</div>
</div>',
);
