(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiSortingClickTracking = {
    attach: function (context, settings) {
      if (!settings.aiSorting || !settings.aiSorting.views) {
        return;
      }

      once('ai-sorting-click-tracking', '.view', context).forEach(function(view) {
        var viewIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-id-'));
        var viewId = viewIdClass ? viewIdClass.replace('view-id-', '') : 'unknown';

        if (settings.aiSorting.views[viewId]) {
          var viewSettings = settings.aiSorting.views[viewId];
          var nidUrlMap = viewSettings.nidUrlMap;
          var clickTrackingUrl = viewSettings.clickTrackingUrl;

          if (nidUrlMap && Object.keys(nidUrlMap).length > 0) {
            view.querySelectorAll('a').forEach(function(link) {
              var href = link.getAttribute('href');
              var nid = Object.keys(nidUrlMap).find(nid => nidUrlMap[nid] === href);
              if (nid) {
                link.dataset.nid = nid;
                link.addEventListener('click', function() {
                  navigator.sendBeacon(clickTrackingUrl, JSON.stringify({
                    nid: nid,
                    view_id: viewId,
                    display_id: viewSettings.displayId
                  }));
                });
              }
            });
          }
        }
      });
    }
  };
})(Drupal, once);