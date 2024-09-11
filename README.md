# ai_sorting

The AI Sorting module provides an intelligent sorting mechanism for Drupal Views using the Upper Confidence Bound (UCB2) algorithm. This module extends the Statistics module to incorporate AI-based sorting functionality.

## Features

- Integrates with Drupal Views to provide AI-based sorting.
- Utilizes the UCB2 algorithm to intelligently sort nodes based on user interactions.
- Allows configuration of the UCB2 algorithm's exploration parameter (`alpha`).
- Tracks node visibility and interaction counts to optimize sorting over time.

## Requirements

- Drupal 10
- Views module
- Statistics module