# AI Sorting

Intelligent ordering for Drupal Views using machine learning. Automatically learns which items engage users most and surfaces the best-performing content, regardless of entity type.

## Features

- **Universal entity support** - Works with nodes, users, taxonomy terms, media, custom entities, and external data
- **Thompson Sampling algorithm** - Advanced machine learning for exploration vs exploitation
- **Cold start handling** - New items get proper exploration scores automatically  
- **Views integration** - Simple sort plugin that works with any Views-compatible data source
- **Real-time learning** - Continuous adaptation based on user interactions
- **Fail-hard debugging** - No silent fallbacks, issues are immediately visible

## Setup

1. Install the module (requires [RL module](https://www.drupal.org/project/rl))
2. Edit any View display (nodes, users, terms, media, custom entities)
3. Add "AI Sorting" as a sort criteria
4. Configure cache refresh rate and time window options
5. Save - items immediately begin learning from user interactions

## How It Works

1. **Track Engagement** - JavaScript monitors when items are viewed and clicked
2. **Learn Patterns** - Thompson Sampling identifies high-performing items  
3. **Optimize Order** - Best items automatically move to prominent positions
4. **Handle New Items** - New content gets exploration scores for fair exposure
5. **Continuous Improvement** - Performance gets better with every visitor

## Use Cases

- **Blog Posts** - Surface most engaging articles first
- **Product Pages** - Highlight items that convert best  
- **News Feeds** - Breaking stories get automatic priority
- **Resource Centers** - Most valuable downloads rise to the top
- **Case Studies** - Showcase most compelling success stories

## Configuration

- **Cache Lifetime** - How often content order refreshes
- **Automatic Cache Setup** - Views cache automatically configured for optimal
  performance

## Dependencies

- [RL (Reinforcement Learning) module](https://www.drupal.org/project/rl)

## Related Modules

- [RL module](https://www.drupal.org/project/rl) - Core Thompson Sampling
  algorithm and API for developers
