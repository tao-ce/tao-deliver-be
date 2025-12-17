// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2016 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License
define(['taoQtiItem/portableLib/jquery_2_1_1', 'taoQtiItem/portableLib/OAT/util/html'], function($, html){
    'use strict';

    function renderChoices(id, $container, config){

        var i,
            $li,
            level = parseInt(config.level) || 5,
            $ul = $container.find('ul.likert');
        
        //ensure that renderChoices() is idempotent
        $ul.empty();
        
        //add levels
        for(i = 1; i <= level; i++){

            $li = $('<li>', {'class' : 'likert'});
            $li.append($('<input>', {type : 'radio', name : id, value : i}));

            $ul.append($li);
        }
    }

    function renderLabels(id, $container, config){

        var $ul = $container.find('ul.likert');
        var $labelMin = $('<span>', {'class' : 'likert-label likert-label-min'}).html(config['label-min']);
        var $labelMax = $('<span>', {'class' : 'likert-label likert-label-max'}).html(config['label-max']);

        $ul.before($labelMin);
        $ul.after($labelMax);
    }

    return {
        render : function(id, container, config, assetManager){

            var $container = $(container);

            renderChoices(id, $container, config);
            renderLabels(id, $container, config, assetManager);
            
            //render rich text content in prompt
            html.render($container.find('.prompt'));
        },
        renderChoices : function(id, container, config){
            renderChoices(id, $(container), config);
        }
    };
});