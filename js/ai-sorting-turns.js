(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiSortingTurns = {
    attach: function (context, settings) {
      console.log('AI Sorting Turns: attach() called', {context, settings});
      
      if (!settings.aiSorting || !settings.aiSorting.views) {
        console.log('AI Sorting Turns: No aiSorting settings found');
        return;
      }

      console.log('AI Sorting Turns: Found settings', settings.aiSorting);

      once('ai-sorting-turns', '.view', context).forEach(function(view) {
        console.log('AI Sorting Turns: Processing view', view);
        
        var viewIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-id-'));
        var viewId = viewIdClass ? viewIdClass.replace('view-id-', '') : 'unknown';
        
        console.log('AI Sorting Turns: Extracted viewId', {viewIdClass, viewId});

        if (settings.aiSorting.views[viewId]) {
          var viewSettings = settings.aiSorting.views[viewId];
          var experimentUuid = viewSettings.experimentUuid;
          var nids = viewSettings.nids;
          var rlEndpointUrl = viewSettings.rlEndpointUrl;

          console.log('AI Sorting Turns: Found view settings', {
            viewId,
            experimentUuid,
            nids,
            rlEndpointUrl
          });

          if (nids && nids.length > 0) {
            console.log('AI Sorting Turns: Setting up IntersectionObserver for', nids.length, 'nids');
            
            var observer = new IntersectionObserver(function(entries) {
              console.log('AI Sorting Turns: IntersectionObserver triggered', entries[0].isIntersecting);
              
              if (entries[0].isIntersecting) {
                console.log('AI Sorting Turns: View is intersecting, recording turns');
                
                // Create FormData for POST request to rl.php
                var formData = new FormData();
                formData.append('action', 'turns');
                formData.append('experiment_uuid', experimentUuid);
                formData.append('arm_ids', nids.join(','));

                console.log('AI Sorting Turns: Sending to', rlEndpointUrl, {
                  action: 'turns',
                  experiment_uuid: experimentUuid,
                  arm_ids: nids.join(',')
                });

                // Use sendBeacon for non-blocking request
                var result = navigator.sendBeacon(rlEndpointUrl, formData);
                console.log('AI Sorting Turns: sendBeacon result', result);
                
                observer.unobserve(view);
              }
            }, {threshold: 0.1});
            
            observer.observe(view);
            console.log('AI Sorting Turns: IntersectionObserver started');
          } else {
            console.log('AI Sorting Turns: No nids found');
          }
        } else {
          console.log('AI Sorting Turns: No settings found for viewId', viewId);
        }
      });
    }
  };
})(Drupal, once);