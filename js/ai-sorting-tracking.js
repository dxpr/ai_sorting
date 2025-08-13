(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiSortingTracking = {
    attach: function (context, settings) {
      if (!settings.aiSorting || !settings.aiSorting.views) {
        return;
      }

      once('ai-sorting-tracking', '.view', context).forEach(function(view) {
        var viewIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-id-'));
        var viewId = viewIdClass ? viewIdClass.replace('view-id-', '') : 'unknown';

        if (!settings.aiSorting.views[viewId]) {
          return;
        }

        var viewSettings = settings.aiSorting.views[viewId];
        var experimentId = viewSettings.experimentId;
        var entityIds = viewSettings.entityIds;
        var entityUrlMap = viewSettings.entityUrlMap;
        var rlEndpointUrl = viewSettings.rlEndpointUrl;

        // Fail hard if required data is missing
        if (!experimentId || !rlEndpointUrl) {
          throw new Error('AI Sorting: Missing required experiment data (experimentId or rlEndpointUrl)');
        }

        // Track turns (when view becomes visible)
        if (entityIds && entityIds.length > 0) {
          var observer = new IntersectionObserver(function(entries) {
            if (entries[0].isIntersecting) {
              // Create FormData for POST request to rl.php
              var formData = new FormData();
              formData.append('action', 'turns');
              formData.append('experiment_id', experimentId);
              formData.append('arm_ids', entityIds.join(','));

              // Use sendBeacon for non-blocking request
              navigator.sendBeacon(rlEndpointUrl, formData);
              
              observer.unobserve(view);
            }
          }, {threshold: 0.1});
          
          observer.observe(view);
        }

        // Track rewards (when links are clicked)
        if (entityUrlMap && Object.keys(entityUrlMap).length > 0) {
          var links = view.querySelectorAll('a');
          
          links.forEach(function(link) {
            var href = link.getAttribute('href');
            var entityId = Object.keys(entityUrlMap).find(id => entityUrlMap[id] === href);
            
            if (entityId) {
              link.dataset.entityId = entityId;
              
              link.addEventListener('click', function() {
                // Create FormData for POST request to rl.php
                var formData = new FormData();
                formData.append('action', 'reward');
                formData.append('experiment_id', experimentId);
                formData.append('arm_id', entityId);

                // Use sendBeacon for non-blocking request
                navigator.sendBeacon(rlEndpointUrl, formData);
              });
            }
          });
        }
      });
    }
  };
})(Drupal, once);