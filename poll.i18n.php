<?php

$messages = array();

$messages['en'] = array(
    'wikipoll-desc'                     => 'A simple extension for inserting polls into articles.',
    'wikipoll-must-login'               => '<b><font color="red">Sorry, you must be logged in to view this poll.</font></b>',
    'wikipoll-must-login-to-vote'       => '<p><b><font color="red">You must be logged in to vote.</font></b></p>',
    'wikipoll-empty'                    => '<b><font color="red">Error: poll is empty.</font></b>',
    'wikipoll-submit'                   => 'Vote',
    'wikipoll-results-hidden-but-no-end' => '<b><font color="red">Error: poll results will be hidden forever, because HIDE_RESULTS are specified without poll end marker END_POLL YYYY-MM-DD.</font></b>',
    'wikipoll-remaining'                => 'You have $1 point{{PLURAL:$1||s}} to vote.',
    'wikipoll-too-many-votes'           => '<b>Too many votes selected.</b> ',
    'wikipoll-none-of-above'            => 'None of the above',
    'wikipoll-recall'                   => 'Recall vote',
    'wikipoll-voted-count'              => '<div style="display: inline-block; background-color: #7fffd4; border: 1px solid #0000ff; padding: 15px">
<div style="background-color: white">
* You have already answered to this poll.{{#if:$2|&nbsp;Poll results will be opened $2.|}}&nbsp;
* Total $1 user{{PLURAL:$1||s}} voted.
</div>
</div>',
    'wikipoll-points'                   => '<span style="color: #0080ff"> ($1 point{{PLURAL:$1||s}} used)</span>',
    'wikipoll-warning-open'             => '<span style="color: #f00; font-weight: bold">Warning: This poll is configured as open. It will display all voters\' user names in the results.</span>',

    'wikipoll-no-id-title'              => 'Poll ID not specified',
    'wikipoll-no-id-text'               =>
'You need to specify poll page and, if there is more than one poll on the page, its ID to view options and results in the administration mode:

<form action="$1" method="POST">
Page: <input type="text" name="pollpage" value="$2" />
ID: <input type="text" name="id" value="$3" /> (optional)
<input type="submit" value="View" />
</form>',
    'wikipoll-not-found-title'          => 'Poll with such ID not found',
    'wikipoll-not-found-text'           =>
'Poll with this ID is not found on the specified page. Please, specify other page or ID:

<form action="$1" method="POST">
Page: <input type="text" name="pollpage" value="$2" />
ID: <input type="text" name="id" value="$3" /> (optional)
<input type="submit" value="View" />
</form>',

    'wikipoll-view-results'             => 'View results &rarr;',
    'polls'                             => 'View WikiPolls',
    'wikipoll-admin-title'              => 'Viewing WikiPoll: $1 / $2',

    'wikipoll-flags-title'              => 'Page: $1',
    'wikipoll-flags-id'                 => 'ID: $1',
    'wikipoll-flags-unsafe'             => 'Unsafe: allows definition change',
    'wikipoll-flags-safe'               => 'Safe: definition change generates new poll',
    'wikipoll-flags-unique'             => 'Unique: same definition inserts new poll on different page',
    'wikipoll-flags-global'             => 'Global: same definition inserts same poll on different page',
    'wikipoll-flags-checks'             => 'CHECKS mode: user can vote for any number of different options',
    'wikipoll-flags-alternative'        => 'ALTERNATIVE mode: user can vote for only one selected option',
    'wikipoll-flags-points'             => 'POINTS $1 mode: user can cast $1 votes for any options',
    'wikipoll-flags-unauth'             => 'Unauthorized: Allows anonymous votes',
    'wikipoll-flags-auth-vote'          => 'Authorized: does not allow anonymous votes, but displays results',
    'wikipoll-flags-auth-display'       => 'Authorized display: allows voting and results display only for authorized users',
    'wikipoll-flags-ip-enabled'         => 'IP restriction enabled. Users can vote only one time from one IP address',
    'wikipoll-flags-ip-disabled'        => 'IP restriction disabled. Users can vote many times from one IP address',
    'wikipoll-flags-open'               => 'Open: voter names are displayed in results',
    'wikipoll-flags-closed'             => 'Closed: voter names can be viewed only in administration',
    'wikipoll-flags-revote'             => 'Allows vote recall',
    'wikipoll-flags-no-revote'          => 'Does not allow vote recall',
    'wikipoll-flags-end-shown'          => 'Poll ends: $1 (results shown)',
    'wikipoll-flags-end-hidden'         => 'Poll ends: $1 (until this date results are hidden)',

    'wikipoll-col-date'                 => 'Time',
    'wikipoll-col-user'                 => 'User',
    'wikipoll-anonymous'                => 'Anonymous',
    'wikipoll-col-ip'                   => 'IP address',
    'wikipoll-col-answer'               => 'Option number',

    'wikipoll-email-subject'            => '[WikiPoll] $1 - $2!',
    'wikipoll-email-nvotes'             => '{{PLURAL:$1|There is $1 vote|There are $1 votes}}',
    'wikipoll-email-html'               =>
'<body>
<p>$1 to poll "$2" on the page <a href="$4">$3</a>, which exceeds or is equal to email notification limit ($5).</p>
<p>The results are:</p>
$6
<p><i>You receive this message because you\'re watching the page <a href="$4">$3</a>.</i></p>
</body>',
    'wikipoll-email-text'               => '$1 to poll "$2" on the page "$3", which exceeds or is equal to email notification limit ($5).
Check poll results on the page: $4
You receive this message because you\'re watching the page.',
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
    'wikipoll-none-of-above'             => 'Ничего из перечисленного',
    'wikipoll-recall'                    => 'Отозвать свой голос',
    'wikipoll-voted-count'               => '<div style="display: inline-block; background-color: #7fffd4; border: 1px solid #0000ff; padding: 15px; margin: 8px 0">
<div style="background-color: white">
* Вы уже ответили на этот опрос.{{#if:$2|&nbsp;Результаты будут доступны $2.|}}&nbsp;
* Всего проголосовал{{PLURAL:$1||о}} $1 человек.
</div>
</div>',
    'wikipoll-points'                   => '<span style="color: #0080ff"> (отдан{{PLURAL:$1||о}} $1 голос{{PLURAL:$1||ов|а}})</span>',
    'wikipoll-warning-open'             => '<span style="color: #f00; font-weight: bold">Внимание: опрос открытый. Имена всех голосующих будут раскрыты в результатах.</span>',

    'wikipoll-no-id-title'              => 'ID голосования не указан',
    'wikipoll-no-id-text'               =>
'Для просмотра параметров и результатов голосования в режиме администрирования необходимо корректно указать страницу и ID голосования:

<form action="$1" method="POST">
Страница: <input type="text" name="pollpage" value="$2" />
ID: <input type="text" name="id" value="$3" />
<input type="submit" value="Просмотреть" />
</form>',
    'wikipoll-not-found-title'          => 'Голосование с таким ID не найдено',
    'wikipoll-not-found-text'           =>
'Голосование с таким ID не найдено на указанной странице. Укажите другую страницу:

<form action="$1" method="POST">
Страница: <input type="text" name="pollpage" value="$2" />
ID: <input type="text" name="id" value="$3" />
<input type="submit" value="Просмотреть" />
</form>',

    'wikipoll-view-results'             => 'Результаты &rarr;',
    'polls'                             => 'Просмотр ВикиГолосований',
    'wikipoll-admin-title'              => 'Просмотр голосования: $1 / $2',

    'wikipoll-flags-title'              => 'Страница: $1',
    'wikipoll-flags-unsafe'             => 'Небезопасный: позволяет менять определение',
    'wikipoll-flags-safe'               => 'Безопасный: смена определения генерирует новый опрос',
    'wikipoll-flags-unique'             => 'Уникальный: такое же определение на другой странице сгенерирует новый опрос',
    'wikipoll-flags-global'             => 'Глобальный: такое же определение вставит этот же опрос на любую страницу',
    'wikipoll-flags-checks'             => 'Режим CHECKS: можно проголосовать за любое число различных вариантов',
    'wikipoll-flags-alternative'        => 'Режим ALTERNATIVE: можно проголосовать только один раз, за один вариант',
    'wikipoll-flags-points'             => 'Режим POINTS $1: можно отдать $1 голоса(ов) за любые варианты',
    'wikipoll-flags-unauth'             => 'Неавторизованный: разрешено анонимное голосование',
    'wikipoll-flags-auth-vote'          => 'Авторизованный: запрещено анонимное голосование, но результаты отображаются',
    'wikipoll-flags-auth-display'       => 'Показ авторизованным: голосование и показ результатов разрешены только зарегистрированным пользователям',
    'wikipoll-flags-ip-enabled'         => 'Ограничение по IP активно, с одного IP-адреса можно голосовать только один раз',
    'wikipoll-flags-ip-disabled'        => 'Ограничение по IP неактивно, с одного IP-адреса можно голосовать любое число раз',
    'wikipoll-flags-open'               => 'Открытое голосование: имена всех голосующих отображаются в результатах',
    'wikipoll-flags-closed'             => 'Закрытое голосование: имена голосующих можно посмотреть только в режиме администрирования',
    'wikipoll-flags-revote'             => 'Разрешён отзыв голоса и повторное голосование',
    'wikipoll-flags-no-revote'          => 'Отзыв голоса запрещён, повторно голосовать нельзя',
    'wikipoll-flags-end-shown'          => 'Опрос кончается $1 (результаты показаны)',
    'wikipoll-flags-end-hidden'         => 'Опрос кончается $1 (до этого момента результаты скрыты)',

    'wikipoll-col-date'                 => 'Время',
    'wikipoll-col-user'                 => 'Пользователь',
    'wikipoll-anonymous'                => 'Анонимный',
    'wikipoll-col-ip'                   => 'IP-адрес',
    'wikipoll-col-answer'               => 'Номер ответа',

    'wikipoll-email-nvotes'             => '{{PLURAL:$1|Набран $1 голос|Набрано $1 голоса|Набрано $1 голосов}}',
    'wikipoll-email-html'               =>
'<body>
<p>$1 к опросу "$2" на странице <a href="$4">$3</a>, что больше или равно заданному лимиту для оповещения ($5).</p>
<p>Результаты:</p>
$6
<p><i>Вы получили это сообщение, потому что следите за страницей <a href="$4">$3</a>.</i></p>
</body>',
    'wikipoll-email-text'               => '$1 к опросу "$2" на странице "$3", что больше или равно заданному лимиту для оповещения ($5).
Результаты можно просмотреть по ссылке: $4
Вы получили это сообщение, потому что следите за вышеуказанной страницей.',
);
