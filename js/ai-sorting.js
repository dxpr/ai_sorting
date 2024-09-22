(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiSorting = {
    attach: function (context, settings) {
      console.log('AI Sorting behavior attached');
      
      console.log('AI Sorting settings:', settings.aiSorting);

      if (!settings.aiSorting || !settings.aiSorting.views) {
        console.warn('AI Sorting settings are not properly configured');
        return;
      }

      once('ai-sorting', '.view', context).forEach(function(view) {
        console.log('Found view element:', view);
        
        // Extract view ID from class names
        var viewIdClass = Array.from(view.classList).find(cls => cls.startsWith('view-id-'));
        var viewId = viewIdClass ? viewIdClass.replace('view-id-', '') : 'unknown';
        
        console.log('Processing view:', viewId);

        if (settings.aiSorting.views[viewId]) {
          console.log('AI Sorting settings found for view:', viewId);
          var viewSettings = settings.aiSorting.views[viewId];
          var nids = viewSettings.nids;
          var incrementTrialsUrl = viewSettings.incrementTrialsUrl;
          console.log('NIDs:', nids);
          console.log('Increment Trials URL:', incrementTrialsUrl);

          if (nids && nids.length > 0) {
            console.log('Creating IntersectionObserver for view:', viewId);
            var observer = new IntersectionObserver(function(entries) {
              console.log('IntersectionObserver callback triggered for view:', viewId);
              if (entries[0].isIntersecting) {
                console.log('View is intersecting, sending request to increment trials');
                fetch(incrementTrialsUrl, {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': settings.aiSorting.csrfToken
                  },
                  body: JSON.stringify({nids: nids})
                })
                .then(response => {
                  console.log('Response status:', response.status);
                  return response.json();
                })
                .then(data => {
                  console.log('Increment trials response:', data);
                  if (data.success) {
                    console.log('AI Sorting trials incremented successfully for view:', viewId);
                  } else {
                    console.error('Failed to increment AI Sorting trials:', data.error);
                  }
                })
                .catch(error => {
                  console.error('Error in AI Sorting increment request:', error);
                });
                console.log('Unobserving view after increment request');
                observer.unobserve(view);
              }
            }, {threshold: 0.1});

            console.log('Starting observation of view:', viewId);
            observer.observe(view);
          } else {
            console.warn('No NIDs found for view:', viewId);
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