(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiSortingTurns = {
    attach: function (context, settings) {
      if (!settings.aiSorting || !settings.aiSorting.views) {
        return;
      }

      once('ai-sorting-turns', '.view', context).forEach(function(view) {
        var viewIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-id-'));
        var viewId = viewIdClass ? viewIdClass.replace('view-id-', '') : 'unknown';

        if (settings.aiSorting.views[viewId]) {
          var viewSettings = settings.aiSorting.views[viewId];
          var experimentUuid = viewSettings.experimentUuid;
          var nids = viewSettings.nids;
          var rlEndpointUrl = viewSettings.rlEndpointUrl;

          if (nids && nids.length > 0) {
            var observer = new IntersectionObserver(function(entries) {
              if (entries[0].isIntersecting) {
                // Create FormData for POST request to rl.php
                var formData = new FormData();
                formData.append('action', 'turns');
                formData.append('experiment_uuid', experimentUuid);
                formData.append('arm_ids', nids.join(','));

                // Use sendBeacon for non-blocking request
                navigator.sendBeacon(rlEndpointUrl, formData);
                
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