var korrioHelp = function() {

}

korrioHelp.prototype = {

    renderHelp:function(obj){

        var internal = this;

        $.mobile.loading('show', {
            text: 'Loading',
            textVisible: true,
            theme: 'h'
        });
        // console.log("-------- renderMobileHelpHome --------");

        $('.wid-page-leftmenu').empty();
        $('.wid-page-footer').empty();
        $('#coachesmanager').removeClass('active');
        $('#parentsplayers').removeClass('active');
        $('#fanshelp').removeClass('active');
        $('#korriofaq').removeClass('active');

        obj.render ('#page_mobile_help .wid-page-header', '#tpl_wid_page_header',
            {left: 'backarrow', title: 'Help', right: ''});

        obj.renderSlideoutMenu (obj, '#page_mobile_help');

        obj.render ('#page_mobile_help .wid-page-footer', '#tpl_wid_page_footer');


        $.mobile.changePage("#page_mobile_help");
        $("#page_mobile_help").trigger('pagecreate');

        $('#coachesmanager').off('click');
        $('#coachesmanager').on('click', function(){
            doTrackLink( 'event', 'help-coachesmanagers', obj, 'link' );
            $('#coachesmanager').addClass('active');
            internal.renderPersonnelFaq(obj);
        });

        $('#parentsplayers').off('click');
        $('#parentsplayers').on('click', function(){
            doTrackLink( 'event', 'help-parentsplayers', obj, 'link' );
            $('#parentsplayers').addClass('active');
            internal.renderPlayerFaq(obj);
        });

        $('#fanshelp').off('click');
        $('#fanshelp').on('click', function(){
            doTrackLink( 'event', 'help-fanshelp', obj, 'link' );
            $('#fanshelp').addClass('active');
            internal.renderFanFaq(obj);
        });

        $('#korriofaq').off('click');
        $('#korriofaq').on('click', function(){
            doTrackLink( 'event', 'help-korriofaq', obj, 'link' );
            $('#korriofaq').addClass('active');
            internal.renderKorrioFaq(obj);
        });

        $('.backarrow').off('click');
        $('.backarrow').on('click', function(e) {
            doTrackLink( 'event', 'help-back', obj, 'link' );
            $('span.ui-icon', this).addClass('ui-icon-back-active');
            obj.dashboard.renderMenu(obj);
        });

        $.mobile.loading('hide');

        //send event to Google Analytics
        doTrackLink( 'pageview', 'help', obj);
    },

    renderPersonnelFaq:function(obj){

        var internal = this;

        $.mobile.loading('show', {
            text: 'Loading',
            textVisible: true,
            theme: 'h'
        });

        internal.getPostData(obj, 'mobile-personnel', function(obj){

            // console.log("-------- renderMobileHelpPersonnel --------");
            // console.log(obj.data.help.personnel);
            $('.wid-page-footer').empty();

            obj.render ('#page_mobile_help_personnel .wid-page-header', '#tpl_wid_page_header',
                {left: 'backarrow', title: 'Coaches and Managers Help', right: ''});


            obj.render ('#page_mobile_help_personnel .wid-page-footer', '#tpl_wid_page_footer');

            var output='';
            $.each(obj.data.help.personnel.posts,function(key,val) {
                output+='<div data-role="collapsible" data-slug="'+val.slug+'">';
                output+='<h3>' + val.title + '</h3>';
                output+='<p>' + val.content + '</p>';
                output+='</div><!-- collapsible -->';
            });

            $('#page_mobile_help_personnel .helppostslist').html(output);

            $.mobile.changePage("#page_mobile_help_personnel");
            $("#page_mobile_help_personnel").trigger('pagecreate');


            $('.backarrow').off('click');
            $('.backarrow').on('click', function(e) {
                doTrackLink( 'event', 'help-coaches-managers-back', obj, 'link' );
                $('span.ui-icon', this).addClass('ui-icon-back-active');
                internal.renderHelp(obj);
            });
            $(".helppostslist .ui-collapsible-content a").each(function(){
                internal.helpBindHelpLink($(this), obj);
            });
            $(".helppostslist .ui-collapsible").each(function(){
                internal.helpBindEventTouch($(this), obj);
            });

            $.mobile.loading('hide');

            //send event to Google Analytics
            doTrackLink( 'pageview', 'help/coaches-managers', obj);
        });
    },

    renderPlayerFaq:function(obj){

        var internal = this;

        $.mobile.loading('show', {
            text: 'Loading',
            textVisible: true,
            theme: 'h'
        });

        internal.getPostData(obj, 'mobile-players', function(obj){

            // console.log("-------- renderMobileHelpPlayers --------");
            // console.log(obj.data.help.personnel);
            $('.wid-page-footer').empty();

            obj.render ('#page_mobile_help_players .wid-page-header', '#tpl_wid_page_header',
                {left: 'backarrow', title: 'Parents and Players Help', right: ''});

            obj.render ('#page_mobile_help_players .wid-page-footer', '#tpl_wid_page_footer');

            var output='';
            //console.log(obj.data.help.personnel.posts);
            $.each(obj.data.help.personnel.posts,function(key,val) {
                output+='<div data-role="collapsible" data-slug="'+val.slug+'">';
                output+='<h3>' + val.title + '</h3>';
                output+='<p>' + val.content + '</p>';
                output+='</div><!-- collapsible -->';
            });

            $('#page_mobile_help_players .helppostslist').html(output);

            $.mobile.changePage("#page_mobile_help_players");
            $("#page_mobile_help_players").trigger('pagecreate');


            $('.backarrow').off('click');
            $('.backarrow').on('click', function(e) {
                doTrackLink( 'event', 'help-parents-players-back', obj, 'link' );
                $('span.ui-icon', this).addClass('ui-icon-back-active');
                internal.renderHelp(obj);
            });

            $(".helppostslist .ui-collapsible-content a").each(function(){
                internal.helpBindHelpLink($(this), obj);
            });
            $(".helppostslist .ui-collapsible").each(function(){
                internal.helpBindEventTouch($(this), obj);
            });

            $.mobile.loading('hide');

            doTrackLink( 'pageview', 'help/parents-players', obj);
        });

    },

    renderFanFaq:function(obj){

        var internal = this;

        $.mobile.loading('show', {
            text: 'Loading',
            textVisible: true,
            theme: 'h'
        });

        internal.getPostData(obj, 'mobile-fans', function(obj){

            // console.log("-------- renderMobileHelpPlayers --------");
            // console.log(obj.data.help.personnel);
            $('.wid-page-footer').empty();

            obj.render ('#page_mobile_help_fans .wid-page-header', '#tpl_wid_page_header',
                {left: 'backarrow', title: 'Fans Help', right: ''});

            obj.render ('#page_mobile_help_fans .wid-page-footer', '#tpl_wid_page_footer');

            var output='';
            $.each(obj.data.help.personnel.posts,function(key,val) {
                output+='<div data-role="collapsible" data-slug="'+val.slug+'">';
                output+='<h3>' + val.title + '</h3>';
                output+='<p>' + val.content + '</p>';
                output+='</div><!-- collapsible -->';
            });

            $('#page_mobile_help_fans .helppostslist').html(output);

            $.mobile.changePage("#page_mobile_help_fans");
            $("#page_mobile_help_fans").trigger('pagecreate');


            $('.backarrow').off('click');
            $('.backarrow').on('click', function(e) {
                doTrackLink( 'event', 'help-fans-back', obj, 'link' );
                $('span.ui-icon', this).addClass('ui-icon-back-active');
                internal.renderHelp(obj);
            });

            $(".helppostslist .ui-collapsible-content a").each(function(){
                internal.helpBindHelpLink($(this), obj);
            });
            $(".helppostslist .ui-collapsible").each(function(){
                internal.helpBindEventTouch($(this), obj);
            });

            $.mobile.loading('hide');

            doTrackLink( 'pageview', 'help/fans', obj);
        });

    },

    renderKorrioFaq:function(obj){

        var internal = this;

        $.mobile.loading('show', {
            text: 'Loading',
            textVisible: true,
            theme: 'h'
        });

        internal.getPostData(obj, 'mobile-korrio', function(obj){

            // console.log("-------- renderMobileHelpKorrio --------");
            // console.log(obj.data.help.personnel);
            $('.wid-page-footer').empty();

            obj.render ('#page_mobile_help_korrio .wid-page-header', '#tpl_wid_page_header',
                {left: 'backarrow', title: 'About Korrio', right: ''});

            obj.render ('#page_mobile_help_korrio .wid-page-footer', '#tpl_wid_page_footer');

            var output='';
            $.each(obj.data.help.personnel.posts,function(key,val) {
                output+='<div data-role="collapsible" data-slug="'+val.slug+'">';
                output+='<h3>' + val.title + '</h3>';
                output+='<p>' + val.content + '</p>';
                output+='</div><!-- collapsible -->';
            });

            $('#page_mobile_help_korrio .helppostslist').html(output);

            $.mobile.changePage("#page_mobile_help_korrio");
            $("#page_mobile_help_korrio").trigger('pagecreate');


            $('.backarrow').off('click');
            $('.backarrow').on('click', function(e) {
                doTrackLink( 'event', 'help-korrio-faq-back', obj, 'link' );
                $('span.ui-icon', this).addClass('ui-icon-back-active');
                internal.renderHelp(obj);
            });

            $(".helppostslist .ui-collapsible-content a").each(function(){
                internal.helpBindHelpLink($(this), obj);
            });
            $(".helppostslist .ui-collapsible").each(function(){
                internal.helpBindEventTouch($(this), obj);
            });

            $.mobile.loading('hide');

            doTrackLink( 'pageview', 'help/korrio-faq', obj);

        });

    },

    getPostData:function(obj, type, callback){

        $.getJSON( 'http://www.korrio.com/support-community/support-article/support-category/'+type+'/?json=get_posts&post_type=support_article&count=100&callback=?', function(postdata) {

            obj.data.help.personnel = postdata;

            if (typeof(callback) == "function") {
                callback(obj);
            }

        }); //get JSON Data for Stories

    },

    helpBindEventTouch:function (element, obj) {
        element.off('tap');
        element.on('tap', function(event, ui) {
            if(element.hasClass('ui-collapsible-collapsed')) {
                //// console.log(element.attr('data-slug')+' is opening');
                doTrackLink( 'event', 'help-' + element.attr('data-slug'), obj, 'link' );
            }
        });
    },

    helpBindHelpLink:function (element, obj) {
        element.off('click');
        element.on('click', function(event, ui) {
            //console.log(element.attr('href')+' is opening');
            setTimeout(function() {
                window.open(element.attr('href'), '_blank', 'location=no,EnableViewPortScale=yes,closebuttoncaption=Return');
            }, 100);

            return false;

        });
    }

}