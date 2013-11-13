$(document).ready(function(){
    $('.getMails').click(function(){
        var $this = $(this);
        var $from = $this.parent().is('p') ? $this.parent().prev().prev() : $this.prev().prev();
        var id = $from.children('a').first().attr('name').substr('poll-'.length);
        var $load = $('<span></span>');
        $this.after($load);
        $load.css({'font-weight' : 'bold'});
        $load.text(mw.msg('loading'));
        var i = 0;
        var iID = setInterval(function(){
            i++;
            var text = mw.msg('loading');
            for (var j = 0; j < i; j++)
            {
                text += '.';
            }
            $load.text(text);
            if (i >= 3)
            {
                i =0;
            }
        }, 300);
        $this.hide();
        $.ajax({
            type: "GET",
            url: mw.util.wikiScript(),
            data: {
                action:'ajax',
                rs:'WikiPoll::AjaxExportList',
                rsargs:[id]
            },
            dataType: 'text',
            success: function(result){
                clearInterval(iID);
                $this.show();
                $load.remove();
                prompt(mw.msg('wikipoll-emails-copy'), result);
            },
            error : function(result){
                clearInterval(iID);
                $load.text(mw.msg('wikipoll-emails-error'));
                setTimeout(function(){
                    $this.show();
                    $load.remove();
                }, 5000);
            }
        });
    });
});