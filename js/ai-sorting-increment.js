(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiSortingIncrement = {
    attach: function (context, settings) {
      if (!settings.aiSorting || !settings.aiSorting.views) {
        return;
      }

      once('ai-sorting-increment', '.view', context).forEach(function(view) {
        var viewIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-id-'));
        var viewId = viewIdClass ? viewIdClass.replace('view-id-', '') : 'unknown';

        if (settings.aiSorting.views[viewId]) {
          var viewSettings = settings.aiSorting.views[viewId];
          var nids = viewSettings.nids;
          var incrementTrialsUrl = viewSettings.incrementTrialsUrl;

          if (nids && nids.length > 0) {
            var observer = new IntersectionObserver(function(entries) {
              if (entries[0].isIntersecting) {
                navigator.sendBeacon(incrementTrialsUrl, JSON.stringify({nids: nids}));
                observer.unobserve(view);
              }
            }, {threshold: 0.1});
            observer.observe(view);
          }
        }
      });

      if (context.querySelectorAll('.view').length === 0) {
        // No view elements found in the current context
      }
    }
  };
})(Drupal, once);