The AI Sorting module provides an intelligent sorting mechanism for Drupal Views using the Upper Confidence Bound (UCB1) algorithm. This module extends the Statistics module to incorporate AI-based sorting functionality, allowing for more dynamic and user-interaction-driven content presentation.

## How does it work?

### ELI5 (Explain like I'm five)
Imagine you have a big jar of different candies, and you want to find out which candy you like the most. You start by trying each type of candy a little bit to see which one is the yummiest. Over time, you eat more of the candies you like the most but still try new ones occasionally to make sure you haven't missed a better candy. This is what our AI Sorting module does with the content on your website. It tries to show the most interesting content to users based on what they have clicked on before.

### ELI High School Graduate
The AI Sorting module uses a smart algorithm called Upper Confidence Bound (UCB1) to sort content in Drupal Views. It tracks how often users interact with different pieces of content and uses this data to decide which content to show more often. The goal is to balance between showing content that has been popular (exploitation) and trying out new content to see if it might be even better (exploration). This approach is inspired by Bayesian methods and aims to minimize regret, which means it tries to make the best possible decisions based on the information it has.

## Features

- **Integration with Drupal Views**: Seamlessly integrates with Drupal Views to provide AI-based sorting.
- **UCB1 Algorithm**: Utilizes the Upper Confidence Bound (UCB1) algorithm to intelligently sort nodes based on user interactions.
- **Configurable Exploration Parameter**: Allows configuration of the UCB1 algorithm's exploration parameter (`alpha`), controlling the balance between exploring new options and exploiting known successful options.
- **Custom Click Tracking**: Provides an option to use custom click tracking for more granular interaction data.
- **Statistics Module Integration**: Optionally integrates with the built-in Drupal Statistics module for tracking user interactions.
- **Node Visibility and Interaction Tracking**: Tracks node visibility and interaction counts to optimize sorting over time.
- **Increment Trials**: Automatically increments trial counts for nodes as they are displayed to users.
- **Click Tracking**: Tracks user clicks on nodes to gather interaction data and improve sorting accuracy.

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

2. **Custom Click Tracking**:
   - If you select `Custom Click Tracking`, ensure that the necessary JavaScript behaviors are attached to your view.
   - The module will automatically track clicks and send the data to the server for processing.

## Usage

- **Exploration-Exploitation Balance**: Adjust the `alpha` parameter to control how the algorithm balances between exploring new content and exploiting known successful content. Higher values encourage more exploration, while lower values favor content that has performed well in the past.
- **Tracking Method**: Choose between using the built-in Drupal Statistics module or a custom click tracking implementation to gather interaction data.

## Custom Click Tracking vs. Statistics Module Tracking

### Custom Click Tracking

**Custom Click Tracking** monitors clicks **only from the specific Views display** using AI Sort. It captures precise user interactions within that view, enabling real-time content optimization and personalization.

**Advantages:**

- **Granular Data:** Tracks exact user clicks in the view.
- **Real-Time Adaptation:** Adjusts content order based on immediate interactions.
- **Personalization:** Tailors content to individual user preferences.

**Considerations:**

- **Limited Scope:** Doesn't include interactions from other sources.
- **Setup Required:** Needs JavaScript behaviors attached to the view.

**Examples:**

- **Personalized News Feed:** Rearranges articles based on user clicks to show more relevant news.
- **Dynamic Product Recommendations:** Updates product listings in real-time based on user interactions.
- **Customized Learning Paths:** Adjusts course suggestions based on courses users click on.

### Statistics Module Tracking

**Statistics Module Tracking** uses the Drupal Statistics module to count content views from **all sources**—including internal pages, search engines, and social media. It leverages historical popularity data to inform AI Sort.

**Advantages:**

- **Comprehensive Data:** Includes views from all sources.
- **Historical Insights:** Prioritizes consistently popular content.
- **Easy Implementation:** Utilizes existing Statistics module.

**Considerations:**

- **Noisier Data:** May include irrelevant views for the specific context.
- **Less Responsive:** Slower to reflect current user preferences.

**Examples:**

- **Top-Rated Articles:** Highlights articles with the highest overall views.
- **Most Watched Videos:** Features videos popular across all platforms.
- **Best-Selling Products:** Showcases products with the most sales historically.

### Choosing the Right Method

- **Use Custom Click Tracking** for personalization and when immediate, view-specific data is needed.
- **Use Statistics Module Tracking** to feature content with proven overall popularity.

## Performance and Caching

While it may seem impossible to implement machine learning algorithms directly in PHP within Drupal, we accomplished something unique: we engineered the AI sorting algorithm purely in SQL. By leveraging the power of modern relational database platforms, we bypassed the limitations of PHP and unlocked an efficient, scalable solution for creating an AI powered digital experience.

To remove any doubt about the module's scalability, we conducted extensive stress testing, sorting 1,000, 10,000, and even 100,000 nodes:

| Number of Nodes  | AI Sort Time (ms) | Sort by ID Time (ms) |
|------------------|-------------------|----------------------------|
| 1,000            | 56                | 44                         |
| 10,000           | 83                | 48                         |
| 100,000          | 326               | 66                         |

Our SQL-based AI sort handles even large datasets efficiently, though it naturally takes a bit more time compared to the simpler "Sort by ID" method. These results demonstrate that our solution can scale well, even when sorting 100,000 nodes.

That said, we recommend enabling caching in production environments. For views sorting fewer than 10,000 nodes, a 1-minute cache lifetime is optimal. For views sorting more than 10,000 nodes, a 5-minute cache lifetime is recommended. Be aware that a longer cache time may affect the exploration aspect of the algorithm, which benefits from up-to-date data.

## How does it work? (continued)

### ELI Doctorate in Computer Science
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

The module also supports other algorithms such as UCB2, epsilon-greedy, and Thompson sampling, each providing different strategies for balancing exploration and exploitation. UCB2 refines the exploration-exploitation balance by adjusting the confidence bounds, epsilon-greedy selects the best-known option most of the time but explores randomly with probability $\epsilon$, and Thompson sampling uses Bayesian inference to update the probability distribution of rewards and select content based on the posterior distribution.

By leveraging these algorithms, the AI Sorting module aims to optimize content presentation dynamically, ensuring that user interactions are maximized while continually improving the content selection process based on real-time feedback. This regret-minimization strategy is crucial for maintaining high user engagement and satisfaction over time.

#### References

- [On Upper-Confidence Bound Policies for Switching Bandit Problems](https://www.researchgate.net/publication/221393905_On_Upper-Confidence_Bound_Policies_for_Switching_Bandit_Problems)
- [Thompson Sampling for Contextual Bandits with Linear Payoffs](https://ieeexplore.ieee.org/abstract/document/6633613)

### Comparison of Algorithms

| Algorithm          | Description                                                                 | Exploration Strategy                          | Exploitation Strategy                          | Complexity          |
|--------------------|-----------------------------------------------------------------------------|----------------------------------------------|------------------------------------------------|---------------------|
| **Thompson Sampling** | Bayesian inference for exploration/exploitation.                        | Samples from posterior distribution.         | Selects highest sampled reward.                | $O(1)$              |
| **UCB2**           | Improved UCB1 with refined exploration-exploitation balance.                | Adjusts confidence bounds.                   | Similar to UCB1.                               | $O(\log n)$         |
| **UCB1**           | Balances exploration and exploitation using confidence bounds.              | Uses upper confidence bounds.                | Selects higher empirical mean rewards.         | $O(\log n)$         |
| **Epsilon-Greedy** | Chooses best-known option most of the time, explores randomly.            | Random exploration with probability $\epsilon$. | Selects best-known option with high probability. | $O(1)$              |

### Key Terms

- **Bayesian-inspired**: Uses Bayesian methods to update probability estimates of content being the best choice based on user interactions.
- **Bayesian-inspired**: The module uses Bayesian methods to update the probability estimates of content being the best choice based on user interactions.
- **Regret-minimization**: The algorithm aims to minimize regret, which in this context means reducing the chances of not showing the best possible content to users.
- **Reinforcement Learning**: A type of machine learning where an agent learns to make decisions by receiving rewards or penalties for actions taken.