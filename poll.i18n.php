<?php

$messages = array();

$messages['en'] = array(
    'wikipoll-must-login'  => '<b><font color="red">Sorry, you must be logged to view this poll.</font></b>',
    'wikipoll-remaining'   => 'You have $1 point{{PLURAL:$1||s}} to vote.',
    'wikipoll-voted-count' => '<div style="display: inline-block; background-color: #7fffd4; border: 1px solid #0000ff; padding: 15px">
<div style="background-color: white">
* You have already answered to this poll.{{#if:$2|&nbsp;Poll results will be opened $2.|}}&nbsp;
* Total $1 user{{PLURAL:$1||s}} voted.
</div>
</div>',
);

$messages['ru'] = array(
    'wikipoll-must-login'  => '<b><font color="red">Извините, вы должны войти в систему, чтобы участвовать в этом голосовании или видеть его результаты.</font></b>',
    'wikipoll-remaining'   => 'Вы можете использовать $1 голос{{PLURAL:$1||ов}}.',
    'wikipoll-voted-count' => '<div style="display: inline-block; background-color: #7fffd4; border: 1px solid #0000ff; padding: 15px">
<div style="background-color: white">
* Вы уже ответили на этот опрос.{{#if:$2|&nbsp;Результаты будут доступны $2.|}}&nbsp;
* Всего проголосовал{{PLURAL:$1||о}} $1 человек.
</div>
</div>',
);
