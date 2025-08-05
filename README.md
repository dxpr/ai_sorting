# AI Sorting

Machine learning content optimization that automatically learns which content engages your audience and promotes it for maximum impact.

## Features

- **Automatic Optimization**: Content that gets clicks rises to the top
- **Smart Discovery**: New content gets fair exposure while proven winners shine
- **Real-time Learning**: Adapts continuously as user preferences change
- **Zero Maintenance**: Set once and works automatically
- **Thompson Sampling**: Efficient multi-armed bandit algorithm
- **Views Integration**: Add AI Sorting to any content listing

## Requirements

- [RL (Reinforcement Learning)](https://www.drupal.org/project/rl) module
- Views module (core)

## Installation

```bash
composer require drupal/ai_sorting drupal/rl
drush en ai_sorting rl
```

## Configuration

1. Edit any View that displays nodes
2. Add "AI Sorting" to Sort criteria in Views admin
3. Content ordering automatically optimizes based on engagement
4. Experiment UUID generated from `view_id:display_id`

## How It Works

1. **Track Engagement**: Monitors when content is viewed and clicked
2. **Learn Patterns**: Machine learning identifies high-performing content
3. **Optimize Order**: Best content automatically moves to prominent positions
4. **Continuous Improvement**: Performance improves with every visitor

## Use Cases

- **Blog Posts**: Surface most engaging articles first
- **Product Pages**: Highlight items that convert best
- **News Feeds**: Breaking stories get automatic priority
- **Resource Centers**: Most valuable downloads rise to top
- **Case Studies**: Showcase most compelling success stories

## Marketing Benefits

- Higher click-through rates
- Better user experience
- Reduced bounce rates
- Content ROI insights
- Evergreen optimization

## Technical Details

- **Algorithm**: Thompson Sampling for multi-armed bandit experiments
- **Tracking**: JavaScript with IntersectionObserver and click events
- **Transport**: `navigator.sendBeacon()` for non-blocking requests
- **Endpoint**: `/modules/contrib/rl/rl.php`
- **Reports**: Built-in experiment analysis at `/admin/reports/rl`