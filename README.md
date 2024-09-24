# AI Sorting Module for Drupal

The AI Sorting module provides an intelligent sorting mechanism for Drupal Views using the Upper Confidence Bound (UCB1) algorithm. This module extends the Statistics module to incorporate AI-based sorting functionality, allowing for more dynamic and user-interaction-driven content presentation.

## Table of contents
1. [Summary](#summary)
2. [How does it work?](#how-does-it-work)
   - [ELI5 (Explain like I'm five)](#eli5-explain-like-im-five)
   - [ELI high school graduate](#eli-high-school-graduate)
3. [Features](#features)
4. [Requirements](#requirements)
5. [Installation](#installation)
6. [Configuration](#configuration)
7. [Usage](#usage)
8. [Custom click tracking vs. statistics module tracking](#custom-click-tracking-vs-statistics-module-tracking)
9. [Performance and caching](#performance-and-caching)
10. [ELI doctorate in computer science](#eli-doctorate-in-computer-science)
11. [References](#references)
12. [Comparison of algorithms](#comparison-of-algorithms)

## Summary

The AI Sorting module leverages the UCB1 algorithm to dynamically sort content in Drupal Views based on user interactions. It aims to balance between showing popular content and exploring new content to optimize user engagement. The module is implemented purely in SQL for efficiency and scalability.

## How does it work?

### ELI5 (Explain like I'm five)
Imagine you have a big jar of different candies, and you want to find out which candy you like the most. You start by trying each type of candy a little bit to see which one is the yummiest. Over time, you eat more of the candies you like the most but still try new ones occasionally to make sure you haven't missed a better candy. This is what our AI Sorting module does with the content on your website. It tries to show the most interesting content to users based on what they have clicked on before.

### ELI high school graduate
The AI Sorting module uses a smart algorithm called Upper Confidence Bound (UCB1) to sort content in Drupal Views. It tracks how often users interact with different pieces of content and uses this data to decide which content to show more often. The goal is to balance between showing content that has been popular (exploitation) and trying out new content to see if it might be even better (exploration). This approach is inspired by Bayesian methods and aims to minimize regret, which means it tries to make the best possible decisions based on the information it has.

## Features

- **AI-Powered Sorting**: Leverages the UCB1 algorithm to sort content dynamically based on user interactions.
- **Views Integration**: Seamlessly integrates with Drupal Views for easy setup and use.
- **Configurable Exploration**: Adjust the `alpha` parameter to fine-tune the balance between exploring new content and exploiting known popular content.
- **Multiple Tracking Methods**: Offers custom click tracking for detailed data or uses the Drupal Statistics module for broader insights.
- **Privacy and Compliance**: All tracking is done anonymously, ensuring user privacy and GDPR compliance.

## Requirements

- Drupal 10
- Views module
- Statistics module (optional, for default tracking method)

## Installation

1. Download and enable the `ai_sorting` module.
2. Ensure that the `views` and `statistics` modules are enabled.
3. Configure the AI Sorting settings in your Drupal Views.

## Configuration

1. **Add AI Sorting to a View**:
   - Go to the Views UI and edit the view where you want to add AI Sorting.
   - Add a new sort criterion and select `AI Sorting`.
   - Configure the `alpha` parameter to control the exploration-exploitation balance.
   - Choose the tracking method (`Statistics Module` or `Custom Click Tracking`).

2. **Custom click tracking**:
   - If you select `Custom Click Tracking`, ensure that the necessary JavaScript behaviors are attached to your view.
   - The module will automatically track clicks and send the data to the server for processing.

## Usage

- **Exploration-exploitation balance**: Adjust the `alpha` parameter to control how the algorithm balances between exploring new content and exploiting known successful content. Higher values encourage more exploration, while lower values favor content that has performed well in the past.
- **Tracking method**: Choose between using the built-in Drupal Statistics module or a custom click tracking implementation to gather interaction data.

## Custom click tracking vs. statistics module tracking

### Views-display-specific click tracking

**Views-Display-Specific Click Tracking** monitors clicks **only from the specific Views display** using AI Sort. It captures precise user interactions within that view, enabling real-time content optimization and personalization.

**Advantages:**

- **Granular data:** Tracks exact user clicks in the view.
- **Real-time adaptation:** Adjusts content order based on immediate interactions.
- **Personalization:** Tailors content to individual user preferences.

**Considerations:**

- **Limited scope:** Doesn't include interactions from other sources.
- **Setup required:** Needs JavaScript behaviors attached to the view.

**Examples:**

- **Personalized news feed:** Rearranges articles based on user clicks to show more relevant news.
- **Dynamic product recommendations:** Updates product listings in real-time based on user interactions.
- **Customized learning paths:** Adjusts course suggestions based on courses users click on.

### Statistics module tracking

**Statistics Module Tracking** uses the Drupal Statistics module to count content views from **all sources**—including internal pages, search engines, and social media. It leverages historical popularity data to inform AI Sort.

**Advantages:**

- **Comprehensive data:** Includes views from all sources.
- **Historical insights:** Prioritizes consistently popular content.
- **Easy implementation:** Utilizes existing Statistics module.

**Considerations:**

- **Noisier data:** May include irrelevant views for the specific context.
- **Less responsive:** Slower to reflect current user preferences.

**Examples:**

- **Top-rated articles:** Highlights articles with the highest overall views.
- **Most watched videos:** Features videos popular across all platforms.
- **Best-selling products:** Showcases products with the most sales historically.

## Performance and caching

While it may seem impossible to implement machine learning algorithms directly in PHP within Drupal, we accomplished something unique: we engineered the AI sorting algorithm purely in SQL. By leveraging the power of modern relational database platforms, we bypassed the limitations of PHP and unlocked an efficient, scalable solution for creating an AI powered digital experience.

To remove any doubt about the module's scalability, we conducted extensive stress testing, sorting 1,000, 10,000, and even 100,000 nodes:

| Number of Nodes  | AI Sort Time (ms) | Sort by ID Time (ms) |
|------------------|-------------------|----------------------------|
| 1,000            | 56                | 44                         |
| 10,000           | 83                | 48                         |
| 100,000          | 326               | 66                         |

Our SQL-based AI sort handles even large datasets efficiently, though it naturally takes a bit more time compared to the simpler "Sort by ID" method. These results demonstrate that our solution can scale well, even when sorting 100,000 nodes.

That said, we recommend enabling caching in production environments. For views sorting fewer than 10,000 nodes, a 1-minute cache lifetime is recommended. For views sorting more than 10,000 nodes, a 5-minute cache lifetime is recommended. Be aware that a longer cache time will downregulate the exploration aspect of the algorithm, which benefits from up-to-date data.

## ELI doctorate in computer science
The AI Sorting module implements a Bayesian-inspired approach to content sorting using the Upper Confidence Bound (UCB1) algorithm, a well-established method in the field of reinforcement learning. The module tracks user interactions to estimate the expected reward $\mu_i$ of displaying each piece of content $i$. The UCB1 algorithm selects the content $i$ that maximizes the upper confidence bound:

$$
i = \arg\max_{i} \left( \hat{\mu}_i + \alpha \sqrt{\frac{2 \ln n}{n_i}} \right)
$$

where:
- $\hat{\mu}_i$ is the empirical mean reward of content $i$,
- $n$ is the total number of trials,
- $n_i$ is the number of times content $i$ has been selected,
- $\alpha$ is the exploration parameter.

This approach balances the trade-off between exploration (selecting less frequently shown content to gather more information) and exploitation (selecting content with higher empirical mean rewards). The exploration parameter $\alpha$ controls this balance, with higher values encouraging exploration and lower values favoring exploitation.

The goal of the UCB1 algorithm is to minimize cumulative regret $R(T)$, defined as the difference between the reward of the optimal strategy and the reward obtained by the algorithm over $T$ trials:

$$
R(T) = T \mu^* - \sum_{t=1}^{T} \mu_{i_t}
$$

where $\mu^*$ is the expected reward of the optimal content, and $\mu_{i_t}$ is the expected reward of the content selected at time $t$.

## References

- [Multi-Armed Bandit Analysis of Upper Confidence Bound Algorithm](https://medium.com/analytics-vidhya/multi-armed-bandit-analysis-of-upper-confidence-bound-algorithm-4b84be516047)
- [On Upper-Confidence Bound Policies for Switching Bandit Problems](https://www.researchgate.net/publication/221393905_On_Upper-Confidence_Bound_Policies_for_Switching_Bandit_Problems)
- [Bandits all the way down: UCB1 as a simulation policy in Monte Carlo Tree Search](https://www.researchgate.net/publication/261452207_Bandits_all_the_way_down_UCB1_as_a_simulation_policy_in_Monte_Carlo_Tree_Search)