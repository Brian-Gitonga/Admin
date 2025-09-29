<?php
// Include session check
require_once 'session_check.php';

// Sample survey data - replace with actual database queries
$surveyStats = [
    'total_responses' => 156,
    'average_rating' => 4.2,
    'satisfaction_rate' => 84,
    'response_rate' => 67
];

// Sample recent feedback data - replace with actua database queries
$recentFeedback = [
    [
        'id' => 1,
        'user_phone' => '0712345678',
        'hotspot_name' => 'Main Street Cafe',
        'rating' => 5,
        'feedback' => 'Excellent service! Fast internet and great customer support.',
        'date' => '2024-01-15 14:30:00',
        'category' => 'Service Quality'
    ],
    [
        'id' => 2,
        'user_phone' => '0723456789',
        'hotspot_name' => 'Downtown Office',
        'rating' => 4,
        'feedback' => 'Good connection speed, but sometimes disconnects during peak hours.',
        'date' => '2024-01-14 09:15:00',
        'category' => 'Connection Stability'
    ],
    [
        'id' => 3,
        'user_phone' => '0734567890',
        'hotspot_name' => 'University Campus',
        'rating' => 3,
        'feedback' => 'Average service. Could improve the login process.',
        'date' => '2024-01-13 16:45:00',
        'category' => 'User Experience'
    ],
    [
        'id' => 4,
        'user_phone' => '0745678901',
        'hotspot_name' => 'Shopping Mall',
        'rating' => 5,
        'feedback' => 'Perfect! Always reliable and fast speeds.',
        'date' => '2024-01-12 11:20:00',
        'category' => 'Service Quality'
    ],
    [
        'id' => 5,
        'user_phone' => '0756789012',
        'hotspot_name' => 'Airport Terminal',
        'rating' => 2,
        'feedback' => 'Slow speeds and frequent disconnections. Needs improvement.',
        'date' => '2024-01-11 08:30:00',
        'category' => 'Connection Speed'
    ]
];

// Sample rating distribution data
$ratingDistribution = [
    5 => 45,
    4 => 38,
    3 => 25,
    2 => 12,
    1 => 8
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Survey & Feedback</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Survey-specific styles */
        .survey-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .survey-stat-card {
            background-color: var(--bg-secondary);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s;
            position: relative;
            overflow: hidden;
        }

        .survey-stat-card:hover {
            transform: translateY(-5px);
        }

        .survey-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }

        .survey-stat-card.blue::before { background-color: var(--accent-blue); }
        .survey-stat-card.green::before { background-color: var(--accent-green); }
        .survey-stat-card.orange::before { background-color: var(--accent-orange); }
        .survey-stat-card.purple::before { background-color: var(--accent-purple); }

        .survey-stat-title {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .survey-stat-value {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .survey-stat-subtitle {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .rating-stars {
            display: flex;
            gap: 0.2rem;
            margin-top: 0.5rem;
        }

        .rating-stars .star {
            color: #fbbf24;
            font-size: 1.2rem;
        }

        .rating-stars .star.empty {
            color: var(--bg-accent);
        }

        .feedback-section {
            background-color: var(--bg-secondary);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select {
            background-color: var(--bg-accent);
            border: none;
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .search-box {
            background-color: var(--bg-accent);
            border: none;
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            width: 200px;
            font-size: 0.9rem;
        }

        .search-box::placeholder {
            color: var(--text-secondary);
        }

        .feedback-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .feedback-table th {
            text-align: left;
            padding: 1rem;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 1px solid var(--bg-accent);
            white-space: nowrap;
        }

        .feedback-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--bg-accent);
            vertical-align: top;
        }

        .feedback-table tr:last-child td {
            border-bottom: none;
        }

        .feedback-table tr:hover {
            background-color: rgba(51, 65, 85, 0.3);
        }

        .rating-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .rating-number {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .rating-stars-small {
            display: flex;
            gap: 0.1rem;
        }

        .rating-stars-small .star {
            color: #fbbf24;
            font-size: 0.9rem;
        }

        .rating-stars-small .star.empty {
            color: var(--bg-accent);
        }

        .feedback-text {
            max-width: 300px;
            line-height: 1.4;
            color: var(--text-primary);
        }

        .feedback-meta {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .user-phone {
            font-weight: 500;
            color: var(--text-primary);
        }

        .hotspot-name {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .feedback-date {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .category-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 1rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
        }

        .category-service { background-color: rgba(74, 222, 128, 0.2); color: var(--accent-blue); }
        .category-connection { background-color: rgba(59, 130, 246, 0.2); color: var(--accent-green); }
        .category-experience { background-color: rgba(245, 158, 11, 0.2); color: var(--accent-orange); }
        .category-speed { background-color: rgba(236, 72, 153, 0.2); color: var(--accent-red); }

        .rating-distribution {
            background-color: var(--bg-secondary);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .rating-bar-container {
            margin-top: 1rem;
        }

        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.8rem;
        }

        .rating-label {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 80px;
            font-size: 0.9rem;
        }

        .rating-bar {
            flex: 1;
            height: 8px;
            background-color: var(--bg-accent);
            border-radius: 4px;
            overflow: hidden;
        }

        .rating-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .rating-bar-fill.star-5 { background-color: var(--accent-blue); }
        .rating-bar-fill.star-4 { background-color: var(--accent-green); }
        .rating-bar-fill.star-3 { background-color: var(--accent-orange); }
        .rating-bar-fill.star-2 { background-color: var(--accent-red); }
        .rating-bar-fill.star-1 { background-color: var(--accent-purple); }

        .rating-count {
            min-width: 40px;
            text-align: right;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .survey-stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                width: 100%;
            }

            .feedback-table {
                font-size: 0.8rem;
            }

            .feedback-table th,
            .feedback-table td {
                padding: 0.5rem;
            }

            .feedback-text {
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Development Notice -->
        <div style="background-color: #fef3c7; border: 2px solid #f59e0b; border-radius: 0.5rem; padding: 1rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
            <div style="flex-shrink: 0;">
                <i class="fas fa-exclamation-triangle" style="color: #f59e0b; font-size: 1.5rem;"></i>
            </div>
            <div>
                <h3 style="margin: 0 0 0.25rem 0; color: #92400e; font-size: 1.1rem; font-weight: 600;">
                    Page Under Development
                </h3>
                <p style="margin: 0; color: #92400e; font-size: 0.9rem;">
                    This survey and feedback page is currently under development. Some features may not be fully functional yet. We're working hard to bring you comprehensive customer feedback analytics soon!
                </p>
            </div>
        </div>

        <div class="page-header">
            <h1 class="page-title">Customer Survey & Feedback</h1>
            <p class="page-subtitle">Monitor customer satisfaction and feedback from your hotspot services</p>
        </div>

        <!-- Survey Statistics -->
        <div class="survey-stats-grid">
            <div class="survey-stat-card blue">
                <div class="survey-stat-title">
                    <i class="fas fa-chart-line"></i>
                    Total Responses
                </div>
                <div class="survey-stat-value"><?php echo $surveyStats['total_responses']; ?></div>
                <div class="survey-stat-subtitle">This month</div>
            </div>

            <div class="survey-stat-card green">
                <div class="survey-stat-title">
                    <i class="fas fa-star"></i>
                    Average Rating
                </div>
                <div class="survey-stat-value"><?php echo $surveyStats['average_rating']; ?></div>
                <div class="rating-stars">
                    <?php
                    $rating = $surveyStats['average_rating'];
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= floor($rating)) {
                            echo '<i class="fas fa-star star"></i>';
                        } elseif ($i <= ceil($rating)) {
                            echo '<i class="fas fa-star-half-alt star"></i>';
                        } else {
                            echo '<i class="far fa-star star empty"></i>';
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="survey-stat-card orange">
                <div class="survey-stat-title">
                    <i class="fas fa-thumbs-up"></i>
                    Satisfaction Rate
                </div>
                <div class="survey-stat-value"><?php echo $surveyStats['satisfaction_rate']; ?>%</div>
                <div class="survey-stat-subtitle">4+ star ratings</div>
            </div>

            <div class="survey-stat-card purple">
                <div class="survey-stat-title">
                    <i class="fas fa-users"></i>
                    Response Rate
                </div>
                <div class="survey-stat-value"><?php echo $surveyStats['response_rate']; ?>%</div>
                <div class="survey-stat-subtitle">Of total users</div>
            </div>
        </div>

        <!-- Rating Distribution -->
        <div class="rating-distribution">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Rating Distribution
                </h2>
            </div>

            <div class="rating-bar-container">
                <?php
                $totalRatings = array_sum($ratingDistribution);
                for ($star = 5; $star >= 1; $star--):
                    $count = $ratingDistribution[$star] ?? 0;
                    $percentage = $totalRatings > 0 ? ($count / $totalRatings) * 100 : 0;
                ?>
                <div class="rating-bar-item">
                    <div class="rating-label">
                        <span><?php echo $star; ?></span>
                        <i class="fas fa-star" style="color: #fbbf24;"></i>
                    </div>
                    <div class="rating-bar">
                        <div class="rating-bar-fill star-<?php echo $star; ?>" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <div class="rating-count"><?php echo $count; ?></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Recent Feedback -->
        <div class="feedback-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-comments"></i>
                    Recent Customer Feedback
                </h2>
                <div class="filter-controls">
                    <select class="filter-select" id="ratingFilter">
                        <option value="">All Ratings</option>
                        <option value="5">5 Stars</option>
                        <option value="4">4 Stars</option>
                        <option value="3">3 Stars</option>
                        <option value="2">2 Stars</option>
                        <option value="1">1 Star</option>
                    </select>
                    <select class="filter-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="Service Quality">Service Quality</option>
                        <option value="Connection Stability">Connection Stability</option>
                        <option value="User Experience">User Experience</option>
                        <option value="Connection Speed">Connection Speed</option>
                    </select>
                    <input type="text" class="search-box" placeholder="Search feedback..." id="feedbackSearch">
                </div>
            </div>

            <div class="table-container">
                <?php if (!empty($recentFeedback)): ?>
                <table class="feedback-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Rating</th>
                            <th>Feedback</th>
                            <th>Category</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="feedbackTableBody">
                        <?php foreach ($recentFeedback as $feedback): ?>
                        <tr data-rating="<?php echo $feedback['rating']; ?>" data-category="<?php echo $feedback['category']; ?>">
                            <td>
                                <div class="feedback-meta">
                                    <div class="user-phone"><?php echo htmlspecialchars($feedback['user_phone']); ?></div>
                                    <div class="hotspot-name"><?php echo htmlspecialchars($feedback['hotspot_name']); ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="rating-display">
                                    <span class="rating-number"><?php echo $feedback['rating']; ?></span>
                                    <div class="rating-stars-small">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star star <?php echo $i > $feedback['rating'] ? 'empty' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="feedback-text"><?php echo htmlspecialchars($feedback['feedback']); ?></div>
                            </td>
                            <td>
                                <span class="category-badge category-<?php echo strtolower(str_replace(' ', '', $feedback['category'])); ?>">
                                    <?php echo htmlspecialchars($feedback['category']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="feedback-date"><?php echo date('M d, Y H:i', strtotime($feedback['date'])); ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-comment-slash"></i>
                    <h3>No feedback available</h3>
                    <p>Customer feedback will appear here once users start rating your services.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const ratingFilter = document.getElementById('ratingFilter');
            const categoryFilter = document.getElementById('categoryFilter');
            const searchBox = document.getElementById('feedbackSearch');
            const tableBody = document.getElementById('feedbackTableBody');
            const rows = tableBody.querySelectorAll('tr');

            function filterTable() {
                const ratingValue = ratingFilter.value;
                const categoryValue = categoryFilter.value;
                const searchValue = searchBox.value.toLowerCase();

                rows.forEach(row => {
                    const rating = row.getAttribute('data-rating');
                    const category = row.getAttribute('data-category');
                    const text = row.textContent.toLowerCase();

                    const ratingMatch = !ratingValue || rating === ratingValue;
                    const categoryMatch = !categoryValue || category === categoryValue;
                    const searchMatch = !searchValue || text.includes(searchValue);

                    if (ratingMatch && categoryMatch && searchMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            ratingFilter.addEventListener('change', filterTable);
            categoryFilter.addEventListener('change', filterTable);
            searchBox.addEventListener('input', filterTable);
        });
    </script>
</body>
</html>