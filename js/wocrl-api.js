jQuery(document).ready(function($){
    // javascript for fixed columns on league tables when scrolling
    //$(".main-table").clone(true).appendTo('#table-scroll').addClass('clone');

    // javascript for buttons to scroll through league tables
    $('button.right-scroll').click(function() {
        event.preventDefault();
        $(this).closest('.league_tables').find('.table-wrap').animate({
            scrollLeft: "+=100px"
        }, "fast");
    });

    $('button.left-scroll').click(function() {
        event.preventDefault();
        $(this).closest('.league_tables').find('.table-wrap').animate({
            scrollLeft: "-=100px"
        }, "fast");
    });

    // function highlight current user in league tables
    $('button.show_me').click(function(e) {
        e.preventDefault();
        var current_user_id = $(this).attr('data-current-user-id');
        $('.league_tables table tr').each(function(){
            var user_id = $(this).attr('data-user-id');
            if(user_id == current_user_id){
                $('html,body').animate({
                    scrollTop: $(this).offset().top - 10
                });
                $(this).addClass('highlight');
            }
        });
    });

    // toggle filter form for league tables
    $('button.filter_league_table').click(function(e) {
        var _search_form = $(this).closest('.filters').find('form#raceLeagueSearch');
        var _search_button = $(this).closest('.filters').find('button.search_league_table');
        var _filter_form = $(this).closest('.filters').find('form#raceLeagueFilter');

        // hide search form if it's visible
        if ( _search_form.is(':visible') ){
            _search_form.slideUp('fast');
        }

        // remove active class from search button if it has it
        if ( _search_button.hasClass('active') ){
            _search_button.removeClass('active');
        }

        // toggle filter form
        _filter_form.slideToggle('fast');
        $(this).toggleClass('active');
    });

    // toggle search form for league tables
    $('button.search_league_table').click(function(e) {
        var _search_form = $(this).closest('.filters').find('form#raceLeagueSearch');
        var _filter_form = $(this).closest('.filters').find('form#raceLeagueFilter');
        var _filter_button = $(this).closest('.filters').find('button.filter_league_table');

        // hide filter form if it's visible
        if ( _filter_form.is(':visible') ){
            _filter_form.slideUp('fast');
        }

        // remove active class from filter button if it has it
        if ( _filter_button.hasClass('active') ){
            _filter_button.removeClass('active');
        }

        // toggle search form
        _search_form.slideToggle('fast');
        $(this).toggleClass('active');
    });

    $('.league_tables .main-table').tablesorter();
});