(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiSortingRewards = {
    attach: function (context, settings) {
      console.log('AI Sorting Rewards: attach() called', {context, settings});
      
      if (!settings.aiSorting || !settings.aiSorting.views) {
        console.log('AI Sorting Rewards: No aiSorting settings found');
        return;
      }

      console.log('AI Sorting Rewards: Found settings', settings.aiSorting);

      once('ai-sorting-rewards', '.view', context).forEach(function(view) {
        console.log('AI Sorting Rewards: Processing view', view);
        
        var viewIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-id-'));
        var viewId = viewIdClass ? viewIdClass.replace('view-id-', '') : 'unknown';
        
        console.log('AI Sorting Rewards: Extracted viewId', {viewIdClass, viewId});

        if (settings.aiSorting.views[viewId]) {
          var viewSettings = settings.aiSorting.views[viewId];
          var experimentUuid = viewSettings.experimentUuid;
          var nidUrlMap = viewSettings.nidUrlMap;
          var rlEndpointUrl = viewSettings.rlEndpointUrl;

          console.log('AI Sorting Rewards: Found view settings', {
            viewId,
            experimentUuid,
            nidUrlMap,
            rlEndpointUrl
          });

          if (nidUrlMap && Object.keys(nidUrlMap).length > 0) {
            var links = view.querySelectorAll('a');
            console.log('AI Sorting Rewards: Found', links.length, 'links in view');
            
            links.forEach(function(link) {
              var href = link.getAttribute('href');
              var nid = Object.keys(nidUrlMap).find(nid => nidUrlMap[nid] === href);
              
              console.log('AI Sorting Rewards: Processing link', {href, nid});
              
              if (nid) {
                link.dataset.nid = nid;
                console.log('AI Sorting Rewards: Adding click listener to link for nid', nid);
                
                link.addEventListener('click', function() {
                  console.log('AI Sorting Rewards: Link clicked for nid', nid);
                  
                  // Create FormData for POST request to rl.php
                  var formData = new FormData();
                  formData.append('action', 'reward');
                  formData.append('experiment_uuid', experimentUuid);
                  formData.append('arm_id', nid);

                  console.log('AI Sorting Rewards: Sending to', rlEndpointUrl, {
                    action: 'reward',
                    experiment_uuid: experimentUuid,
                    arm_id: nid
                  });

                  // Use sendBeacon for non-blocking request
                  var result = navigator.sendBeacon(rlEndpointUrl, formData);
                  console.log('AI Sorting Rewards: sendBeacon result', result);
                });
              } else {
                console.log('AI Sorting Rewards: No nid found for href', href);
              }
            });
          } else {
            console.log('AI Sorting Rewards: No nidUrlMap found');
          }
        } else {
          console.log('AI Sorting Rewards: No settings found for viewId', viewId);
        }
      });
    }
  };
})(Drupal, once);