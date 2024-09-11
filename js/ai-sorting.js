/**
 * @file
 * Provides AI Sorting functionality for views.
 */

(function (Drupal, once) {
    'use strict';
  
    /**
     * Attaches the AI Sorting behavior to views.
     *
     * @type {Drupal~behavior}
     *
     * @prop {Drupal~behaviorAttach} attach
     *   Attaches the AI Sorting behavior.
     */
    Drupal.behaviors.aiSorting = {
      attach: function (context, settings) {
        once('ai-sorting', '.view-display-id-page', context).forEach(function(view) {
          var viewId = view.id;
          if (settings.aiSorting && settings.aiSorting.views && settings.aiSorting.views[viewId]) {
            var viewSettings = settings.aiSorting.views[viewId];
            var nids = viewSettings.nids;
            var incrementTrialsUrl = viewSettings.incrementTrialsUrl;
  
            if (nids.length > 0) {
              var observer = new IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting) {
                  fetch(incrementTrialsUrl, {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/json',
                      'X-CSRF-Token': settings.aiSorting.csrfToken
                    },
                    body: JSON.stringify({nids: nids})
                  })
                  .then(response => response.json())
                  .then(data => {
                    if (data.success) {
                      console.log('AI Sorting trials incremented for view: ' + viewId);
                    }
                  })
                  .catch(error => console.error('Error:', error));
                  observer.unobserve(view);
                }
              }, {threshold: 0.1});
  
              observer.observe(view);
            }
          }
        });
      }
    };
  })(Drupal, once);