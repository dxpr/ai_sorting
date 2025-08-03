(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiSortingRewards = {
    attach: function (context, settings) {
      if (!settings.aiSorting || !settings.aiSorting.views) {
        return;
      }

      once('ai-sorting-rewards', '.view', context).forEach(function(view) {
        var viewIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-id-'));
        var viewId = viewIdClass ? viewIdClass.replace('view-id-', '') : 'unknown';

        if (settings.aiSorting.views[viewId]) {
          var viewSettings = settings.aiSorting.views[viewId];
          var experimentUuid = viewSettings.experimentUuid;
          var nidUrlMap = viewSettings.nidUrlMap;
          var rlEndpointUrl = viewSettings.rlEndpointUrl;

          if (nidUrlMap && Object.keys(nidUrlMap).length > 0) {
            var links = view.querySelectorAll('a');
            
            links.forEach(function(link) {
              var href = link.getAttribute('href');
              var nid = Object.keys(nidUrlMap).find(nid => nidUrlMap[nid] === href);
              
              if (nid) {
                link.dataset.nid = nid;
                
                link.addEventListener('click', function() {
                  // Create FormData for POST request to rl.php
                  var formData = new FormData();
                  formData.append('action', 'reward');
                  formData.append('experiment_uuid', experimentUuid);
                  formData.append('arm_id', nid);

                  // Use sendBeacon for non-blocking request
                  navigator.sendBeacon(rlEndpointUrl, formData);
                });
              }
            });
          }
        }
      });
    }
  };
})(Drupal, once);