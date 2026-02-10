<?php
// Start session at the very beginning, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment & Services - FES</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f5f5;
            color: #424242;
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
        }

        /* Page Header */
        #equipment-header {
            background-color: #424242;
            color: #ffffff;
            padding: 60px 50px;
            text-align: center;
        }

        #equipment-header h1 {
            font-size: 48px;
            margin-bottom: 15px;
        }

        #equipment-header p {
            font-size: 18px;
            color: #cccccc;
        }

        /* Main Content */
        .equipment-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 50px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 40px;
        }

        /* Sidebar Filters */
        .filters-sidebar {
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .filter-section {
            margin-bottom: 30px;
        }

        .filter-section:last-child {
            margin-bottom: 0;
        }

        .filter-title {
            font-size: 16px;
            font-weight: 700;
            color: #212121;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-title i {
            color: #D32F2F;
            font-size: 14px;
        }

        .filter-divider {
            height: 2px;
            background: linear-gradient(90deg, #D32F2F, transparent);
            margin-bottom: 15px;
        }

        .filter-option {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            cursor: pointer;
        }

        .filter-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            cursor: pointer;
            accent-color: #D32F2F;
        }

        .filter-option label {
            cursor: pointer;
            font-size: 14px;
            color: #424242;
            flex: 1;
        }

        .filter-option .count {
            font-size: 12px;
            color: #757575;
        }

        .price-range {
            margin-top: 15px;
        }

        .price-range input[type="range"] {
            width: 100%;
            margin-bottom: 10px;
            accent-color: #D32F2F;
        }

        .price-display {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #757575;
        }

        .clear-filters {
            width: 100%;
            padding: 12px;
            background-color: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            color: #424242;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .clear-filters:hover {
            background-color: #eeeeee;
            border-color: #D32F2F;
        }

        /* Main Content Area */
        .equipment-main {
            display: flex;
            flex-direction: column;
        }

        /* Search and Sort Bar */
        .equipment-toolbar {
            background: #ffffff;
            padding: 20px 30px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            gap: 20px;
        }

        .search-box {
            flex: 1;
            position: relative;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #D32F2F;
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #757575;
        }

        .sort-select {
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background-color: #ffffff;
            color: #424242;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .sort-select:focus {
            outline: none;
            border-color: #D32F2F;
        }

        .results-count {
            font-size: 14px;
            color: #757575;
            white-space: nowrap;
        }

        /* Equipment Grid */
        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        /* Equipment Card */
        .equipment-card {
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .equipment-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .equipment-image {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, #424242 0%, #D32F2F 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            color: rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .equipment-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: #D32F2F;
            color: #ffffff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .equipment-badge.available {
            background: #4CAF50;
        }

        .equipment-badge.limited {
            background: #FF9800;
        }

        .equipment-content {
            padding: 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .equipment-category {
            font-size: 12px;
            color: #D32F2F;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .equipment-name {
            font-size: 18px;
            font-weight: 700;
            color: #212121;
            margin-bottom: 8px;
        }

        .equipment-description {
            font-size: 13px;
            color: #757575;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .equipment-specs {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .spec-tag {
            background: #f5f5f5;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            color: #424242;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .spec-tag i {
            color: #D32F2F;
            font-size: 11px;
        }

        .equipment-pricing {
            border-top: 1px solid #f0f0f0;
            padding-top: 16px;
            margin-top: 16px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .price-row label {
            color: #757575;
        }

        .price-row .price {
            font-weight: 600;
            color: #212121;
        }

        .equipment-footer {
            display: flex;
            gap: 12px;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
        }

        .btn-view {
            flex: 1;
            padding: 12px;
            background-color: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            color: #424242;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            font-size: 13px;
        }

        .btn-view:hover {
            background-color: #eeeeee;
            border-color: #D32F2F;
            color: #D32F2F;
        }

        .btn-book {
            flex: 1;
            padding: 12px;
            background-color: #D32F2F;
            border: none;
            border-radius: 6px;
            color: #ffffff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            font-size: 13px;
        }

        .btn-book:hover {
            background-color: #B71C1C;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: #ffffff;
            border-radius: 8px;
            color: #757575;
        }

        .empty-state i {
            font-size: 64px;
            color: #D32F2F;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            color: #212121;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .equipment-container {
                grid-template-columns: 1fr;
                padding: 30px 20px;
            }

            .filters-sidebar {
                position: static;
            }

            #equipment-header {
                padding: 40px 20px;
            }

            #equipment-header h1 {
                font-size: 36px;
            }

            .equipment-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: 100%;
            }

            .equipment-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .equipment-toolbar {
                padding: 15px;
            }

            .equipment-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .filters-sidebar {
                display: none;
            }

            .equipment-container {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    
    <?php include '../includes/header.php'; ?>
    
    <!-- Page Header -->
    <section id="equipment-header">
        <h1>Our Equipment & Services</h1>
        <p>Browse our comprehensive catalogue of agricultural and engineering equipment available for booking</p>
    </section>

    <!-- Main Content -->
    <div class="equipment-container">
        
        <!-- Sidebar Filters -->
        <aside class="filters-sidebar">
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-filter"></i> Filters
                </div>
                <div class="filter-divider"></div>
            </div>

            <!-- Category Filter -->
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-list"></i> Category
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="cat-tractors" checked>
                    <label for="cat-tractors">Tractors</label>
                    <span class="count">(8)</span>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="cat-harvesters">
                    <label for="cat-harvesters">Harvesters</label>
                    <span class="count">(5)</span>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="cat-planters">
                    <label for="cat-planters">Planters</label>
                    <span class="count">(4)</span>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="cat-irrigation">
                    <label for="cat-irrigation">Irrigation Systems</label>
                    <span class="count">(6)</span>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="cat-sprayers">
                    <label for="cat-sprayers">Sprayers</label>
                    <span class="count">(3)</span>
                </div>
            </div>

            <!-- Availability Filter -->
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-check-circle"></i> Availability
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="avail-available" checked>
                    <label for="avail-available">Available</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="avail-limited">
                    <label for="avail-limited">Limited Stock</label>
                </div>
            </div>

            <!-- Price Range Filter -->
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-money-bill"></i> Price Range
                </div>
                <div class="price-range">
                    <input type="range" min="0" max="50000" value="50000" id="priceRange">
                    <div class="price-display">
                        <span>MWK 0</span>
                        <span id="priceValue">MWK 50,000</span>
                    </div>
                </div>
            </div>

            <!-- Size/Capacity Filter -->
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-expand"></i> Size/Capacity
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="size-small">
                    <label for="size-small">Small (0-50 HP)</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="size-medium" checked>
                    <label for="size-medium">Medium (50-100 HP)</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="size-large">
                    <label for="size-large">Large (100+ HP)</label>
                </div>
            </div>

            <button class="clear-filters">Clear All Filters</button>
        </aside>

        <!-- Main Equipment Area -->
        <div class="equipment-main">
            
            <!-- Toolbar -->
            <div class="equipment-toolbar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search equipment...">
                </div>
                <select class="sort-select">
                    <option value="popular">Most Popular</option>
                    <option value="newest">Newest</option>
                    <option value="price-low">Price: Low to High</option>
                    <option value="price-high">Price: High to Low</option>
                    <option value="rating">Highest Rated</option>
                </select>
                <div class="results-count">Showing <strong>26</strong> equipment</div>
            </div>

            <!-- Equipment Grid -->
            <div class="equipment-grid">
                
                <!-- Equipment Card 1 -->
                <div class="equipment-card">
                    <div class="equipment-image">
                        <i class="fas fa-tractor"></i>
                        <span class="equipment-badge available">Available</span>
                    </div>
                    <div class="equipment-content">
                        <div class="equipment-category">Tractors</div>
                        <h3 class="equipment-name">John Deere 5090R</h3>
                        <p class="equipment-description">90 HP tractor with advanced hydraulics, ideal for medium to large-scale farming operations.</p>
                        <div class="equipment-specs">
                            <span class="spec-tag"><i class="fas fa-bolt"></i> 90 HP</span>
                            <span class="spec-tag"><i class="fas fa-cog"></i> 4WD</span>
                            <span class="spec-tag"><i class="fas fa-star"></i> 4.8/5</span>
                        </div>
                        <div class="equipment-pricing">
                            <div class="price-row">
                                <label>Hourly Rate:</label>
                                <span class="price">MWK 15,000</span>
                            </div>
                            <div class="price-row">
                                <label>Daily Rate:</label>
                                <span class="price">MWK 120,000</span>
                            </div>
                        </div>
                    </div>
                    <div class="equipment-footer">
                        <a href="#" class="btn-view">View Details</a>
                        <a href="auth/signin.php" class="btn-book">Book Now</a>
                    </div>
                </div>

                <!-- Equipment Card 2 -->
                <div class="equipment-card">
                    <div class="equipment-image">
                        <i class="fas fa-combine"></i>
                        <span class="equipment-badge available">Available</span>
                    </div>
                    <div class="equipment-content">
                        <div class="equipment-category">Harvesters</div>
                        <h3 class="equipment-name">CLAAS Lexion 780</h3>
                        <p class="equipment-description">High-capacity combine harvester for efficient grain harvesting with modern technology.</p>
                        <div class="equipment-specs">
                            <span class="spec-tag"><i class="fas fa-leaf"></i> Grain</span>
                            <span class="spec-tag"><i class="fas fa-tachometer-alt"></i> 600 HP</span>
                            <span class="spec-tag"><i class="fas fa-star"></i> 4.9/5</span>
                        </div>
                        <div class="equipment-pricing">
                            <div class="price-row">
                                <label>Hourly Rate:</label>
                                <span class="price">MWK 45,000</span>
                            </div>
                            <div class="price-row">
                                <label>Daily Rate:</label>
                                <span class="price">MWK 350,000</span>
                            </div>
                        </div>
                    </div>
                    <div class="equipment-footer">
                        <a href="#" class="btn-view">View Details</a>
                        <a href="auth/signin.php" class="btn-book">Book Now</a>
                    </div>
                </div>

                <!-- Equipment Card 3 -->
                <div class="equipment-card">
                    <div class="equipment-image">
                        <i class="fas fa-water"></i>
                        <span class="equipment-badge limited">Limited</span>
                    </div>
                    <div class="equipment-content">
                        <div class="equipment-category">Irrigation</div>
                        <h3 class="equipment-name">Pivot Irrigation System</h3>
                        <p class="equipment-description">Center pivot system covering up to 40 hectares with automated controls and water efficiency.</p>
                        <div class="equipment-specs">
                            <span class="spec-tag"><i class="fas fa-expand"></i> 40 Ha</span>
                            <span class="spec-tag"><i class="fas fa-droplet"></i> 500 m³/hr</span>
                            <span class="spec-tag"><i class="fas fa-star"></i> 4.7/5</span>
                        </div>
                        <div class="equipment-pricing">
                            <div class="price-row">
                                <label>Daily Rate:</label>
                                <span class="price">MWK 80,000</span>
                            </div>
                            <div class="price-row">
                                <label>Monthly Rate:</label>
                                <span class="price">MWK 1,800,000</span>
                            </div>
                        </div>
                    </div>
                    <div class="equipment-footer">
                        <a href="#" class="btn-view">View Details</a>
                        <a href="auth/signin.php" class="btn-book">Book Now</a>
                    </div>
                </div>

                <!-- Equipment Card 4 -->
                <div class="equipment-card">
                    <div class="equipment-image">
                        <i class="fas fa-spray-can"></i>
                        <span class="equipment-badge available">Available</span>
                    </div>
                    <div class="equipment-content">
                        <div class="equipment-category">Sprayers</div>
                        <h3 class="equipment-name">Boom Sprayer 4000L</h3>
                        <p class="equipment-description">Mounted boom sprayer with 4000L tank capacity, ideal for pesticide and fertilizer application.</p>
                        <div class="equipment-specs">
                            <span class="spec-tag"><i class="fas fa-cube"></i> 4000L</span>
                            <span class="spec-tag"><i class="fas fa-arrows-alt"></i> 24m Boom</span>
                            <span class="spec-tag"><i class="fas fa-star"></i> 4.6/5</span>
                        </div>
                        <div class="equipment-pricing">
                            <div class="price-row">
                                <label>Hourly Rate:</label>
                                <span class="price">MWK 8,000</span>
                            </div>
                            <div class="price-row">
                                <label>Daily Rate:</label>
                                <span class="price">MWK 60,000</span>
                            </div>
                        </div>
                    </div>
                    <div class="equipment-footer">
                        <a href="#" class="btn-view">View Details</a>
                        <a href="auth/signin.php" class="btn-book">Book Now</a>
                    </div>
                </div>

                <!-- Equipment Card 5 -->
                <div class="equipment-card">
                    <div class="equipment-image">
                        <i class="fas fa-seedling"></i>
                        <span class="equipment-badge available">Available</span>
                    </div>
                    <div class="equipment-content">
                        <div class="equipment-category">Planters</div>
                        <h3 class="equipment-name">Precision Planter 12-Row</h3>
                        <p class="equipment-description">Precision planting system with GPS guidance for accurate seed placement and spacing.</p>
                        <div class="equipment-specs">
                            <span class="spec-tag"><i class="fas fa-align-justify"></i> 12 Rows</span>
                            <span class="spec-tag"><i class="fas fa-satellite"></i> GPS</span>
                            <span class="spec-tag"><i class="fas fa-star"></i> 4.8/5</span>
                        </div>
                        <div class="equipment-pricing">
                            <div class="price-row">
                                <label>Hourly Rate:</label>
                                <span class="price">MWK 12,000</span>
                            </div>
                            <div class="price-row">
                                <label>Daily Rate:</label>
                                <span class="price">MWK 90,000</span>
                            </div>
                        </div>
                    </div>
                    <div class="equipment-footer">
                        <a href="#" class="btn-view">View Details</a>
                        <a href="auth/signin.php" class="btn-book">Book Now</a>
                    </div>
                </div>

                <!-- Equipment Card 6 -->
                <div class="equipment-card">
                    <div class="equipment-image">
                        <i class="fas fa-tractor"></i>
                        <span class="equipment-badge available">Available</span>
                    </div>
                    <div class="equipment-content">
                        <div class="equipment-category">Tractors</div>
                        <h3 class="equipment-name">Massey Ferguson 4710</h3>
                        <p class="equipment-description">Reliable 70 HP tractor perfect for small to medium farms with excellent fuel efficiency.</p>
                        <div class="equipment-specs">
                            <span class="spec-tag"><i class="fas fa-bolt"></i> 70 HP</span>
                            <span class="spec-tag"><i class="fas fa-cog"></i> 2WD</span>
                            <span class="spec-tag"><i class="fas fa-star"></i> 4.7/5</span>
                        </div>
                        <div class="equipment-pricing">
                            <div class="price-row">
                                <label>Hourly Rate:</label>
                                <span class="price">MWK 12,000</span>
                            </div>
                            <div class="price-row">
                                <label>Daily Rate:</label>
                                <span class="price">MWK 90,000</span>
                            </div>
                        </div>
                    </div>
                    <div class="equipment-footer">
                        <a href="#" class="btn-view">View Details</a>
                        <a href="auth/signin.php" class="btn-book">Book Now</a>
                    </div>
                </div>

            </div>

            <!-- Pagination or Load More -->
            <div style="text-align: center; margin-top: 40px;">
                <button style="padding: 14px 40px; background-color: #D32F2F; color: #ffffff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 15px; transition: all 0.3s;" onmouseover="this.style.backgroundColor='#B71C1C'" onmouseout="this.style.backgroundColor='#D32F2F'">
                    Load More Equipment
                </button>
            </div>

        </div>

    </div>

    <!-- Info Section -->
    <section style="background-color: #ffffff; padding: 60px 50px; margin-top: 40px;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <h2 style="text-align: center; font-size: 32px; margin-bottom: 40px; color: #212121;">Why Choose FES Equipment?</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
                <div style="text-align: center;">
                    <div style="width: 60px; height: 60px; background-color: #ffebee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 28px; color: #D32F2F;">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 style="color: #212121; margin-bottom: 10px;">Well-Maintained</h3>
                    <p style="color: #757575; font-size: 14px;">All equipment is regularly serviced and maintained to the highest standards.</p>
                </div>
                <div style="text-align: center;">
                    <div style="width: 60px; height: 60px; background-color: #ffebee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 28px; color: #D32F2F;">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 style="color: #212121; margin-bottom: 10px;">Expert Support</h3>
                    <p style="color: #757575; font-size: 14px;">Our trained operators provide professional support throughout your service.</p>
                </div>
                <div style="text-align: center;">
                    <div style="width: 60px; height: 60px; background-color: #ffebee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 28px; color: #D32F2F;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 style="color: #212121; margin-bottom: 10px;">Quick Booking</h3>
                    <p style="color: #757575; font-size: 14px;">Book equipment online in minutes and get instant confirmation.</p>
                </div>
                <div style="text-align: center;">
                    <div style="width: 60px; height: 60px; background-color: #ffebee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 28px; color: #D32F2F;">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3 style="color: #212121; margin-bottom: 10px;">Multiple Locations</h3>
                    <p style="color: #757575; font-size: 14px;">Access equipment from our depots across the country.</p>
                </div>
            </div>
        </div>
    </section>
    
    <?php include '../includes/footer.php'; ?>

    <script>
        // Price range slider
        const priceRange = document.getElementById('priceRange');
        const priceValue = document.getElementById('priceValue');
        
        priceRange.addEventListener('input', function() {
            const value = parseInt(this.value).toLocaleString();
            priceValue.textContent = 'MWK ' + value;
        });

        // Clear filters
        document.querySelector('.clear-filters').addEventListener('click', function() {
            document.querySelectorAll('.filter-option input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            document.getElementById('cat-tractors').checked = true;
            document.getElementById('avail-available').checked = true;
            document.getElementById('size-medium').checked = true;
            priceRange.value = 50000;
            priceValue.textContent = 'MWK 50,000';
        });
    </script>

</body>
</html>
