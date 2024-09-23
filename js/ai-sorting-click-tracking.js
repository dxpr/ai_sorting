(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiSortingClickTracking = {
    attach: function (context, settings) {
      console.log('AI Sorting Click Tracking behavior attached');
      
      if (!settings.aiSorting || !settings.aiSorting.views) {
        console.warn('AI Sorting settings are not properly configured');
        return;
      }

      once('ai-sorting-click-tracking', '.view', context).forEach(function(view) {
        console.log('Found view element:', view);
        
        // Extract view ID from class names
        var viewIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-id-'));
        var viewId = viewIdClass ? viewIdClass.replace('view-id-', '') : 'unknown';
        
        console.log('Processing view:', viewId);

        if (settings.aiSorting.views[viewId]) {
          console.log('AI Sorting settings found for view:', viewId);
          var viewSettings = settings.aiSorting.views[viewId];
          var nidUrlMap = viewSettings.nidUrlMap;
          var clickTrackingUrl = viewSettings.clickTrackingUrl;
          console.log('NID URL Map:', nidUrlMap);
          console.log('Click Tracking URL:', clickTrackingUrl);

          if (nidUrlMap && Object.keys(nidUrlMap).length > 0) {
            console.log('Attaching click event listeners for view:', viewId);
            view.querySelectorAll('a').forEach(function(link) {
              var href = link.getAttribute('href');
              var nid = Object.keys(nidUrlMap).find(nid => nidUrlMap[nid] === href);
              if (nid) {
                link.dataset.nid = nid;
                link.addEventListener('click', function() {
                  console.log('Link clicked, sending request to track click');
                  navigator.sendBeacon(clickTrackingUrl, JSON.stringify({
                    nid: nid,
                    view_id: viewId,
                    display_id: viewSettings.displayId
                  }));
                });
              }
            });
          } else {
            console.warn('No NID URL Map found for view:', viewId);
          }
        } else {
          console.warn('No AI Sorting settings found for view:', viewId);
        }
      });

      if (context.querySelectorAll('.view').length === 0) {
        console.warn('No view elements found in the current context');
      }
    }
  };
})(Drupal, once);