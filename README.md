# AI Sorting

Intelligent content ordering for Drupal Views using machine learning. Content automatically learns which items engage users most and surfaces the best-performing content.

## Features

- Automatic content optimization based on user engagement
- Thompson Sampling machine learning algorithm
- Views integration as sort plugin
- Automatic cache management
- Real-time learning and adaptation

## Setup

1. Install the module (requires [RL module](https://www.drupal.org/project/rl))
2. Edit any View display
3. Add "AI Sorting" as a sort criteria
4. Configure cache refresh rate (30 seconds to 10 minutes)
5. Save - content immediately begins learning from user interactions

## How It Works

1. **Track Engagement** - JavaScript monitors when content is viewed and clicked
2. **Learn Patterns** - Machine learning identifies high-performing content
3. **Optimize Order** - Best content automatically moves to prominent positions
4. **Continuous Improvement** - Performance gets better with every visitor

## Use Cases

- **Blog Posts** - Surface most engaging articles first
- **Product Pages** - Highlight items that convert best  
- **News Feeds** - Breaking stories get automatic priority
- **Resource Centers** - Most valuable downloads rise to the top
- **Case Studies** - Showcase most compelling success stories

## Configuration

- **Cache Lifetime** - How often content order refreshes
- **Automatic Cache Setup** - Views cache automatically configured for optimal performance

## Dependencies

- [RL (Reinforcement Learning) module](https://www.drupal.org/project/rl)

## Related Modules

- [RL module](https://www.drupal.org/project/rl) - Core Thompson Sampling algorithm and API for developers