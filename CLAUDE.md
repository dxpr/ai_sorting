# Reinforcement Learning Module Split Implementation Plan

## Overview
Split the current AI Sorting module into two separate modules:
1. **Reinforcement Learning (rl)** - Core API module for tracking multi-armed bandit experiments using turns and rewards
2. **AI Sorting (ai_sorting)** - Views integration module that uses the RL API

**Note**: Since the module has never been released, we're removing Statistics module integration entirely for a cleaner architecture.

### RL Module Terminology
- **Turn**: When an arm is trialed (e.g., content displayed, email sent, option presented)
- **Reward**: When an arm succeeds (e.g., click, signup, time on page, scroll depth)
- **Arm**: Any option in the experiment (completely abstract - could be content, layouts, algorithms, etc.)
- **Experiment**: A collection of arms being tested

## Architecture Design

### Core RL Module Structure
```
rl/
├── rl.info.yml
├── rl.module
├── rl.install
├── rl.routing.yml
├── rl.services.yml
├── rl.permissions.yml
├── rl.php                           # Optimized bootstrap endpoint
└── src/
    ├── Storage/
    │   ├── ExperimentDataStorage.php   # Handles turns/rewards storage
    │   └── ExperimentDataStorageInterface.php
    ├── Service/
    │   ├── ExperimentManager.php       # Main API service
    │   └── UCB1Calculator.php          # UCB1 algorithm implementation
    ├── Controller/
    │   └── ExperimentController.php    # REST endpoints for tracking
    └── Exception/
        └── ExperimentNotFoundException.php
```

### Refactored AI Sorting Module Structure
```
ai_sorting/
├── ai_sorting.info.yml              # Add dependency on rl, remove statistics
├── ai_sorting.module                # Remove direct DB operations
├── ai_sorting.install               # Remove table creation, add migration
├── ai_sorting.libraries.yml         # No changes
├── ai_sorting.routing.yml           # Remove tracking routes (moved to RL)
├── ai_sorting.services.yml          # Update service to use RL
├── ai_sorting.permissions.yml       # No changes
├── ai_sorting.schema.yml            # Remove (tables now in RL)
├── js/
│   ├── ai-sorting-rewards.js        # Track rewards (renamed from click-tracking)
│   └── ai-sorting-turns.js          # Track turns (renamed from increment)
└── src/
    ├── Plugin/
    │   └── views/
    │       └── sort/
    │           └── AISorting.php    # Simplified - uses RL for all data
    ├── Service/
    │   ├── TotalTrialsService.php   # Refactor to use RL API
    │   └── ExperimentResolver.php   # NEW: Generate UUID from view/display
    └── Controller/
        └── AISortingController.php  # Remove (functionality moved to RL)
```

### Database Schema for RL Module
```sql
-- rl_experiment_totals table (equivalent to ai_sorting_total_trials)
CREATE TABLE rl_experiment_totals (
  id SERIAL PRIMARY KEY,
  experiment_uuid VARCHAR(128) NOT NULL,
  total_turns INT UNSIGNED DEFAULT 0,
  UNIQUE KEY experiment_unique (experiment_uuid),
  INDEX idx_experiment (experiment_uuid)
);

-- rl_arm_data table (stores all turn and reward data)
CREATE TABLE rl_arm_data (
  id SERIAL PRIMARY KEY,
  experiment_uuid VARCHAR(128) NOT NULL,
  arm_id VARCHAR(255) NOT NULL,
  turns INT UNSIGNED DEFAULT 0,
  rewards INT UNSIGNED DEFAULT 0,
  UNIQUE KEY experiment_arm (experiment_uuid, arm_id),
  INDEX idx_experiment (experiment_uuid)
);
```

## API Design

### ExperimentManager Service API
```php
interface ExperimentManagerInterface {
  // Turn tracking - when arms are trialed
  public function recordTurn($experimentUuid, $armId);
  public function recordTurns($experimentUuid, array $armIds);
  
  // Reward tracking - when arms succeed
  public function recordReward($experimentUuid, $armId);
  
  // Data retrieval
  public function getArmData($experimentUuid, $armId);
  public function getAllArmsData($experimentUuid);
  public function getTotalTurns($experimentUuid);
  
  // UCB1 calculation
  public function getUCB1Scores($experimentUuid, $alpha = 2.0);
}
```

### Dual Access Methods

The RL module provides two ways to access each function:

#### 1. Service API (Full Bootstrap)
For server-side usage within Drupal:
```php
// Example usage in other modules
$rlManager = \Drupal::service('rl.experiment_manager');
$rlManager->recordTurn($uuid, $armId);
$rlManager->recordReward($uuid, $armId);
$scores = $rlManager->getUCB1Scores($uuid, 2.0);
```

#### 2. Optimized Endpoints (Minimal Bootstrap)
For high-performance client-side tracking:

**Optimized Bootstrap Endpoint (rl.php)**
```
POST   /rl/rl.php  (MUST be POST - GET requests are rejected)
  Form data: 
    - action: turn|turns|reward
    - experiment_uuid: {uuid}
    - arm_id: {id} (for single turn/reward)
    - arm_ids: {comma-separated ids} (for multiple turns)
```

**Standard REST Endpoints (Full Bootstrap)**
```
POST   /rl/experiment/{uuid}/turn
  Body: { "arm_id": "123" }

POST   /rl/experiment/{uuid}/turns
  Body: { "arm_ids": ["123", "456"] }

POST   /rl/experiment/{uuid}/reward
  Body: { "arm_id": "123" }

GET    /rl/experiment/{uuid}/scores
  Query: ?alpha=2.0
```

## Implementation Strategy

### Phase 1: Create RL Module
1. Create basic module structure
2. Implement storage layer for experiment data
3. Create ExperimentManager service with dual access (API + endpoints)
4. Implement REST controllers and rl.php
5. Add UCB1Calculator service

### Phase 2: Create Fresh AI Sorting Module
1. Add dependency on RL module
2. Implement Views plugin using RL API calls
3. Create JavaScript that calls RL endpoints
4. Generate UUID from view_id + display_id
5. No migration needed - fresh implementation

## Integration Points

### AI Sorting to RL Module Mapping
- **Experiment UUID**: Generated from view_id + display_id hash
- **Arm ID**: Node ID (nid)
- **Turn**: When nodes are displayed in view
- **Reward**: When a node is clicked

### Modified AI Sorting Flow
1. View loads → AI Sorting generates experiment UUID from view_id:display_id
2. AI Sorting calls RL API to get UCB1 scores
3. Nodes sorted by scores
4. JavaScript records turns → calls RL optimized endpoint (rl.php) when view is displayed
5. JavaScript records rewards → calls RL optimized endpoint (rl.php) when node is clicked
6. RL module is agnostic to what constitutes a turn or reward

### Optimized Bootstrap Implementation

The RL module implements an optimized bootstrap endpoint (`rl.php`) following the Statistics module architecture:

**Critical Design Decision: POST-Only**
Following statistics.php, rl.php ONLY accepts POST requests. This is essential because:
1. **Security**: GET requests can be triggered by image tags, iframes, or prefetching
2. **Caching**: GET requests may be cached by browsers, proxies, or CDNs, causing incorrect data
3. **RESTful**: State-changing operations should never use GET

**Benefits:**
- Minimal Drupal bootstrap (faster response times)
- Direct database writes without full Drupal stack
- Better performance for high-traffic sites
- Non-blocking JavaScript calls using sendBeacon or AJAX POST

**Implementation Details:**
```php
// rl.php structure (following statistics.php architecture)

// CRITICAL: Only accept POST requests for security and caching reasons
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
$experiment_uuid = filter_input(INPUT_POST, 'experiment_uuid', FILTER_SANITIZE_STRING);
$arm_id = filter_input(INPUT_POST, 'arm_id', FILTER_SANITIZE_STRING);

// Early exit if not POST or missing required parameters
if (!$action || !$experiment_uuid) {
  exit();
}

// Minimal bootstrap
try {
  chdir('../../..');  // Navigate to Drupal root
  $autoloader = require_once 'autoload.php';
  $kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
  $kernel->boot();
  $container = $kernel->getContainer();
  
  // Direct database operations
  $storage = $container->get('rl.experiment_data_storage');
  
  switch ($action) {
    case 'turn':
      $storage->recordTurn($experiment_uuid, $arm_id);
      break;
    case 'turns':
      $arm_ids = filter_input(INPUT_POST, 'arm_ids', FILTER_SANITIZE_STRING);
      $storage->recordTurns($experiment_uuid, explode(',', $arm_ids));
      break;
    case 'reward':
      $storage->recordReward($experiment_uuid, $arm_id);
      break;
  }
} catch (\Exception $e) {
  // Silently fail - same as statistics.php
}
```

**Security & Performance Benefits:**
- **POST-only**: Prevents URL-based attacks and ensures requests aren't cached by proxies/CDNs
- **No CSRF needed**: Like statistics.php, recording turns/rewards is low-risk
- **Early validation**: Exits immediately if request is invalid
- **Silent failure**: Doesn't leak information about system internals

**Usage Decision Matrix:**
| Use Case | Recommended Method |
|----------|-------------------|
| JavaScript tracking | rl.php (optimized) |
| High-volume tracking | rl.php (optimized) |
| Server-side module integration | Service API |
| Admin UI operations | REST endpoints |
| External API clients | REST endpoints |

## Benefits of This Architecture

1. **Reusability**: RL module can be used for other recommendation systems
2. **Clean Separation**: Business logic separated from Drupal-specific integrations
3. **Testability**: Core RL logic can be unit tested independently
4. **Performance**: Centralized optimization in RL module
5. **API-First**: Other modules can integrate via REST API or services

## Implementation Order

1. Create RL module structure
2. Implement storage layer matching current functionality
3. Add turn/reward tracking (matching incrementTrials/trackClick)
4. Implement UCB1 calculator (exact same algorithm)
5. Create REST endpoints
6. Update AI Sorting to use RL services
7. Update JavaScript to use new endpoints
8. Create data migration
9. Update documentation

## Key Refactoring Principles

1. **No Feature Additions**: Maintain exact same functionality (minus Statistics integration)
2. **Data Structure Mapping**: 
   - `ai_sorting_total_trials` → `rl_experiment_totals` (total turns)
   - `node_counter.ai_sorting_trials` → `rl_arm_data.turns`
   - `ai_sorting_clicks` → `rl_arm_data.rewards`
   - Statistics module integration → Removed entirely
3. **API Equivalence**: Each RL method maps to existing functionality
4. **Deterministic UUIDs**: Generate from view_id:display_id for consistency
5. **Abstract Tracking**: RL doesn't know what turns/rewards represent

## Key Changes in AI Sorting Module

### Files to Remove/Move
- `AISortingController.php` - Functionality moves to RL module's `ExperimentController.php`
- `ai_sorting.schema.yml` - Tables now defined in RL module
- Database table creation in `ai_sorting.install` - Replaced with migration code

### Implementation Notes
Since this is a fresh implementation (not a refactor of existing code):
1. **ai_sorting.info.yml** - Create with only RL and Views dependencies
2. **ai_sorting.module** - Implement fresh, passing RL endpoint URLs to JavaScript
3. **ai_sorting.routing.yml** - No tracking routes needed
4. **AISorting.php** - Use RL service API for scoring
5. **TotalTrialsService.php** - Simple wrapper around RL ExperimentManager
6. **JavaScript files** - Implement to use rl.php endpoint with FormData
7. **AISorting.php buildOptionsForm()** - Only alpha parameter needed

### New Files
1. **ExperimentResolver.php** - Service to generate/retrieve experiment UUID from view_id and display_id